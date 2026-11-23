<?php

namespace ChurchCRM\Service;

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\dto\Notification\UiNotification;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Remote\CentralServices;
use ChurchCRM\Utils\LoggerUtils;

/**
 * Generic notification registry.
 *
 * On login, AuthenticationManager sets session state:
 *  - $_SESSION['systemUpdateAvailable'] (system upgrade check)
 *  - $_SESSION['SystemNotifications']   (remote GitHub JSON fetch)
 *
 * On every page load, Header.php calls loadSessionNotifications() which
 * hydrates the in-memory registry from both session sources.
 * No external HTTP calls happen in the render path.
 */
class NotificationService
{
    /** @var UiNotification[] */
    private static array $notifications = [];

    /**
     * Push a notification into the registry.
     * Automatically skips if the current user has dismissed it.
     */
    public static function add(UiNotification $notification): void
    {
        $currentUser = AuthenticationManager::getCurrentUser();

        $dismissKey = $notification->getDismissSettingKey();
        if ($notification->getId() !== 'system-update-available'
            && $dismissKey !== '' && $currentUser->getSettingValue($dismissKey) === 'true') {
            return;
        }

        self::$notifications[] = $notification;
    }

    /**
     * Return all active (non-dismissed) notifications.
     *
     * @return UiNotification[]
     */
    public static function getNotifications(): array
    {
        return self::$notifications;
    }

    public static function hasActiveNotifications(): bool
    {
        return count(self::$notifications) > 0;
    }

    public static function dismissalSettingKey(string $id): string
    {
        if ($id === '') {
            return '';
        }

        $prefix = 'notification.dismissed.';
        $key = $prefix . $id;
        // Keep existing dismissals; long remote IDs must fit user_settings.setting_name.
        return strlen($key) <= 50 ? $key : $prefix . substr(hash('sha256', $id), 0, 50 - strlen($prefix));
    }

    /**
     * Hydrate the registry from all session-cached notification sources.
     * Called on every page load from Header.php — reads session only, no HTTP.
     */
    public static function loadSessionNotifications(): void
    {
        self::loadUpgradeNotification();
        self::loadDatabaseSupportNotification();
        self::loadRemoteNotifications();
    }

    // ─── Source 1: System upgrade (session set at login) ────────────────

    /**
     * Show the system upgrade notification to every logged-in user.
     * Only this notice is dismissed per session, not through user settings.
     */
    private static function loadUpgradeNotification(): void
    {
        if (($_SESSION['systemUpdateAvailable'] ?? false) !== true
            || ($_SESSION['systemUpdateNoticeDismissed'] ?? false) === true) {
            return;
        }

        $currentUser = AuthenticationManager::getCurrentUser();
        $version = isset($_SESSION['systemUpdateVersion'])
            ? (string) $_SESSION['systemUpdateVersion']
            : '';
        $isAdmin = $currentUser->isAdmin();
        $message = $isAdmin
            ? ($version !== ''
                ? sprintf(gettext('Version %s is available. Please upgrade your installation.'), $version)
                : gettext('A system update is available. Please upgrade your installation.'))
            : ($version !== ''
                ? sprintf(gettext('Version %s is available. Please ask your administrator to upgrade your installation.'), $version)
                : gettext('A system update is available. Please ask your administrator to upgrade your installation.'));

        $id = 'system-update-available';
        self::add(new UiNotification(
            $id,
            self::dismissalSettingKey($id),
            gettext('System Update Available'),
            $message . ' ' . gettext('Security updates and support are provided for the latest version only.'),
            SystemURLs::getRootPath() . ($isAdmin ? '/admin/system/upgrade' : '/v2/releases'),
            'warning',
            'refresh',
        ));
    }

    /**
     * Not dismissible: it stays until the database is moved to a supported version.
     */
    private static function loadDatabaseSupportNotification(): void
    {
        if (!AuthenticationManager::getCurrentUser()->isAdmin()) {
            return;
        }
        $message = DatabaseVersionService::getSecurityRiskMessage();
        if ($message === null) {
            return;
        }

        self::add(new UiNotification(
            'database-out-of-support',
            '',
            gettext('Database out of support'),
            $message,
            '',
            'danger',
            'database',
        ));
    }

    // ─── Source 2: Remote GitHub JSON (session set at login) ────────────

    /**
     * Fetch notifications from the remote GitHub-hosted JSON file.
     * Called ONCE on login by AuthenticationManager — never in the render path.
     * Results are stored in $_SESSION['SystemNotifications'].
     */
    public static function fetchRemoteNotifications(): void
    {
        try {
            $url = CentralServices::NOTIFICATIONS_URL;
            // Runs inside the sign-in request, so never let a slow remote host hold up the redirect.
            $context = stream_context_create(['http' => ['timeout' => 5]]);
            $contents = @file_get_contents($url, false, $context);
            if ($contents === false) {
                LoggerUtils::getAppLogger()->warning('Failed to fetch remote notifications', ['url' => $url]);

                return;
            }
            $data = json_decode($contents, null, 512);
            if (isset($data->TTL)) {
                $_SESSION['SystemNotifications'] = $data;
            }
        } catch (\Exception $e) {
            LoggerUtils::getAppLogger()->warning('Error processing remote notifications', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Walk cached remote messages and push version/role-matching ones into the registry.
     */
    private static function loadRemoteNotifications(): void
    {
        if (!isset($_SESSION['SystemNotifications']->messages)) {
            return;
        }

        $currentUser = AuthenticationManager::getCurrentUser();
        $installedVersion = $_SESSION['sSoftwareInstalledVersion'] ?? '';

        foreach ($_SESSION['SystemNotifications']->messages as $message) {
            $pattern = $message->targetVersionPattern ?? $message->targetVersion ?? '';
            if ($pattern !== '' && !self::matchesVersionPattern($pattern, $installedVersion)) {
                continue;
            }
            if (!empty($message->adminOnly) && !$currentUser->isAdmin()) {
                continue;
            }

            $id = $message->id ?? '';
            $dismissKey = self::dismissalSettingKey($id);

            self::add(new UiNotification(
                $id,
                $dismissKey,
                $message->title ?? '',
                $message->message ?? '',
                $message->link ?? '',
                $message->type ?? 'info',
                $message->icon ?? 'circle-info',
            ));
        }
    }

    /**
     * Check if a version pattern matches the installed version.
     * Supports wildcards: "7.1.*" matches 7.1.x, "7.*" matches 7.x.y, "*" matches all.
     */
    private static function matchesVersionPattern(string $pattern, string $version): bool
    {
        if ($pattern === '*') {
            return true;
        }
        $regex = '/^' . str_replace(['.', '*'], ['\.', '\d+'], $pattern) . '$/';

        return (bool) preg_match($regex, $version);
    }
}
