<?php

namespace ChurchCRM\Service;

use ChurchCRM\dto\ChurchMetaData;
use ChurchCRM\Emails\signin\PasskeyAddedEmail;
use ChurchCRM\model\ChurchCRM\Person;
use ChurchCRM\model\ChurchCRM\PersonPasskey;
use ChurchCRM\model\ChurchCRM\PersonPasskeyQuery;
use ChurchCRM\model\ChurchCRM\PersonSigninQuery;
use ChurchCRM\model\ChurchCRM\User;
use ChurchCRM\Portal\Auth\PasskeySignInOutcome;
use ChurchCRM\Portal\Auth\PasskeySite;
use ChurchCRM\Portal\Auth\PortalPrincipal;
use ChurchCRM\Portal\Auth\PortalSessionStarter;
use ChurchCRM\Portal\Auth\PortalSessionStore;
use ChurchCRM\Portal\Auth\PortalSignInRefusedException;
use ChurchCRM\Portal\Auth\SignInAudit;
use ChurchCRM\Portal\Auth\SignInAuditEvent;
use ChurchCRM\Portal\Auth\SignInEmailPolicy;
use ChurchCRM\Portal\Auth\SignInMethod;
use ChurchCRM\Portal\Auth\SignInReason;
use ChurchCRM\Portal\MemberLanguage;
use ChurchCRM\Utils\DateTimeUtils;
use ChurchCRM\Utils\LoggerUtils;
use DateTimeImmutable;
use DateTimeInterface;
use lbuchs\WebAuthn\WebAuthn;
use Propel\Runtime\ActiveQuery\Criteria;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;
use stdClass;
use Throwable;

/**
 * Passkeys (member sign-in design §6.3, §14) on top of lbuchs/webauthn.
 *
 * - A passkey is added only within 15 minutes of an email sign-in, so a
 *   copied session or device cookie can't turn itself into a lasting
 *   credential. User verification is required both when it is added and at
 *   every sign-in.
 * - Each ceremony's challenge lives in the PHP session, is used once, and
 *   expires. The browser's origin must be this site's host exactly.
 * - A passkey is a discoverable credential: `/portal/login` asks for one with
 *   no account named, and finds it by the SHA-256 of its raw credential id.
 * - A passkey sign-in goes through the shared step, so it fails whenever the
 *   person may no longer sign in, without removing the passkey.
 */
final class PasskeyService
{
    public const RECENT_EMAIL_SIGN_IN_MINUTES = 15;
    public const BUCKET_FAILURES_IP = 'portal-passkey.ip';

    private const SESSION_KEY = 'PortalPasskeyChallenge';
    private const OFFER_KEY = 'PortalPasskeyOffer';
    private const ADD = 'add';
    private const SIGN_IN = 'sign-in';
    private const SIGN_IN_CHALLENGES_KEPT = 5;
    private const ADD_SECONDS = 300;
    private const SIGN_IN_SECONDS = 600;
    private const CEREMONY_SECONDS = 300;
    private const FAILURES_PER_IP_PER_HOUR = 20;
    private const HOUR = 3600;
    private const DB_TIME = 'Y-m-d H:i:s';
    private const TRANSPORTS = ['ble', 'hybrid', 'internal', 'nfc', 'smart-card', 'usb'];
    private const MAX_CLIENT_DATA_BYTES = 8192;
    private const MAX_AUTHENTICATOR_BYTES = 65536;

    /**
     * "A recent email sign-in" (§6.3): this session itself was started by an
     * email code or link in the last 15 minutes. A session resumed from a
     * remembered device, or opened from a staff session, never counts.
     */
    public static function isRecentEmailSignIn(?PortalPrincipal $principal): bool
    {
        return $principal instanceof PortalPrincipal
            && $principal->method->isEmail()
            && $principal->startedAt->getTimestamp() >= time() - self::RECENT_EMAIL_SIGN_IN_MINUTES * 60;
    }

    /** Why this member can't add a passkey right now, in words for them, or null. */
    public static function addProblem(ServerRequestInterface $request, ?PortalPrincipal $principal): ?string
    {
        $siteProblem = PasskeySite::requestProblem($request);
        if ($siteProblem !== null) {
            return $siteProblem;
        }
        if (!self::isRecentEmailSignIn($principal)) {
            return self::needsRecentEmailSignIn();
        }

        return null;
    }

