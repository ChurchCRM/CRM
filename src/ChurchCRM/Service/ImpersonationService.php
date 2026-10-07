<?php

namespace ChurchCRM\Service;

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\model\ChurchCRM\User;
use ChurchCRM\model\ChurchCRM\UserMasqueradeAction;
use ChurchCRM\model\ChurchCRM\UserMasqueradeSession;
use ChurchCRM\model\ChurchCRM\UserMasqueradeSessionQuery;
use ChurchCRM\model\ChurchCRM\UserQuery;
use ChurchCRM\Utils\DateTimeUtils;
use ChurchCRM\Utils\LoggerUtils;
use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\Collection\ObjectCollection;

/**
 * Admin masquerade ("Login as User", issue #9843).
 *
 * An administrator can open a session as another user in order to see the
 * application exactly as that user sees it. The administrator's own user id is
 * remembered in `$_SESSION['impersonator']`; while that key is present every
 * page renders the impersonation banner and the exit control.
 *
 * Security notes:
 *  - Off unless `bAllowLoginAsUser` is on (Admin → System Users settings).
 *  - Starting a masquerade requires an administrator *session*. The route layer
 *    enforces that with AdminRoleAuthMiddleware plus SessionOnlyMiddleware, so
 *    an `x-api-key` caller can never reach this service.
 *  - A user with two-factor authentication can only be entered from a session
 *    that was itself signed in with two-factor authentication.
 *  - Every session is a `user_masquerade_session_ums` row and every write request
 *    made during it a `user_masquerade_action_uma` row, so each change can be
 *    tied to the administrator; "edited by" columns keep naming the target.
 *  - Start and end are written to the auth log with both user ids.
 *  - The impersonated account is not touched: no `usr_LastLogin` stamp, no
 *    login/failed-login counters, no 2FA prompt, and no plugin login hooks.
 */
class ImpersonationService
{
    /** Session key holding the masquerade record. */
    public const SESSION_KEY = 'impersonator';

    public const END_EXIT = 'exit';
    public const END_SIGNOUT = 'signout';
    public const END_TIMEOUT = 'timeout';

    /** Write requests that are not recorded as actions: leaving the masquerade, and the page footer's scheduler ping. */
    private const UNRECORDED_PATHS = [
        '/v2/user/impersonate/exit',
        '/api/background/timerjobs',
    ];

    private const READ_METHODS = ['GET', 'HEAD', 'OPTIONS'];

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

    public static function isEnabled(): bool
    {
        return SystemConfig::getBooleanValue('bAllowLoginAsUser');
    }

    /**
     * Is the current session a masquerade?
     */
    public static function isActive(): bool
    {
        $record = $_SESSION[self::SESSION_KEY] ?? null;
        if ($record === null) {
            return false;
        }

        // A record only counts while the session still belongs to the user it
        // was started for. Anything else (an incomplete record, no current user,
        // or a different user) is stale and is discarded, so it can never be
        // used to restore the stored administrator.
        try {
            $currentUserId = AuthenticationManager::getCurrentUser()->getId();
        } catch (\Throwable $e) {
            $currentUserId = null;
        }
        if (
            !isset($record['userId'], $record['targetUserId'], $record['sessionId'])
            || (int) $record['targetUserId'] !== $currentUserId
        ) {
            self::clear(self::END_SIGNOUT);

            return false;
        }

        return true;
    }

