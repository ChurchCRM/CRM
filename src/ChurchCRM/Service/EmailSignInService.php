<?php

namespace ChurchCRM\Service;

use ChurchCRM\dto\SystemConfig;
use ChurchCRM\Emails\portal\MemberSignInEmail;
use ChurchCRM\Emails\portal\MemberSignInNoticeEmail;
use ChurchCRM\model\ChurchCRM\Person;
use ChurchCRM\model\ChurchCRM\PersonQuery;
use ChurchCRM\model\ChurchCRM\PersonSigninRequest;
use ChurchCRM\model\ChurchCRM\PersonSigninRequestQuery;
use ChurchCRM\Portal\Auth\EmailSignInOutcome;
use ChurchCRM\Portal\Auth\MemberSignInRule;
use ChurchCRM\Portal\Auth\PortalSessionStarter;
use ChurchCRM\Portal\Auth\PortalSignInRefusedException;
use ChurchCRM\Portal\Auth\SignInAudit;
use ChurchCRM\Portal\Auth\SignInAuditEvent;
use ChurchCRM\Portal\Auth\SignInCandidate;
use ChurchCRM\Portal\Auth\SignInMethod;
use ChurchCRM\Portal\Auth\SignInReason;
use ChurchCRM\Utils\AfterResponse;
use ChurchCRM\Utils\DateTimeUtils;
use ChurchCRM\Utils\KeyManagerUtils;
use ChurchCRM\Utils\LoggerUtils;
use DateTimeImmutable;
use Propel\Runtime\ActiveQuery\Criteria;
use Throwable;

/**
 * Member sign-in by email (member sign-in design §6.1): a request emails one
 * code and one link, and either of them, used once, starts a portal session
 * through the shared step.
 *
 * Nothing here tells the person asking whether an address may sign in (D3):
 * the caller shows the same answer whatever `request()` did, and every email
 * goes out after that answer has reached the browser, so SMTP never sets how
 * long the answer takes.
 *
 * At rest the link token is a SHA-256 hash and the browser binding a SHA-256
 * hash of the cookie value. The code is an HMAC keyed by a server secret,
 * because a six-digit code's SHA-256 is found by trying every code.
 */
final class EmailSignInService
{
    public const BUCKET_ADDRESS = 'portal-signin.address';
    public const BUCKET_IP = 'portal-signin.ip';
    public const BUCKET_PERSON = 'portal-signin.person';
    public const BUCKET_OFFICE_NOTICE = 'portal-signin.office-notice';
    public const BUCKET_THROTTLE_AUDIT = 'portal-signin.throttle-audit';

    public const MAX_CODE_ATTEMPTS = 5;

    private const REQUESTS_PER_ADDRESS_PER_HOUR = 5;
    private const EMAILS_PER_PERSON_PER_DAY = 5;
    private const HOUR = 3600;
    private const DAY = 86400;
    private const DB_TIME = 'Y-m-d H:i:s';

    public static function isEnabled(): bool
    {
        return SystemConfig::getBooleanValue('bMemberSignIn');
    }

    public static function newBrowserSecret(): string
    {
        return bin2hex(random_bytes(32));
    }

    public static function isWellFormedEmail(string $typedEmail): bool
    {
        return filter_var(trim($typedEmail), FILTER_VALIDATE_EMAIL) !== false;
    }

    /**
     * Handles one "email me a code" request from the browser holding
     * `$browserSecret`. Sends at most one email to the person, or a notice to
     * the office list, and audits what it did.
     */
    public function request(string $typedEmail, string $browserSecret): void
    {
        // Every answer ends the same way, with or without an email to follow.
        AfterResponse::finishEarly();
        $now = self::now();
        $this->removeExpiredRequests($now);

        $email = MemberSignInRule::normalizeEmail($typedEmail);
        $ipKey = RateLimiter::clientIpKey();

        $perIp = new RateLimiter(self::BUCKET_IP, SystemConfig::getIntValue('iMemberSignInRequestsPerIP'), self::HOUR);
        if (!$perIp->attempt($ipKey)) {
            $this->auditThrottled(SignInReason::THROTTLED_IP, $ipKey, null, $email);

            return;
        }
        $perAddress = new RateLimiter(self::BUCKET_ADDRESS, self::REQUESTS_PER_ADDRESS_PER_HOUR, self::HOUR);
        if (!$perAddress->attempt($email)) {
            $this->auditThrottled(SignInReason::THROTTLED_ADDRESS, $email, null, $email);

            return;
        }

        $candidate = MemberSignInRule::fromSettings()->findSignInCandidate($email);
        if (!$candidate->isAllowed()) {
            $this->refuse($candidate);

            return;
        }

        $person = $candidate->person;
        $personId = (int) $person->getId();
        $perPerson = new RateLimiter(self::BUCKET_PERSON, self::EMAILS_PER_PERSON_PER_DAY, self::DAY);
        if (!$perPerson->attempt((string) $personId)) {
            $this->auditThrottled(SignInReason::THROTTLED_PERSON, (string) $personId, $personId, $email);

            return;
        }

        $this->sendSignInEmail($person, $browserSecret, $now);
    }