    /** After an email sign-in where the member ticked "This is my own device" (§6.3). */
    public static function offerAfterSignIn(ServerRequestInterface $request): void
    {
        if (PasskeySite::isAvailable($request)) {
            $_SESSION[self::OFFER_KEY] = true;
        }
    }

    /** Whether to show the one-time offer on this page. Asking spends it. */
    public static function takeOffer(): bool
    {
        if (!isset($_SESSION[self::OFFER_KEY])) {
            return false;
        }
        unset($_SESSION[self::OFFER_KEY]);

        return PasskeySite::isTurnedOn() && self::isRecentEmailSignIn(PortalSessionStore::load());
    }

    /**
     * The options for `navigator.credentials.create()`, binary fields in
     * base64url. Existing passkeys are excluded, so a device that already
     * holds one says so instead of adding a second.
     *
     * @return array<string, mixed>
     */
    public function addOptions(Person $person): array
    {
        $personId = (int) $person->getId();
        $userHandle = null;
        $exclude = [];
        foreach (PersonPasskeyQuery::create()->filterByPersonId($personId)->find() as $passkey) {
            $userHandle ??= self::fromBase64Url((string) $passkey->getUserHandle());
            $credentialId = self::fromBase64Url((string) $passkey->getCredentialId());
            if ($credentialId !== null) {
                $exclude[] = $credentialId;
            }
        }
        $userHandle ??= random_bytes(32);

        $email = trim((string) $person->getEmail());
        $webAuthn = self::webAuthn();
        $args = $webAuthn->getCreateArgs(
            $userHandle,
            $email !== '' ? $email : $person->getFullName(),
            $person->getFullName(),
            self::CEREMONY_SECONDS,
            'required',
            'required',
            null,
            $exclude
        );
        // Attestation is not checked against any root, so none is asked for.
        $args->publicKey->attestation = 'none';
        $args->publicKey->extensions = new stdClass();
        $_SESSION[self::SESSION_KEY][self::ADD] = [
            'challenge' => self::toBase64Url($webAuthn->getChallenge()->getBinaryString()),
            'personId' => $personId,
            'userHandle' => self::toBase64Url($userHandle),
            'expiresAt' => time() + self::ADD_SECONDS,
        ];

        return self::toArray($args->publicKey);
    }

    /**
     * Verifies a new passkey and keeps it, then tells the member by email.
     *
     * @param list<string> $transports what the browser reported, if anything
     *
     * @return string|null why it was not added, in words for the member, or null when it was
     */
    public function add(
        Person $person,
        ?PortalPrincipal $principal,
        string $clientDataJson,
        string $attestationObject,
        array $transports,
        string $userAgent,
    ): ?string {
        $personId = (int) $person->getId();
        $challenge = self::takeAddChallenge($personId);
        if ($challenge === null) {
            return gettext('That took too long. Please try again.');
        }
        if (!self::isRecentEmailSignIn($principal) || $principal->personId !== $personId) {
            return self::needsRecentEmailSignIn();
        }

        try {
            if (!self::isOwnClientData($clientDataJson, 'webauthn.create') || strlen($attestationObject) > self::MAX_AUTHENTICATOR_BYTES) {
                throw new RuntimeException('client data or attestation not accepted');
            }
            $created = self::webAuthn()->processCreate($clientDataJson, $attestationObject, $challenge['challenge'], true, true, false, false);
        } catch (Throwable $e) {
            LoggerUtils::getAuthLogger()->info('A passkey could not be added', ['personId' => $personId, 'exception' => $e->getMessage()]);

            return gettext('The passkey could not be added. Please try again.');
        }

        $credentialId = (string) $created->credentialId;
        $credentialHash = hash('sha256', $credentialId);
        if (PersonPasskeyQuery::create()->filterByCredentialHash($credentialHash)->exists()) {
            return gettext('This passkey is already on record.');
        }

        $now = self::now();
        $label = RememberedDeviceService::labelFor($userAgent);
        $knownTransports = array_values(array_intersect(self::TRANSPORTS, $transports));
        (new PersonPasskey())
            ->setPersonId($personId)
            ->setCredentialId(self::toBase64Url($credentialId))
            ->setCredentialHash($credentialHash)
            ->setPublicKey((string) $created->credentialPublicKey)
            ->setSignCount((string) ($created->signatureCounter ?? 0))
            ->setUserHandle(self::toBase64Url($challenge['userHandle'] ?? ''))
            ->setTransports($knownTransports === [] ? null : implode(',', $knownTransports))
            ->setLabel($label)
            ->setCreatedAt($now)
            ->save();

        SignInAudit::record(SignInAuditEvent::PASSKEY_ADDED, $personId, $principal->method, detail: $label);
        $this->sendAddedNotice($person, $label, $now);

        return null;
    }

