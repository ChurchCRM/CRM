<?php

namespace ChurchCRM\Service;

use ChurchCRM\dto\SystemConfig;
use ChurchCRM\model\ChurchCRM\PersonDevice;
use ChurchCRM\model\ChurchCRM\PersonDeviceQuery;
use ChurchCRM\model\ChurchCRM\User;
use ChurchCRM\Portal\Auth\PortalSessionStarter;
use ChurchCRM\Portal\Auth\PortalSessionStore;
use ChurchCRM\Portal\Auth\PortalSignInRefusedException;
use ChurchCRM\Portal\Auth\RememberedDeviceToken;
use ChurchCRM\Portal\Auth\SignInAudit;
use ChurchCRM\Portal\Auth\SignInAuditEvent;
use ChurchCRM\Portal\Auth\SignInMethod;
use ChurchCRM\Portal\Auth\SignInReason;
use ChurchCRM\Utils\DateTimeUtils;
use DateTimeImmutable;
use DateTimeInterface;
use Propel\Runtime\ActiveQuery\Criteria;

/**
 * Remembered devices (member sign-in design §6.2): a device the member said is
 * their own starts a new portal session through the shared step once the old
 * one has gone idle or closed.
 *
 * A device works for `iRememberDeviceDays` from the day the member ticked the
 * box, counted with the current setting, so lowering it (or setting 0) cuts
 * existing devices short. Its token is replaced at each use, and the token it
 * replaced still signs in for a short grace, so two tabs or parallel requests
 * that resume together do not sign each other out.
 */
final class RememberedDeviceService
{
    public const KIND_BROWSER = 'browser';
    public const AUDIT_PREVIOUS_TOKEN = 'previous_token';

    private const DB_TIME = 'Y-m-d H:i:s';
    private const KEEP_REMOVED_DAYS = 30;
    private const PREVIOUS_TOKEN_GRACE_SECONDS = 60;

    public static function lifetimeDays(): int
    {
        return max(0, SystemConfig::getIntValue('iRememberDeviceDays'));
    }

    /**
     * The person's devices that can still sign in: not signed out, and within
     * both their stored expiry and today's lifetime setting.
     */
    public static function liveDevicesQuery(int $personId): PersonDeviceQuery
    {
        $now = self::now();

        return PersonDeviceQuery::create()
            ->filterByPersonId($personId)
            ->filterByKind(self::KIND_BROWSER)
            ->filterByRevokedAt(null, Criteria::ISNULL)
            ->filterByExpiresAt($now->format(self::DB_TIME), Criteria::GREATER_THAN)
            ->filterByCreatedAt($now->modify(sprintf('-%d days', self::lifetimeDays()))->format(self::DB_TIME), Criteria::GREATER_THAN);
    }

    public function remember(int $personId, SignInMethod $method, string $userAgent): ?RememberedDeviceToken
    {
        $days = self::lifetimeDays();
        if ($days === 0) {
            return null;
        }

        $now = self::now();
        $token = self::newToken();
        $label = self::labelFor($userAgent);
        $device = (new PersonDevice())
            ->setPersonId($personId)
            ->setTokenHash(self::hash($token))
            ->setKind(self::KIND_BROWSER)
            ->setLabel($label)
            ->setCreatedAt($now)
            ->setLastUsedAt($now)
            ->setExpiresAt($now->modify(sprintf('+%d days', $days)));
        $device->save();

        PortalSessionStore::setDeviceId((int) $device->getId());
        SignInAudit::record(SignInAuditEvent::DEVICE_ADDED, $personId, $method, detail: $label);
        $this->removeOldDevices($now);

        return new RememberedDeviceToken((int) $device->getId(), $token, self::expiryOf($device, $days));
    }