    /**
     * The live request a link belongs to, for the confirm page. Reads only:
     * opening a link changes nothing, so a mail scanner can't spend it.
     */
    public function findLinkRequest(string $token): ?PersonSigninRequest
    {
        return $this->liveRequests(self::now())
            ->filterByTokenHash(self::hashToken($token))
            ->findOne();
    }

    public function signInWithLink(string $token): EmailSignInOutcome
    {
        $now = self::now();
        $request = PersonSigninRequestQuery::create()->findOneByTokenHash(self::hashToken($token));
        if (!$request instanceof PersonSigninRequest || !$this->spend($request, $now)) {
            return EmailSignInOutcome::LINK_UNAVAILABLE;
        }

        return $this->startSession($request, SignInMethod::EMAIL_LINK);
    }

    /**
     * A code works only in the browser that asked for it, against that
     * browser's newest live request. The fifth wrong code cancels the request.
     */
    public function signInWithCode(string $code, string $browserSecret): EmailSignInOutcome
    {
        $code = preg_replace('/\s+/', '', $code) ?? '';
        if ($browserSecret === '' || !preg_match('/^\d{6}$/', $code)) {
            return EmailSignInOutcome::CODE_NOT_ACCEPTED;
        }

        $now = self::now();
        $request = $this->liveRequests($now)
            ->filterByBrowserHash(self::hashBrowserSecret($browserSecret))
            ->orderById(Criteria::DESC)
            ->findOne();
        if (!$request instanceof PersonSigninRequest) {
            return EmailSignInOutcome::CODE_NOT_ACCEPTED;
        }

        if (!hash_equals((string) $request->getCodeHash(), self::hashCode((string) $request->getTokenHash(), $code))) {
            $this->recordWrongCode($request, $now);

            return EmailSignInOutcome::CODE_NOT_ACCEPTED;
        }

        if (!$this->spend($request, $now)) {
            return EmailSignInOutcome::CODE_NOT_ACCEPTED;
        }

        return $this->startSession($request, SignInMethod::EMAIL_CODE);
    }

    private function sendSignInEmail(Person $person, string $browserSecret, DateTimeImmutable $now): void
    {
        $personId = (int) $person->getId();
        $address = trim((string) $person->getEmail());
        $token = bin2hex(random_bytes(32));
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $tokenHash = self::hashToken($token);
        $expiresAt = $now->modify('+' . max(1, SystemConfig::getIntValue('iSignInLinkMinutes')) . ' minutes');

        PersonSigninRequestQuery::create()
            ->filterByPersonId($personId)
            ->filterByUsedAt(null, Criteria::ISNULL)
            ->filterByCancelledAt(null, Criteria::ISNULL)
            ->update(['CancelledAt' => $now->format(self::DB_TIME)]);

        (new PersonSigninRequest())
            ->setPersonId($personId)
            ->setEmail($address)
            ->setTokenHash($tokenHash)
            ->setCodeHash(self::hashCode($tokenHash, $code))
            ->setBrowserHash(self::hashBrowserSecret($browserSecret))
            ->setAttempts(0)
            ->setCreatedAt($now)
            ->setExpiresAt($expiresAt)
            ->save();

        $auditId = SignInAudit::record(SignInAuditEvent::EMAIL_REQUESTED, $personId, null, null, null, $address);

        AfterResponse::defer(static function () use ($person, $code, $token, $expiresAt, $auditId): void {
            $sent = false;
            try {
                $sent = (new MemberSignInEmail($person, $code, $token, $expiresAt))->send();
            } catch (Throwable $e) {
                LoggerUtils::getAppLogger()->error('Could not send the Member Portal sign-in email', [
                    'personId' => (int) $person->getId(),
                    'exception' => $e->getMessage(),
                ]);
            }
            if (!$sent && $auditId !== null) {
                SignInAudit::setDetail($auditId, 'send_failed');
            }
        });
    }

    private function refuse(SignInCandidate $candidate): void
    {
        SignInAudit::record(
            SignInAuditEvent::REFUSED,
            $candidate->person instanceof Person ? (int) $candidate->person->getId() : null,
            null,
            $candidate->refusal,
            null,
            $candidate->email,
            $candidate->matchedPersonIds === [] ? null : 'matched:' . implode(',', $candidate->matchedPersonIds),
        );

        $this->noticeOffice($candidate);
    }

