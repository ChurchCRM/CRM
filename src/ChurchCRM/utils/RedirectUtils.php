<?php

namespace ChurchCRM\Utils;

use ChurchCRM\dto\SystemURLs;

class RedirectUtils
{

    /**
     * Convert a relative URL into an absolute URL and redirect the browser there.
     * @param string $sRelativeURL
     * @throws \Exception
     */
    public static function Redirect($sRelativeURL)
    {
        LoggerUtils::getAppLogger()->info("Redirect Request: " . $sRelativeURL);
        if (substr($sRelativeURL, 0,1) != "/") {
            $sRelativeURL = "/" . $sRelativeURL;
        }
        if (substr($sRelativeURL, 0, strlen(SystemURLs::getRootPath())) != SystemURLs::getRootPath()) {
            $finalLocation = SystemURLs::getRootPath() . $sRelativeURL;
        } else {
            $finalLocation = $sRelativeURL;
        }
        LoggerUtils::getAppLogger()->info("Redirect Final: " . $finalLocation);
        header('Location: ' . $finalLocation);
        exit;
    }
    
    public static function SecurityRedirect($role) {
        LoggerUtils::getAppLogger()->info("Security Redirect Request due to Role: " . $role);
        self::Redirect("Menu.php");
    }
        
}
