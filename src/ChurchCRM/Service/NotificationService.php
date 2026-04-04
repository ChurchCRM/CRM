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
     * rather than polling an external URL. Returns an array of stdClass objects
     * with the following fields (all optional except title):
     *   - id: string — unique identifier used for per-user dismiss state
     *   - title: string
     *   - message: string
     *   - icon: string — Tabler icon name (e.g. "refresh", "alert-circle")
     *   - type: string — Bootstrap alert type: "info"|"warning"|"danger"|"success"
     *   - link: string — optional URL for a "Learn more" anchor
     *   - adminOnly: bool
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
            $availableVersion = (isset($_SESSION['systemUpdateVersion']) && $_SESSION['systemUpdateVersion'] !== null)
                ? (string) $_SESSION['systemUpdateVersion']
                : '';

            $notification = new \stdClass();
            $notification->id        = 'system-update-available';
            $notification->title     = gettext('System Update Available');
            $notification->message   = $availableVersion !== ''
                ? sprintf(gettext('Version %s is available. Please upgrade your installation.'), $availableVersion)
                : gettext('A system update is available. Please upgrade your installation.');
            $notification->icon      = 'refresh';
            $notification->type      = 'warning';
            $notification->link      = '/admin/system/upgrade';
            $notification->adminOnly = true;

            // Skip if admin has dismissed this notification
            $dismissKey = 'notification.dismissed.' . $notification->id;
            if ($currentUser->getSettingValue($dismissKey) !== 'true') {
                $notifications[] = $notification;
            }
        }

        return $notifications;
    }

    public static function hasActiveNotifications(): bool
    {
        return count(self::getNotifications()) > 0;
    }

    /**
     * Transform raw notification stdClass objects into UiNotification DTOs ready for JSON output.
     *
     * @param \stdClass[] $notifications
     * @return UiNotification[]
     */
    public static function toUiNotifications(array $notifications): array
    {
        $result = [];
        foreach ($notifications as $notification) {
            $id               = $notification->id ?? '';
            $dismissSettingKey = $id !== '' ? 'notification.dismissed.' . $id : '';
            $result[] = new UiNotification(
                $notification->title ?? '',
                $notification->icon ?? 'info-circle',
                $notification->link ?? '',
                $notification->message ?? '',
                $notification->type ?? 'info',
                $notification->timeout ?? 4000,
                $notification->placement ?? 'bottom',
                $notification->align ?? 'right',
                $id,
                $dismissSettingKey,
            );
        }
        return $result;
    }
}