    /**
     * The options for `navigator.credentials.get()` on `/portal/login`: no
     * account named, so the browser offers every passkey it holds for this site.
     *
     * @return array<string, mixed>
     */
    public function signInOptions(): array
    {
        $webAuthn = self::webAuthn();
        $args = $webAuthn->getGetArgs([], self::CEREMONY_SECONDS, true, true, true, true, true, 'required');
        $live = array_filter(
            (array) ($_SESSION[self::SESSION_KEY][self::SIGN_IN] ?? []),
            static fn (mixed $expiresAt): bool => is_int($expiresAt) && $expiresAt >= time()
        );
        $live[self::toBase64Url($webAuthn->getChallenge()->getBinaryString())] = time() + self::SIGN_IN_SECONDS;
        $_SESSION[self::SESSION_KEY][self::SIGN_IN] = array_slice($live, -self::SIGN_IN_CHALLENGES_KEPT, null, true);

        return self::toArray($args->publicKey);
    }

    /**
     * Signs in with a passkey assertion (binary fields as the browser sent
     * them). Failures are counted per IP address; past the limit nothing is
     * checked until the hour is up.
     */
    public function signIn(
        string $credentialId,
        string $clientDataJson,
        string $authenticatorData,
        string $signature,
        string $userHandle,
    ): PasskeySignInOutcome {
        $ipKey = RateLimiter::clientIpKey();
        $failures = new RateLimiter(self::BUCKET_FAILURES_IP, self::FAILURES_PER_IP_PER_HOUR, self::HOUR);
        if ($failures->tooManyAttempts($ipKey)) {
            $this->auditThrottled($ipKey);

            return PasskeySignInOutcome::FAILED;
        }

        $challenge = self::takeSignInChallenge($clientDataJson);
        if ($challenge === null) {
            return PasskeySignInOutcome::EXPIRED;
        }

        $passkey = $credentialId === '' ? null : PersonPasskeyQuery::create()->findOneByCredentialHash(hash('sha256', $credentialId));
        if (!$passkey instanceof PersonPasskey) {
            $failures->hit($ipKey);
            SignInAudit::record(SignInAuditEvent::REFUSED, null, SignInMethod::PASSKEY, SignInReason::PASSKEY_UNKNOWN);

            return PasskeySignInOutcome::FAILED;
        }

        $personId = (int) $passkey->getPersonId();
        if (!$this->verifyAssertion($passkey, $clientDataJson, $authenticatorData, $signature, $userHandle, $challenge)) {
            $failures->hit($ipKey);
            SignInAudit::record(SignInAuditEvent::REFUSED, $personId, SignInMethod::PASSKEY, SignInReason::PASSKEY_INVALID, detail: $passkey->getLabel());

            return PasskeySignInOutcome::FAILED;
        }

        try {
            PortalSessionStarter::start($personId, SignInMethod::PASSKEY);
        } catch (PortalSignInRefusedException) {
            return PasskeySignInOutcome::FAILED;
        }

        PersonPasskeyQuery::create()->filterById($passkey->getId())->update(['LastUsedAt' => self::now()->format(self::DB_TIME)]);

        return PasskeySignInOutcome::SIGNED_IN;
    }

