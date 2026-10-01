<?php

namespace ChurchCRM\Service;

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\model\ChurchCRM\User;
use ChurchCRM\model\ChurchCRM\UserQuery;
use ChurchCRM\Utils\LoggerUtils;

/**
 * Admin masquerade ("Login as User", issue #9843).
 *
 * An administrator can open a session as another user in order to see the
 * application exactly as that user sees it. The administrator's own user id is
 * remembered in `$_SESSION['impersonator']`; while that key is present every
 * page renders the impersonation banner and the exit control.
 *
 * Security notes:
 *  - Starting a masquerade requires an administrator *session*. The route layer
 *    enforces that with AdminRoleAuthMiddleware plus SessionOnlyMiddleware, so
 *    an `x-api-key` caller can never reach this service.
 *  - Both the start and the end are written to the auth log with both user ids.
 *  - The impersonated account is not touched: no `usr_LastLogin` stamp, no
 *    login/failed-login counters, no 2FA prompt, and no plugin login hooks.
 */
class ImpersonationService
{
    /** Session key holding the masquerade record. */
    public const SESSION_KEY = 'impersonator';

    /**
     * Session keys captured at login for the administrator that must not leak
     * into (or survive) a masquerade. They are stashed on the masquerade record
     * and restored on exit.
     */
    private const STASHED_SESSION_KEYS = [
        'systemUpdateAvailable',
        'systemUpdateVersion',
        'systemLatestVersion',
    ];

    /**
     * Is the current session a masquerade?
     */
    public static function isActive(): bool
    {
        return isset($_SESSION[self::SESSION_KEY]['userId']);
    }

    /**
     * The user id of the administrator behind the current masquerade, or null
     * when the session is a genuine login.
     */
    public static function getImpersonatorUserId(): ?int
    {
        if (!self::isActive()) {
            return null;
        }

        return (int) $_SESSION[self::SESSION_KEY]['userId'];
    }

    /**
     * Begin a masquerade as `$target`.
     *
     * The caller must already have established that the current session belongs
     * to an administrator, that `$target` is not that administrator, and that no
     * masquerade is active.
     *
     * @throws \RuntimeException when a masquerade is already active
     */
    public static function start(User $target): void
    {
        if (self::isActive()) {
            throw new \RuntimeException('A masquerade is already active for this session');
        }

        $admin = AuthenticationManager::getCurrentUser();

        $record = [
            'userId'    => $admin->getId(),
            'startedAt' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
            'stashed'   => self::stashSessionKeys(),
        ];

        // Establish the session as the target user exactly the way a successful
        // password (+2FA) login would, minus every login side effect. The
        // session id itself is kept — see
        // LocalAuthentication::establishSessionAsUser() for why rotating it here
        // strands in-flight requests from the page being left behind.
        $_SESSION[self::SESSION_KEY] = $record;
        AuthenticationManager::establishSessionAsUser($target);

        LoggerUtils::getAuthLogger()->info(sprintf(
            'Masquerade started: admin %d (%s) as user %d (%s)',
            $admin->getId(),
            $admin->getName(),
            $target->getId(),
            $target->getName()
        ));
    }

    /**
     * End the current masquerade and restore the administrator's session.
     *
     * @return int|null the user id that was being impersonated, or null when the
     *                  stored administrator can no longer be restored (the
     *                  session has then been ended entirely and the caller must
     *                  send the browser to the login page)
     *
     * @throws \RuntimeException when no masquerade is active
     */
    public static function end(): ?int
    {
        if (!self::isActive()) {
            throw new \RuntimeException('No masquerade is active for this session');
        }

        $logger = LoggerUtils::getAuthLogger();
        $record = $_SESSION[self::SESSION_KEY];
        $adminId = (int) $record['userId'];
        $targetId = AuthenticationManager::getCurrentUser()->getId();

        $admin = UserQuery::create()->findPk($adminId);
        if (!$admin instanceof User || !$admin->isAdmin()) {
            $logger->warning(sprintf(
                'Masquerade aborted: admin %d is no longer an administrator; ending session of user %d',
                $adminId,
                $targetId
            ));
            unset($_SESSION[self::SESSION_KEY]);
            AuthenticationManager::endSession(true);

            return null;
        }

        unset($_SESSION[self::SESSION_KEY]);
        AuthenticationManager::establishSessionAsUser($admin);
        self::restoreSessionKeys($record['stashed'] ?? []);

        $logger->info(sprintf(
            'Masquerade ended: admin %d back from user %d',
            $adminId,
            $targetId
        ));

        return $targetId;
    }

    /**
     * Remove and return the administrator-scoped session values that must not be
     * visible while masquerading (the "a new release is available" menu, which
     * is only populated for administrators at login).
     */
    private static function stashSessionKeys(): array
    {
        $stashed = [];
        foreach (self::STASHED_SESSION_KEYS as $key) {
            if (array_key_exists($key, $_SESSION)) {
                $stashed[$key] = $_SESSION[$key];
                unset($_SESSION[$key]);
            }
        }

        return $stashed;
    }

    private static function restoreSessionKeys(array $stashed): void
    {
        foreach (self::STASHED_SESSION_KEYS as $key) {
            if (array_key_exists($key, $stashed)) {
                $_SESSION[$key] = $stashed[$key];
            }
        }
    }
}
