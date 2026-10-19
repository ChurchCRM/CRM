<?php

namespace ChurchCRM\Shepherd;

use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\model\ChurchCRM\ListOption;
use ChurchCRM\model\ChurchCRM\ListOptionQuery;
use ChurchCRM\model\ChurchCRM\Person;
use ChurchCRM\model\ChurchCRM\PersonQuery;
use ChurchCRM\model\ChurchCRM\UserQuery;
use ChurchCRM\Service\UserService;
use ChurchCRM\Utils\LoggerUtils;
use DateTime;
use Propel\Runtime\Propel;

final class SignupService
{
    public const GENERIC_SUBMISSION_MESSAGE = 'If the information can be accepted, a verification message will arrive shortly.';

    public function __construct(private readonly SignupRequestRepository $repository = new SignupRequestRepository())
    {
    }

    public function submit(array $input, string $ipAddress): void
    {
        $this->repository->ensureSchema();
        $ipHash = $this->hashIp($ipAddress);

        if ($this->repository->isRateLimited($ipHash)) {
            $this->repository->audit('signup_rate_limited', null, null, $ipHash);
            return;
        }
        $this->repository->audit('signup_submitted', null, null, $ipHash);

        $normalized = $this->validateSignup($input);
        if ($normalized === null || !empty($input['website'])) {
            $this->repository->audit('signup_rejected_validation', null, null, $ipHash);
            return;
        }

        if (UserQuery::create()->findOneByUserName($normalized['username']) !== null) {
            $this->repository->audit('signup_duplicate', null, null, $ipHash, ['field' => 'username']);
            return;
        }

        $rawToken = bin2hex(random_bytes(32));
        $requestId = $this->repository->create($normalized, hash('sha256', $rawToken), $ipHash);
        if ($requestId === null) {
            $this->repository->audit('signup_duplicate', null, null, $ipHash);
            return;
        }

        $verificationUrl = rtrim(SystemURLs::getURL(), '/') . '/session/signup/verify?token=' . rawurlencode($rawToken);
        $email = new ShepherdEmail(
            $normalized['email'],
            $normalized['first_name'],
            'Verify your account request',
            'Please verify your email address. Verification records your request for an administrator to review; it does not grant access.',
            $verificationUrl,
            'Verify email address'
        );

        if ($email->send()) {
            $this->repository->audit('verification_sent', $requestId, null, $ipHash);
        } else {
            LoggerUtils::getAppLogger()->error('Shepherd verification email failed for request ' . $requestId . ': ' . $email->getError());
            $this->repository->audit('verification_email_failed', $requestId, null, $ipHash);
        }
    }

    public function verify(string $rawToken, string $ipAddress): bool
    {
        $this->repository->ensureSchema();
        if (!preg_match('/^[a-f0-9]{64}$/', $rawToken)) {
            return false;
        }

        $request = $this->repository->findByVerificationToken(hash('sha256', $rawToken));
        if ($request === null || !$this->repository->verify((int) $request['id'])) {
            return false;
        }

        $this->repository->audit('email_verified', (int) $request['id'], null, $this->hashIp($ipAddress));
        return true;
    }

    public function approve(int $requestId, string $profile, int $existingPersonId, int $reviewerId): array
    {
        $this->repository->ensureSchema();
        if (!$this->repository->claimForApproval($requestId, $reviewerId)) {
            throw new \RuntimeException('This request is no longer awaiting approval.');
        }

        $request = $this->repository->find($requestId);
        if ($request === null) {
            $this->repository->releaseApprovalClaim($requestId);
            throw new \RuntimeException('The signup request could not be found.');
        }

        $person = null;
        $createdVisitor = false;
        $userCreated = false;
        try {
            $person = $existingPersonId > 0
                ? PersonQuery::create()->findPk($existingPersonId)
                : $this->createVisitor($request, $reviewerId);
            $createdVisitor = $existingPersonId <= 0;

            if ($person === null) {
                throw new \RuntimeException('The selected person could not be found.');
            }

            $userService = new UserService();
            $permissions = array_replace(['manageMinistries' => 0, 'manageMyMinistries' => 0, 'addEvent' => false], $this->permissionsForProfile($profile));
            $userService->createUser((int) $person->getId(), $permissions, (string) $request['username'], false);
            $userCreated = true;

            $rawToken = bin2hex(random_bytes(32));
            if (!$this->repository->approve($requestId, $reviewerId, (int) $person->getId(), hash('sha256', $rawToken))) {
                throw new \RuntimeException('The account was created but the request could not be finalized. Contact a system administrator.');
            }
            $this->sendPasswordSetupEmail($request, $rawToken);
            $this->repository->audit('request_approved', $requestId, $reviewerId, null, ['profile' => $profile, 'person_id' => $person->getId()]);

            return ['person' => $person, 'profile' => $profile];
        } catch (\Throwable $exception) {
            $currentRequest = $this->repository->find($requestId);
            if (($currentRequest['status'] ?? null) === 'approving') {
                try {
                    if ($userCreated && $person !== null) {
                        UserQuery::create()->findPk((int) $person->getId())?->delete();
                    }
                    if ($createdVisitor && $person !== null) {
                        $person->delete();
                    }
                } catch (\Throwable $cleanupException) {
                    LoggerUtils::getAppLogger()->error('Shepherd approval cleanup failed: ' . $cleanupException->getMessage());
                }
            }
            $this->repository->releaseApprovalClaim($requestId);
            throw $exception;
        }
    }

