<?php

namespace ChurchCRM\Service;

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\Utils\MiscUtils;

class NotificationService
{
    public static function updateNotifications(): void
    {
        /** Get the latest notifications from the source.  Store in session variable */
        try {
            $notificationFileContents = file_get_contents(SystemConfig::getValue('sNotificationsURL'));
            MiscUtils::throwIfFailed($notificationFileContents);
            $tempNotifications = json_decode($notificationFileContents, null, 512, JSON_THROW_ON_ERROR);
            if (isset($tempNotifications->TTL)) {
                $_SESSION['SystemNotifications'] = $tempNotifications;
                $expires = (new \DateTimeImmutable())
                    ->add(new \DateInterval('PT' . $_SESSION['SystemNotifications']->TTL . 'S'));
                $_SESSION['SystemNotifications']->expires = $expires;
            }
        } catch (\Exception $ex) {
            //a failure here should never prevent the page from loading.
            //Possibly log an exception when a unified logger is implemented.
            //for now, do nothing.
        }
    }

    /** retrieve active notifications from the session variable for display */
    public static function getNotifications(): array
    {
        $notifications = [];
        if (isset($_SESSION['SystemNotifications'])) {
            foreach ($_SESSION['SystemNotifications']->messages as $message) {
                if ($message->targetVersion === $_SESSION['sSoftwareInstalledVersion']) {
                    if (!$message->adminOnly || AuthenticationManager::getCurrentUser()->isAdmin()) {
                        $notifications[] = $message;
                    }
                }
            }
        }

        return $notifications;
    }

    public static function hasActiveNotifications(): bool
    {
        return count(NotificationService::getNotifications()) > 0;
    }

    /**
     * If session does not contain notifications, or if the notification TTL has expired, return true
     * otherwise return false.
     */
    public static function isUpdateRequired(): bool
    {
        return !isset($_SESSION['SystemNotifications']) || $_SESSION['SystemNotifications']->expires < new \DateTimeImmutable();
    }
}