    /**
     * The office list hears about a refused address at most once an hour (A6).
     */
    private function noticeOffice(SignInCandidate $candidate): void
    {
        $recipients = self::officeAddresses();
        if ($recipients === [] || !(new RateLimiter(self::BUCKET_OFFICE_NOTICE, 1, self::HOUR))->attempt($candidate->email)) {
            return;
        }

        $refusedAt = self::now();
        AfterResponse::defer(static function () use ($recipients, $candidate, $refusedAt): void {
            $matchedPeople = $candidate->matchedPersonIds === []
                ? []
                : PersonQuery::create()->filterById($candidate->matchedPersonIds, Criteria::IN)->orderById()->find()->getData();

            try {
                $notice = new MemberSignInNoticeEmail($recipients, $candidate->email, $candidate->refusal ?? SignInReason::NO_MATCH, $matchedPeople, $refusedAt);
                if (!$notice->send()) {
                    LoggerUtils::getAppLogger()->warning('Could not send the Member Portal sign-in notice', ['error' => $notice->getError()]);
                }
            } catch (Throwable $e) {
                LoggerUtils::getAppLogger()->error('Could not send the Member Portal sign-in notice', ['exception' => $e->getMessage()]);
            }
        });
    }

    /**
     * @return string[] each office-list person's own email
     */
    private static function officeAddresses(): array
    {
        $ids = MemberSignInRule::idList(SystemConfig::getValue('sMemberSignInNotificationRecipientIDs'));
        if ($ids === []) {
            return [];
        }

        $addresses = [];
        foreach (PersonQuery::create()->filterById($ids, Criteria::IN)->filterByDateDeceased(null, Criteria::ISNULL)->find() as $person) {
            $address = trim((string) $person->getEmail());
            if ($address !== '') {
                $addresses[] = $address;
            }
        }

        return array_values(array_unique($addresses));
    }

    /**
     * An attacker who stays over a limit would otherwise write an audit row
     * per request; one row per limit and key an hour says the same thing.
     */
    private function auditThrottled(SignInReason $reason, string $key, ?int $personId, string $email): void
    {
        if (!(new RateLimiter(self::BUCKET_THROTTLE_AUDIT, 1, self::HOUR))->attempt($reason->value . ':' . $key)) {
            return;
        }

        SignInAudit::record(SignInAuditEvent::THROTTLED, $personId, null, $reason, null, $email);
    }

    private function recordWrongCode(PersonSigninRequest $request, DateTimeImmutable $now): void
    {
        for ($try = 0; $try < self::MAX_CODE_ATTEMPTS; $try++) {
            $seen = (int) $request->getAttempts();
            $attempts = $seen + 1;
            $values = ['Attempts' => $attempts];
            if ($attempts >= self::MAX_CODE_ATTEMPTS) {
                $values['CancelledAt'] = $now->format(self::DB_TIME);
            }

            $updated = PersonSigninRequestQuery::create()
                ->filterById($request->getId())
                ->filterByAttempts($seen)
                ->filterByCancelledAt(null, Criteria::ISNULL)
                ->update($values);
            if ($updated === 1) {
                if ($attempts >= self::MAX_CODE_ATTEMPTS) {
                    SignInAudit::record(SignInAuditEvent::THROTTLED, (int) $request->getPersonId(), SignInMethod::EMAIL_CODE, SignInReason::TOO_MANY_CODES, null, (string) $request->getEmail());
                }

                return;
            }

            $request = PersonSigninRequestQuery::create()->findPk($request->getId());
            if (!$request instanceof PersonSigninRequest || $request->getCancelledAt() !== null) {
                return;
            }
        }
    }

    /**
     * Spends a request in one conditional UPDATE, so two browsers pressing
     * Sign in at once can't both succeed.
     */
    private function spend(PersonSigninRequest $request, DateTimeImmutable $now): bool
    {
        return $this->liveRequests($now)
            ->filterById($request->getId())
            ->update(['UsedAt' => $now->format(self::DB_TIME)]) === 1;
    }

    private function startSession(PersonSigninRequest $request, SignInMethod $method): EmailSignInOutcome
    {
        try {
            PortalSessionStarter::start((int) $request->getPersonId(), $method, null, (string) $request->getEmail());
        } catch (PortalSignInRefusedException) {
            return EmailSignInOutcome::REFUSED;
        }

        return EmailSignInOutcome::SIGNED_IN;
    }

    private function liveRequests(DateTimeImmutable $now): PersonSigninRequestQuery
    {
        return PersonSigninRequestQuery::create()
            ->filterByUsedAt(null, Criteria::ISNULL)
            ->filterByCancelledAt(null, Criteria::ISNULL)
            ->filterByExpiresAt($now->format(self::DB_TIME), Criteria::GREATER_THAN);
    }

    private function removeExpiredRequests(DateTimeImmutable $now): void
    {
        PersonSigninRequestQuery::create()
            ->filterByExpiresAt($now->format(self::DB_TIME), Criteria::LESS_EQUAL)
            ->delete();
    }

    private static function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('now', DateTimeUtils::getConfiguredTimezone());
    }

    private static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    private static function hashBrowserSecret(string $browserSecret): string
    {
        return hash('sha256', $browserSecret);
    }

    private static function hashCode(string $tokenHash, string $code): string
    {
        $key = hash_hmac('sha256', 'member-sign-in-code', (string) KeyManagerUtils::getTwoFASecretKey());

        return hash_hmac('sha256', $tokenHash . ':' . $code, $key);
    }
}
