<?php

namespace ChurchCRM\Service;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\Authentication\AuthenticationManager;

class NotificationService
{
  public static function updateNotifications()
  {
    /* Get the latest notifications from the source.  Store in session variable
     *
     */
    try {
      $TempNotificaions = json_decode(file_get_contents(SystemConfig::getValue("sNotificationsURL")));
      if (isset($TempNotificaions->TTL) ) {
        $_SESSION['SystemNotifications'] = $TempNotificaions;
        $_SESSION['SystemNotifications']->expires = new \DateTime();
        $_SESSION['SystemNotifications']->expires->add(new \DateInterval("PT".$_SESSION['SystemNotifications']->TTL."S"));
      }
    } catch (\Exception $ex) {
      //a failure here should never prevent the page from loading.
      //Possibly log an exception when a unified logger is implemented.
      //for now, do nothing.
    }
  }

  public static function getNotifications()
  {
    /* retreive active notifications from the session variable for display
     *
     */
    if (isset($_SESSION['SystemNotifications']))
    {
      $notifications = array();
      foreach ($_SESSION['SystemNotifications']->messages as $message)
      {
        if($message->targetVersion == $_SESSION['sSoftwareInstalledVersion'])
        {
          if (! $message->adminOnly ||  AuthenticationManager::GetCurrentUser()->isAdmin())
          {
            array_push($notifications, $message);
          }
        }
      }
      return $notifications;
    }
  }

  public static function hasActiveNotifications()
  {
    return count(NotificationService::getNotifications()) > 0;
  }

  public static function isUpdateRequired()
  {
    /*
     * If session does not contain notifications, or if the notification TTL has expired, return true
     * otherwise return false.
     */
    if (!isset($_SESSION['SystemNotifications']) || $_SESSION['SystemNotifications']->expires < new \DateTime())
    {
      return true;
    }
    return false;
  }

}
