<?php

Namespace ChurchCRM\Utils;
use ChurchCRM\Utils\MiscUtils;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\dto\SystemConfig;

Class PageSecurityManager {

  private static function isAPIRequest() {
    return $_SERVER['SCRIPT_FILENAME'] == SystemURLs::getDocumentRoot() . "/api/index.php";
  }

  private static function isUserSessionExpired() {
    if (SystemConfig::getValue('iSessionTimeout') > 0) {
      if ((time() - $_SESSION['tLastOperation']) > SystemConfig::getValue('iSessionTimeout')) {
        return true;
      } else {
        self::updateLastUserActionTime();
        return false;
      }
    }
  }

  private static function updateLastUserActionTime() {
    $_SESSION['tLastOperation'] = time();
  }

  private static function isUserSessionValid() {
    return isset($_SESSION['iUserID']) && ! self::isUserSessionExpired();
  }
  
  private static function doesUserNeedPasswordChange(){
    return $_SESSION['bNeedPasswordChange'] && !isset($bNoPasswordRedirect);
  }

  public static function ValidateSecurity($bSuppressSessionTests=false) {
    if ($bSuppressSessionTests) {  // This is used for the login page only.
      return true;
    }
    else if ((!self::isUserSessionValid()) && self::isAPIRequest()) {  //api requests should not get redirected, they should simply fail.
      http_response_code(403);
      exit();
    }
    else if (!self::isUserSessionValid()) {  // if the user session is not present, or expired, the user should see the login page.
      MiscUtils::Redirect('Login.php');
      exit;
    }
    else if(self::doesUserNeedPasswordChange()){  // if the user needs to change their password, redirect to password change page.
      MiscUtils::Redirect('UserPasswordChange.php?PersonID=' . $_SESSION['iUserID']);
      exit;
    }
  }
}