    /**
     * Starts a portal session from a device cookie, or returns null when the
     * cookie may not sign in. The token is spent before the shared step runs,
     * so a copied cookie works once at most outside the grace, and a refusal
     * signs the device out.
     */
    public function resume(string $token): ?RememberedDeviceToken
    {
        $hash = self::hash($token);
        $device = PersonDeviceQuery::create()
            ->filterByKind(self::KIND_BROWSER)
            ->findOneByTokenHash($hash);
        if (!$device instanceof PersonDevice) {
            return $this->resumeWithPreviousToken($hash);
        }

        $personId = (int) $device->getPersonId();
        if ($device->getRevokedAt() !== null) {
            SignInAudit::record(SignInAuditEvent::REFUSED, $personId, SignInMethod::DEVICE, SignInReason::DEVICE_REMOVED, detail: $device->getLabel());

            return null;
        }

        $now = self::now();
        $expiresAt = self::expiryOf($device, self::lifetimeDays());
        if ($expiresAt <= $now) {
            SignInAudit::record(SignInAuditEvent::REFUSED, $personId, SignInMethod::DEVICE, SignInReason::DEVICE_EXPIRED, detail: $device->getLabel());

            return null;
        }

        $newToken = self::newToken();
        $spent = PersonDeviceQuery::create()
            ->filterById($device->getId())
            ->filterByTokenHash($hash)
            ->filterByRevokedAt(null, Criteria::ISNULL)
            ->update([
                'TokenHash' => self::hash($newToken),
                'PreviousTokenHash' => $hash,
                'PreviousValidUntil' => $now->modify(sprintf('+%d seconds', self::PREVIOUS_TOKEN_GRACE_SECONDS))->format(self::DB_TIME),
                'LastUsedAt' => $now->format(self::DB_TIME),
            ]);
        if ($spent !== 1) {
            // A parallel request with the same cookie replaced the token first.
            return $this->resumeWithPreviousToken($hash);
        }

        if (!$this->startSession($device)) {
            return null;
        }
        $this->removeOldDevices($now);

        return new RememberedDeviceToken((int) $device->getId(), $newToken, $expiresAt);
    }

    /**
     * @return array<int, array{id: int, label: string, addedAt: string, lastUsedAt: string, isThisDevice: bool}>
     */
    public function listForPerson(int $personId, ?int $thisDeviceId): array
    {
        $devices = self::liveDevicesQuery($personId)
            ->orderByLastUsedAt(Criteria::DESC)
            ->orderById(Criteria::DESC)
            ->find();

        $rows = [];
        foreach ($devices as $device) {
            $rows[] = [
                'id' => (int) $device->getId(),
                'label' => (string) ($device->getLabel() ?? ''),
                'addedAt' => self::formatTime($device->getCreatedAt()),
                'lastUsedAt' => self::formatTime($device->getLastUsedAt() ?? $device->getCreatedAt()),
                'isThisDevice' => (int) $device->getId() === $thisDeviceId,
            ];
        }

        return $rows;
    }

    /**
     * The member, or an administrator on the person page, signs out one of the
     * person's devices. False when it isn't theirs or is already signed out.
     */
    public function signOutDevice(int $personId, int $deviceId, ?User $actor = null): bool
    {
        $device = PersonDeviceQuery::create()->filterByPersonId($personId)->findPk($deviceId);

        return $device instanceof PersonDevice && $this->revoke($device, SignInReason::SIGNED_OUT, $actor);
    }

    /** Sign out forgets the device this session was kept on. */
    public function forgetSessionDevice(): void
    {
        $deviceId = PortalSessionStore::deviceId();
        $device = $deviceId === null ? null : PersonDeviceQuery::create()->findPk($deviceId);
        if ($device instanceof PersonDevice) {
            $this->revoke($device, SignInReason::SIGNED_OUT);
        }
    }

    /** A new sign-in on this browser replaces whatever device cookie it held. */
    public function forgetToken(string $token): void
    {
        $device = PersonDeviceQuery::create()
            ->filterByKind(self::KIND_BROWSER)
            ->findOneByTokenHash(self::hash($token));
        if ($device instanceof PersonDevice) {
            $this->revoke($device, SignInReason::REPLACED);
        }
    }

    /** A session kept on a device ends once that device is signed out or removed. */
    public static function isSignedOut(int $deviceId): bool
    {
        return PersonDeviceQuery::create()
            ->filterById($deviceId)
            ->filterByRevokedAt(null, Criteria::ISNULL)
            ->count() === 0;
    }

    public static function recordUse(int $deviceId, DateTimeInterface $when): void
    {
        PersonDeviceQuery::create()
            ->filterById($deviceId)
            ->filterByRevokedAt(null, Criteria::ISNULL)
            ->update(['LastUsedAt' => $when->format(self::DB_TIME)]);
    }