    /**
     * @return list<array{id: int, label: string, addedAt: string, lastUsedAt: string}>
     */
    public function listForPerson(int $personId): array
    {
        $rows = [];
        $passkeys = PersonPasskeyQuery::create()
            ->filterByPersonId($personId)
            ->orderByCreatedAt(Criteria::DESC)
            ->orderById(Criteria::DESC)
            ->find();
        foreach ($passkeys as $passkey) {
            $rows[] = [
                'id' => (int) $passkey->getId(),
                'label' => (string) ($passkey->getLabel() ?? ''),
                'addedAt' => self::formatTime($passkey->getCreatedAt()),
                'lastUsedAt' => self::formatTime($passkey->getLastUsedAt()),
            ];
        }

        return $rows;
    }

    public static function countForPerson(int $personId): int
    {
        return PersonPasskeyQuery::create()->filterByPersonId($personId)->count();
    }

    /**
     * Removes one of the person's passkeys: by the member (no actor) or by an
     * administrator on the person's page (§11.3). False when it isn't theirs.
     */
    public function remove(int $personId, int $passkeyId, ?User $actor = null): bool
    {
        $passkey = PersonPasskeyQuery::create()->filterByPersonId($personId)->findPk($passkeyId);
        if (!$passkey instanceof PersonPasskey) {
            return false;
        }
        $passkey->delete();

        SignInAudit::record(
            SignInAuditEvent::PASSKEY_REMOVED,
            $personId,
            actorUserId: $actor === null ? null : (int) $actor->getId(),
            detail: $passkey->getLabel(),
        );

        return true;
    }

    /**
     * The unconfirmed address a member signed in with a passkey may confirm
     * from the Sign-in and devices page (§9), or null.
     */
    public static function emailToConfirm(Person $person, ?PortalPrincipal $principal): ?string
    {
        if ($principal?->method !== SignInMethod::PASSKEY || $principal->personId !== (int) $person->getId()) {
            return null;
        }
        $address = trim((string) PersonSigninQuery::create()->findPk((int) $person->getId())?->getUnconfirmedEmail());

        return $address !== '' && SignInEmailPolicy::sameAddress($address, $person->getEmail()) ? $address : null;
    }

    public static function toBase64Url(string $binary): string
    {
        return rtrim(strtr(base64_encode($binary), '+/', '-_'), '=');
    }

    public static function fromBase64Url(string $text): ?string
    {
        if ($text === '' || preg_match('/^[A-Za-z0-9_-]+$/', $text) !== 1) {
            return null;
        }
        $binary = base64_decode(strtr($text, '-_', '+/'), true);

        return $binary === false ? null : $binary;
    }

    private static function needsRecentEmailSignIn(): string
    {
        return sprintf(
            gettext('A passkey can be added only within %d minutes of signing in with your email address. Sign out, sign in again with your email, then come back here.'),
            self::RECENT_EMAIL_SIGN_IN_MINUTES
        );
    }

    private function verifyAssertion(
        PersonPasskey $passkey,
        string $clientDataJson,
        string $authenticatorData,
        string $signature,
        string $userHandle,
        string $challenge,
    ): bool {
        if ($userHandle !== '' && !hash_equals((string) $passkey->getUserHandle(), self::toBase64Url($userHandle))) {
            return false;
        }
        if (!self::isOwnClientData($clientDataJson, 'webauthn.get') || strlen($authenticatorData) > self::MAX_AUTHENTICATOR_BYTES) {
            return false;
        }

        $previous = (int) $passkey->getSignCount();
        try {
            $webAuthn = self::webAuthn();
            $webAuthn->processGet($clientDataJson, $authenticatorData, $signature, (string) $passkey->getPublicKey(), $challenge, $previous, true, true);
        } catch (Throwable $e) {
            LoggerUtils::getAuthLogger()->info('A passkey assertion was not accepted', ['passkeyId' => $passkey->getId(), 'exception' => $e->getMessage()]);

            return false;
        }

        // Synced passkeys keep no counter and report 0 on every use, which the
        // library returns as null. processGet() has already refused a counter
        // that did not go up, including a drop to 0 from a stored count.
        $counter = $webAuthn->getSignatureCounter() ?? 0;
        if ($counter === 0) {
            return $previous === 0;
        }

        // The conditional update makes two uses of one counter value fail, as a cloned key would.
        return PersonPasskeyQuery::create()
            ->filterById($passkey->getId())
            ->filterBySignCount($previous)
            ->update(['SignCount' => $counter]) === 1;
    }