    public function reject(int $requestId, int $reviewerId, string $reason): bool
    {
        $reason = trim($reason);
        if ($reason === '') {
            throw new \InvalidArgumentException('A rejection reason is required.');
        }
        $reason = mb_substr($reason, 0, 500);
        $this->repository->ensureSchema();
        $request = $this->repository->find($requestId);
        if ($request === null || !$this->repository->reject($requestId, $reviewerId, $reason)) {
            return false;
        }

        $email = new ShepherdEmail(
            (string) $request['email'],
            (string) $request['first_name'],
            'Account request update',
            'Your Shepherd account request was not approved. Please contact the church office if you believe this was in error.'
        );
        if (!$email->send()) {
            LoggerUtils::getAppLogger()->warning('Shepherd rejection email failed for request ' . $requestId . ': ' . $email->getError());
        }
        $this->repository->audit('request_rejected', $requestId, $reviewerId);
        return true;
    }

    public function resendPasswordSetup(int $requestId, int $reviewerId): bool
    {
        $this->repository->ensureSchema();
        $request = $this->repository->find($requestId);
        if ($request === null || $request['status'] !== 'approved' || empty($request['linked_person_id'])) {
            return false;
        }

        $rawToken = bin2hex(random_bytes(32));
        if (!$this->repository->renewPasswordToken($requestId, hash('sha256', $rawToken))) {
            return false;
        }
        $this->sendPasswordSetupEmail($request, $rawToken);
        $this->repository->audit('password_setup_resent', $requestId, $reviewerId);
        return true;
    }

    public function getPasswordRequest(string $rawToken): ?array
    {
        $this->repository->ensureSchema();
        if (!preg_match('/^[a-f0-9]{64}$/', $rawToken)) {
            return null;
        }
        return $this->repository->findByPasswordToken(hash('sha256', $rawToken));
    }

    public function setPassword(string $rawToken, string $password, string $confirmation, string $ipAddress): bool
    {
        $request = $this->getPasswordRequest($rawToken);
        if ($request === null || !hash_equals($password, $confirmation)) {
            return false;
        }
        if (strlen($password) < SystemConfig::getIntValue('iMinPasswordLength')) {
            throw new \InvalidArgumentException('Your password is too short.');
        }

        $disallowed = array_filter(array_map('trim', explode(',', strtolower((string) SystemConfig::getValue('aDisallowedPasswords')))));
        $disallowed[] = strtolower((string) $request['first_name']);
        $disallowed[] = strtolower((string) $request['last_name']);
        if (in_array(strtolower($password), $disallowed, true)) {
            throw new \InvalidArgumentException('Please choose a less obvious password.');
        }

        $user = UserQuery::create()->findPk((int) $request['linked_person_id']);
        if ($user === null) {
            return false;
        }

        $connection = Propel::getWriteConnection('default');
        $connection->beginTransaction();
        try {
            if (!$this->repository->consumePasswordToken((int) $request['id'])) {
                $connection->rollBack();
                return false;
            }
            $user->updatePassword($password);
            $user->setNeedPasswordChange(false);
            $user->setFailedLogins(0);
            $user->save();
            $connection->commit();
        } catch (\Throwable $exception) {
            $connection->rollBack();
            throw $exception;
        }
        $this->repository->audit('password_setup_completed', (int) $request['id'], null, $this->hashIp($ipAddress));
        return true;
    }