    /**
     * A short browser and system label from the user agent, in names that need
     * no translation ("Chrome · macOS"). Null when neither is recognised.
     */
    public static function labelFor(string $userAgent): ?string
    {
        $has = static fn (string ...$needles): bool => array_filter($needles, static fn (string $needle): bool => str_contains($userAgent, $needle)) !== [];

        $browser = match (true) {
            $has('Edg/', 'EdgA/', 'EdgiOS/') => 'Edge',
            $has('OPR/', 'Opera') => 'Opera',
            $has('SamsungBrowser/') => 'Samsung Internet',
            $has('Firefox/', 'FxiOS/') => 'Firefox',
            $has('Chrome/', 'CriOS/', 'Chromium/') => 'Chrome',
            $has('Safari/') => 'Safari',
            default => null,
        };
        $system = match (true) {
            $has('iPhone') => 'iPhone',
            $has('iPad') => 'iPad',
            $has('Android') => 'Android',
            $has('CrOS') => 'ChromeOS',
            $has('Windows') => 'Windows',
            $has('Macintosh', 'Mac OS X') => 'macOS',
            $has('Linux') => 'Linux',
            default => null,
        };

        $label = implode(' · ', array_filter([$browser, $system]));

        return $label === '' ? null : $label;
    }

    /**
     * The values that sign a device out. Its previous token goes too, so it
     * signs nothing in for the rest of its grace.
     *
     * @return array{RevokedAt: string, PreviousTokenHash: null, PreviousValidUntil: null}
     */
    public static function revokedValues(DateTimeInterface $when): array
    {
        return ['RevokedAt' => $when->format(self::DB_TIME), 'PreviousTokenHash' => null, 'PreviousValidUntil' => null];
    }

    /**
     * The token a device replaced moments ago, still in its grace. It starts a
     * session without being replaced again; the browser already holds the
     * current token, so the result carries none for the cookie.
     */
    private function resumeWithPreviousToken(string $hash): ?RememberedDeviceToken
    {
        $now = self::now();
        $device = PersonDeviceQuery::create()
            ->filterByKind(self::KIND_BROWSER)
            ->filterByPreviousTokenHash($hash)
            ->filterByPreviousValidUntil($now->format(self::DB_TIME), Criteria::GREATER_THAN)
            ->filterByRevokedAt(null, Criteria::ISNULL)
            ->findOne();
        if (!$device instanceof PersonDevice) {
            return null;
        }

        $expiresAt = self::expiryOf($device, self::lifetimeDays());
        if ($expiresAt <= $now || !$this->startSession($device, self::AUDIT_PREVIOUS_TOKEN)) {
            return null;
        }

        return new RememberedDeviceToken((int) $device->getId(), null, $expiresAt);
    }

    private function startSession(PersonDevice $device, ?string $auditDetail = null): bool
    {
        try {
            PortalSessionStarter::start((int) $device->getPersonId(), SignInMethod::DEVICE, auditDetail: $auditDetail);
        } catch (PortalSignInRefusedException $e) {
            $this->revoke($device, $e->reason);

            return false;
        }
        PortalSessionStore::setDeviceId((int) $device->getId());

        return true;
    }

    private function revoke(PersonDevice $device, SignInReason $reason, ?User $actor = null): bool
    {
        $revoked = PersonDeviceQuery::create()
            ->filterById($device->getId())
            ->filterByRevokedAt(null, Criteria::ISNULL)
            ->update(self::revokedValues(self::now()));
        if ($revoked !== 1) {
            return false;
        }

        SignInAudit::record(
            SignInAuditEvent::DEVICE_REMOVED,
            (int) $device->getPersonId(),
            reason: $reason,
            actorUserId: $actor === null ? null : (int) $actor->getId(),
            detail: $device->getLabel(),
        );

        return true;
    }

    private function removeOldDevices(DateTimeImmutable $now): void
    {
        $cutoff = $now->modify(sprintf('-%d days', self::KEEP_REMOVED_DAYS))->format(self::DB_TIME);
        PersonDeviceQuery::create()->filterByKind(self::KIND_BROWSER)->filterByExpiresAt($cutoff, Criteria::LESS_THAN)->delete();
        PersonDeviceQuery::create()->filterByKind(self::KIND_BROWSER)->filterByRevokedAt($cutoff, Criteria::LESS_THAN)->delete();
    }

    private static function expiryOf(PersonDevice $device, int $days): DateTimeImmutable
    {
        $stored = DateTimeImmutable::createFromInterface($device->getExpiresAt());
        $bySetting = DateTimeImmutable::createFromInterface($device->getCreatedAt())->modify(sprintf('+%d days', $days));

        return min($stored, $bySetting);
    }

    private static function newToken(): string
    {
        return bin2hex(random_bytes(32));
    }

    private static function hash(string $token): string
    {
        return hash('sha256', $token);
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