    /**
     * Checks lbuchs/webauthn leaves to the caller or checks loosely: the
     * origin is this site's host exactly (not merely ending in it), and the
     * request is neither cross-origin nor token-bound.
     */
    private static function isOwnClientData(string $clientDataJson, string $type): bool
    {
        if (strlen($clientDataJson) > self::MAX_CLIENT_DATA_BYTES) {
            return false;
        }
        $clientData = json_decode($clientDataJson, true);
        if (!is_array($clientData) || ($clientData['type'] ?? null) !== $type || ($clientData['crossOrigin'] ?? false) === true) {
            return false;
        }
        if (($clientData['tokenBinding']['status'] ?? null) === 'present') {
            return false;
        }

        return is_string($clientData['origin'] ?? null) && PasskeySite::acceptsOrigin($clientData['origin']);
    }

    private static function webAuthn(): WebAuthn
    {
        $name = trim((string) ChurchMetaData::getChurchName());

        return new WebAuthn($name !== '' ? $name : 'ChurchCRM', PasskeySite::rpId(), null, true);
    }

    /**
     * @return array{challenge: string, userHandle: string|null}|null
     */
    private static function takeAddChallenge(int $personId): ?array
    {
        $stored = $_SESSION[self::SESSION_KEY][self::ADD] ?? null;
        unset($_SESSION[self::SESSION_KEY][self::ADD]);
        if (!is_array($stored) || (int) ($stored['expiresAt'] ?? 0) < time() || ($stored['personId'] ?? null) !== $personId) {
            return null;
        }
        $challenge = self::fromBase64Url((string) ($stored['challenge'] ?? ''));
        if ($challenge === null) {
            return null;
        }

        return [
            'challenge' => $challenge,
            'userHandle' => is_string($stored['userHandle'] ?? null) ? self::fromBase64Url($stored['userHandle']) : null,
        ];
    }

    /**
     * The login page may ask for options more than once (the autofill request,
     * then the button), so each sign-in challenge waits under its own value
     * until the answer names it. Each is used once.
     */
    private static function takeSignInChallenge(string $clientDataJson): ?string
    {
        $clientData = json_decode($clientDataJson, true);
        $named = is_array($clientData) && is_string($clientData['challenge'] ?? null) ? $clientData['challenge'] : '';
        $expiresAt = $_SESSION[self::SESSION_KEY][self::SIGN_IN][$named] ?? null;
        if ($named === '' || $expiresAt === null) {
            return null;
        }
        unset($_SESSION[self::SESSION_KEY][self::SIGN_IN][$named]);

        return is_int($expiresAt) && $expiresAt >= time() ? self::fromBase64Url($named) : null;
    }

    private function auditThrottled(string $ipKey): void
    {
        if (!(new RateLimiter(EmailSignInService::BUCKET_THROTTLE_AUDIT, 1, self::HOUR))->attempt('passkey:' . $ipKey)) {
            return;
        }

        SignInAudit::record(SignInAuditEvent::THROTTLED, null, SignInMethod::PASSKEY, SignInReason::THROTTLED_IP);
    }

    private function sendAddedNotice(Person $person, ?string $label, DateTimeImmutable $addedAt): void
    {
        if (trim((string) $person->getEmail()) === '') {
            return;
        }

        try {
            MemberLanguage::inLanguageOf((int) $person->getId(), static fn (): bool => (new PasskeyAddedEmail($person, $label, $addedAt))->send());
        } catch (Throwable $e) {
            LoggerUtils::getAppLogger()->error('Could not send the passkey notice', [
                'personId' => (int) $person->getId(),
                'exception' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private static function toArray(stdClass $options): array
    {
        return json_decode(json_encode($options, JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR);
    }

    private static function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('now', DateTimeUtils::getConfiguredTimezone());
    }

    private static function formatTime(mixed $value): string
    {
        return $value instanceof DateTimeInterface ? DateTimeUtils::formatDate($value->format(self::DB_TIME), true) : '';
    }
}