    /**
     * Discard any masquerade record without restoring the administrator, and
     * close its history row with `$reason`. Called when a real login begins, on
     * sign-out and when the session times out, so a record can never outlive the
     * session it was started in.
     */
    public static function clear(string $reason): void
    {
        $record = $_SESSION[self::SESSION_KEY] ?? null;
        if ($record === null) {
            return;
        }
        unset($_SESSION[self::SESSION_KEY]);

        if (isset($record['sessionId']) && self::closeSessionRow((int) $record['sessionId'], $reason)) {
            LoggerUtils::getAuthLogger()->info(sprintf(
                'Masquerade ended (%s): admin %d as user %d',
                $reason,
                (int) ($record['userId'] ?? 0),
                (int) ($record['targetUserId'] ?? 0)
            ));
        }
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
     * Why the current administrator may not start a masquerade as `$target`, or
     * null when they may. Covers the two-factor rule only; the route checks the
     * setting, the role, self and other administrators.
     */
    public static function getStartRefusal(User $target): ?string
    {
        if (!$target->is2FactorAuthEnabled()) {
            return null;
        }

        if (!AuthenticationManager::getCurrentUser()->is2FactorAuthEnabled()) {
            return gettext('This user signs in with two-factor authentication. Turn on two-factor authentication for your own account and sign in with it to log in as them.');
        }

        if (!AuthenticationManager::isSessionTwoFactorVerified()) {
            return gettext('This user signs in with two-factor authentication. Sign out, then sign in again with your own two-factor code to log in as them.');
        }

        return null;
    }

    /**
     * Begin a masquerade as `$target`.
     *
     * The caller must already have established that the current session belongs
     * to an administrator, that `$target` is not that administrator, that
     * getStartRefusal() is null, and that no masquerade is active.
     *
     * @throws \RuntimeException when a masquerade is already active
     */
    public static function start(User $target): void
    {
        if (self::isActive()) {
            throw new \RuntimeException('A masquerade is already active for this session');
        }

        $admin = AuthenticationManager::getCurrentUser();

        $sessionRow = new UserMasqueradeSession();
        $sessionRow->setAdminUserId($admin->getId());
        $sessionRow->setTargetUserId($target->getId());
        $sessionRow->setStarted(self::now());
        $sessionRow->save();

        $record = [
            'userId'                 => $admin->getId(),
            'targetUserId'           => $target->getId(),
            'sessionId'              => $sessionRow->getId(),
            'adminTwoFactorVerified' => AuthenticationManager::isSessionTwoFactorVerified(),
            'stashed'                => self::stashSessionKeys(),
        ];

        // Establish the session as the target user exactly the way a successful
        // password (+2FA) login would, minus every login side effect. The
        // session id itself is kept — see
        // LocalAuthentication::establishSessionAsUser() for why rotating it here
        // strands in-flight requests from the page being left behind.
        $_SESSION[self::SESSION_KEY] = $record;
        AuthenticationManager::establishSessionAsUser($target);

        LoggerUtils::getAuthLogger()->info(sprintf(
            'Masquerade started: admin %d (%s) as user %d (%s), session %d',
            $admin->getId(),
            $admin->getName(),
            $target->getId(),
            $target->getName(),
            $sessionRow->getId()
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

        unset($_SESSION[self::SESSION_KEY]);
        self::closeSessionRow((int) $record['sessionId'], self::END_EXIT);

        $admin = UserQuery::create()->findPk($adminId);
        if (!$admin instanceof User || !$admin->isAdmin()) {
            $logger->warning(sprintf(
                'Masquerade aborted: admin %d is no longer an administrator; ending session of user %d',
                $adminId,
                $targetId
            ));
            AuthenticationManager::endSession(true);

            return null;
        }

        AuthenticationManager::establishSessionAsUser($admin, (bool) ($record['adminTwoFactorVerified'] ?? false));
        self::restoreSessionKeys($record['stashed'] ?? []);

        $logger->info(sprintf(
            'Masquerade ended: admin %d back from user %d',
            $adminId,
            $targetId
        ));

        return $targetId;
    }

    /**
     * The history row id to record a write request against, or null when the
     * request is not recorded: no masquerade, a read method, or an unrecorded path.
     */
    public static function getRecordingSessionId(string $method, string $path): ?int
    {
        if (in_array(strtoupper($method), self::READ_METHODS, true) || !self::isActive()) {
            return null;
        }

        $rootPath = SystemURLs::getRootPath();
        foreach (self::UNRECORDED_PATHS as $unrecorded) {
            if ($path === $rootPath . $unrecorded) {
                return null;
            }
        }

        return (int) $_SESSION[self::SESSION_KEY]['sessionId'];
    }

    public static function recordAction(int $sessionId, string $method, string $path, ?int $status): void
    {
        try {
            $action = new UserMasqueradeAction();
            $action->setSessionId($sessionId);
            $action->setMethod(substr(strtoupper($method), 0, 10));
            $action->setPath(mb_substr($path, 0, 255));
            $action->setStatus($status !== null && $status > 0 ? $status : null);
            $action->setTime(self::now());
            $action->save();
        } catch (\Throwable $e) {
            LoggerUtils::getAuthLogger()->error('Could not record a Login as User action', [
                'session' => $sessionId,
                'method'  => $method,
                'path'    => $path,
                'error'   => $e->getMessage(),
            ]);
        }
    }

    /**
     * Record a legacy `*.php` write request once the page has finished, with the
     * status it sent. Legacy pages end with exit(), so a shutdown function is the
     * only place the status is known.
     */
    public static function recordLegacyRequest(): void
    {
        $method = (string) ($_SERVER['REQUEST_METHOD'] ?? 'GET');
        $path = (string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH);
        $sessionId = self::getRecordingSessionId($method, $path);
        if ($sessionId === null) {
            return;
        }

        register_shutdown_function(static function () use ($sessionId, $method, $path): void {
            $status = http_response_code();
            self::recordAction($sessionId, $method, $path, is_int($status) ? $status : null);
        });
    }

    /**
     * Sessions in which `$userId` was the administrator or the user signed in as,
     * newest first, with their actions.
     *
     * @return ObjectCollection<UserMasqueradeSession>
     */
    public static function getHistoryForUser(int $userId, int $limit = 25): ObjectCollection
    {
        return UserMasqueradeSessionQuery::create()
            ->filterByAdminUserId($userId)
            ->_or()
            ->filterByTargetUserId($userId)
            ->orderByStarted(Criteria::DESC)
            ->orderById(Criteria::DESC)
            ->limit($limit)
            ->find();
    }

    private static function closeSessionRow(int $sessionId, string $reason): bool
    {
        try {
            $sessionRow = UserMasqueradeSessionQuery::create()->findPk($sessionId);
            if (!$sessionRow instanceof UserMasqueradeSession || $sessionRow->getEnded() !== null) {
                return false;
            }
            $sessionRow->setEnded(self::now());
            $sessionRow->setEndReason($reason);
            $sessionRow->save();

            return true;
        } catch (\Throwable $e) {
            LoggerUtils::getAuthLogger()->error('Could not close a Login as User session', [
                'session' => $sessionId,
                'error'   => $e->getMessage(),
            ]);

            return false;
        }
    }

    private static function now(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('now', DateTimeUtils::getConfiguredTimezone());
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
