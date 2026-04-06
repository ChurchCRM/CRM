<?php

namespace ChurchCRM\Service;

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\dto\Notification\UiNotification;

class NotificationService
{
    /**
     * Generate in-app system notifications based on current system state.
     *
     * Notifications are derived entirely from in-app data (session, system config, etc.)
     * rather than polling an external URL.
     *
     * @return UiNotification[]
     */
    public static function getNotifications(): array
    {
        $notifications = [];
        $currentUser = AuthenticationManager::getCurrentUser();

        // System update available (admin-only)
        if (
            $currentUser->isAdmin()
            && isset($_SESSION['systemUpdateAvailable'])
            && $_SESSION['systemUpdateAvailable'] === true
        ) {
            $id = 'system-update-available';
            $dismissKey = 'notification.dismissed.' . $id;

            if ($currentUser->getSettingValue($dismissKey) !== 'true') {
                $availableVersion = (isset($_SESSION['systemUpdateVersion']) && $_SESSION['systemUpdateVersion'] !== null)
                    ? (string) $_SESSION['systemUpdateVersion']
                    : '';

                $notifications[] = new UiNotification(
                    $id,
                    $dismissKey,
                    gettext('System Update Available'),
                    $availableVersion !== ''
                        ? sprintf(gettext('Version %s is available. Please upgrade your installation.'), $availableVersion)
                        : gettext('A system update is available. Please upgrade your installation.'),
                    '/admin/system/upgrade',
                    'warning',
                    'refresh',
                );
            }
        }

        return $notifications;
    }

    public static function hasActiveNotifications(): bool
    {
        return count(self::getNotifications()) > 0;
    }
}