    private function validateSignup(array $input): ?array
    {
        $firstName = trim((string) ($input['first_name'] ?? ''));
        $lastName = trim((string) ($input['last_name'] ?? ''));
        $email = strtolower(trim((string) ($input['email'] ?? '')));
        // Same namespace, same rule, one definition. This validated the
        // identical pattern inline before Username existed.
        $username = Username::canonical((string) ($input['username'] ?? ''));
        $note = trim((string) ($input['note'] ?? ''));

        if ($firstName === '' || $lastName === '' || mb_strlen($firstName) > 100 || mb_strlen($lastName) > 100) {
            return null;
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 254) {
            return null;
        }
        if ($username === null || mb_strlen($note) > 2000) {
            return null;
        }

        return [
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => $email,
            'username' => $username,
            'note' => $note,
        ];
    }

    private function createVisitor(array $request, int $reviewerId): Person
    {
        $visitorClassification = ListOptionQuery::create()
            ->filterById(1)
            ->filterByOptionName('Visitor')
            ->findOne();

        if ($visitorClassification === null) {
            $connection = Propel::getWriteConnection('default');
            $statement = $connection->prepare('SELECT COALESCE(MAX(lst_OptionID), 0) + 1 FROM list_lst WHERE lst_ID = 1');
            $statement->execute();
            $nextId = (int) $statement->fetchColumn();
            $visitorClassification = new ListOption();
            $visitorClassification->setId(1)
                ->setOptionId($nextId)
                ->setOptionSequence($nextId)
                ->setOptionName('Visitor')
                ->save();
        }

        $person = new Person();
        $person->setFirstName((string) $request['first_name'])
            ->setLastName((string) $request['last_name'])
            ->setEmail((string) $request['email'])
            ->setClsId((int) $visitorClassification->getOptionId())
            ->setEnteredBy($reviewerId)
            ->setEditedBy($reviewerId)
            ->setDateEntered(new DateTime())
            ->setDateLastEdited(new DateTime());

        if (!$person->validate()) {
            throw new \RuntimeException('The Visitor person record did not pass validation.');
        }
        $person->save();
        return $person;
    }

    private function permissionsForProfile(string $profile): array
    {
        return match ($profile) {
            'self_service' => [
                'admin' => 0, 'editSelf' => 1, 'addRecords' => 0, 'editRecords' => 0,
                'deleteRecords' => 0, 'menuOptions' => 0, 'manageGroups' => 0,
                'finance' => 0, 'manageFundraisers' => 0, 'notes' => 0,
            ],
            'staff' => [
                'admin' => 0, 'editSelf' => 0, 'addRecords' => 1, 'editRecords' => 1,
                'deleteRecords' => 0, 'menuOptions' => 0, 'manageGroups' => 1,
                'finance' => 0, 'manageFundraisers' => 0, 'notes' => 1,
            ],
            'treasurer' => [
                'admin' => 0, 'editSelf' => 0, 'addRecords' => 1, 'editRecords' => 1,
                'deleteRecords' => 0, 'menuOptions' => 0, 'manageGroups' => 1,
                'finance' => 1, 'manageFundraisers' => 1, 'notes' => 1,
            ],
            'administrator' => [
                'admin' => 1, 'editSelf' => 0, 'addRecords' => 1, 'editRecords' => 1,
                'deleteRecords' => 1, 'menuOptions' => 1, 'manageGroups' => 1,
                'finance' => 1, 'manageFundraisers' => 1, 'notes' => 1,
            ],
            default => throw new \InvalidArgumentException('Unknown permission profile.'),
        };
    }

    private function sendPasswordSetupEmail(array $request, string $rawToken): void
    {
        $url = rtrim(SystemURLs::getURL(), '/') . '/session/signup/password/' . rawurlencode($rawToken);
        $email = new ShepherdEmail(
            (string) $request['email'],
            (string) $request['first_name'],
            'Your account request was approved',
            'Your Shepherd account is ready. Use this single-use link within 48 hours to choose your password.',
            $url,
            'Choose a password'
        );
        if (!$email->send()) {
            LoggerUtils::getAppLogger()->error('Shepherd password setup email failed for request ' . $request['id'] . ': ' . $email->getError());
        }
    }

    private function hashIp(string $ipAddress): string
    {
        $key = getenv('SHEPHERD_AUDIT_KEY') ?: 'shepherd-local-audit-key';
        return hash_hmac('sha256', $ipAddress, $key);
    }
}
