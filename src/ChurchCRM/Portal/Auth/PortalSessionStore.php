<?php

namespace ChurchCRM\Portal\Auth;

use ChurchCRM\Utils\LoggerUtils;
use RuntimeException;

/**
 * The portal session's place in the PHP session. It sits beside the staff
 * session (`AuthenticationProvider`) and never inside it, so ending one leaves
 * the other alone. Only `PortalSessionStarter` saves a principal.
 */
final class PortalSessionStore
{
    private const PRINCIPAL_KEY = 'PortalPrincipal';
    private const LAST_ACTIVITY_KEY = 'PortalLastActivity';
    private const DEVICE_KEY = 'PortalDeviceId';

    public static function load(): ?PortalPrincipal
    {
        if (!isset($_SESSION) || !array_key_exists(self::PRINCIPAL_KEY, $_SESSION)) {
            return null;
        }

        return PortalPrincipal::fromArray($_SESSION[self::PRINCIPAL_KEY]);
    }

    public static function has(): bool
    {
        return self::load() instanceof PortalPrincipal;
    }

    public static function save(PortalPrincipal $principal): void
    {
        $_SESSION[self::PRINCIPAL_KEY] = $principal->toArray();
        unset($_SESSION[self::DEVICE_KEY]);
        self::touch();
    }

    /** The remembered device this session started from or was kept on (design §6.2). */
    public static function deviceId(): ?int
    {
        $value = $_SESSION[self::DEVICE_KEY] ?? null;

        return is_int($value) ? $value : null;
    }

    public static function setDeviceId(int $deviceId): void
    {
        $_SESSION[self::DEVICE_KEY] = $deviceId;
    }

    public static function lastActivity(): ?int
    {
        $value = $_SESSION[self::LAST_ACTIVITY_KEY] ?? null;

        return is_int($value) ? $value : null;
    }

    public static function touch(): void
    {
        $_SESSION[self::LAST_ACTIVITY_KEY] = time();
    }

    public static function clear(): void
    {
        unset($_SESSION[self::PRINCIPAL_KEY], $_SESSION[self::LAST_ACTIVITY_KEY], $_SESSION[self::DEVICE_KEY]);
    }

    /**
     * @throws RuntimeException when the id cannot be rotated; the caller must not save a principal
     */
    public static function regenerateId(): void
    {
        $reason = match (true) {
            session_status() !== PHP_SESSION_ACTIVE => 'no active session',
            headers_sent() => 'headers already sent',
            !session_regenerate_id(true) => 'session_regenerate_id failed',
            default => null,
        };
        if ($reason !== null) {
            LoggerUtils::getAppLogger()->error('Portal sign-in stopped: the session id could not be rotated', ['reason' => $reason]);

            throw new RuntimeException('The portal session id could not be rotated: ' . $reason);
        }
    }
}
