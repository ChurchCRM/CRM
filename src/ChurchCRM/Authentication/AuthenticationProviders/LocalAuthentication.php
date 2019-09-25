<?php

namespace ChurchCRM\Authentication\AuthenticationProviders {
  use ChurchCRM\dto\SystemConfig;
  use ChurchCRM\Utils\RedirectUtils;
  use ChurchCRM\Authentication\AuthenticationProviders;
    use ChurchCRM\Authentication\AuthenticationResult;
    use ChurchCRM\SessionUser;

class LocalAuthentication implements IAuthenticationProvider
  {

    public function Authenticate() : AuthenticationResult
    {
      $authenticationResult = new AuthenticationResult();
      // Basic security: If the UserID isn't set (no session), redirect to the login page

      if (!array_key_exists('user',$_SESSION) || null == $_SESSION['user']) {
        $authenticationResult->isAuthenticated = false;
        $authenticationResult->nextStepURL = 'Login.php?location=' . urlencode(substr($_SERVER['REQUEST_URI'], 1));
        return $authenticationResult;
      }

      try {
        $_SESSION['user']->reload();
      } catch (\Exception $exc) {
        $authenticationResult->isAuthenticated = false;
        $authenticationResult->nextStepURL = 'Login.php?location=' . urlencode(substr($_SERVER['REQUEST_URI'], 1));
        return $authenticationResult;
      }


      // Check for login timeout.  If login has expired, redirect to login page
      if (SystemConfig::getValue('iSessionTimeout') > 0) {
        if ((time() - $_SESSION['tLastOperation']) > SystemConfig::getValue('iSessionTimeout')) {
          $authenticationResult->isAuthenticated = false;
          $authenticationResult->nextStepURL = 'Login.php?location=' . urlencode(substr($_SERVER['REQUEST_URI'], 1));
          return $authenticationResult;
        } else {
          $_SESSION['tLastOperation'] = time();
        }
      }

      // If this user needs to change password, send to that page
      if ($_SESSION['user']->getNeedPasswordChange() && !isset($bNoPasswordRedirect)) {
        $authenticationResult->isAuthenticated = false;
        $authenticationResult->nextStepURL = 'UserPasswordChange.php?PersonID=' . $_SESSION['user']->getId();
      }

      $authenticationResult->isAuthenticated = true;
      return $authenticationResult;
      // Check if https is required

      // Note: PHP has limited ability to access the address bar
      // url.  PHP depends on Apache or other web server
      // to provide this information.  The web server
      // may or may not be configured to pass the address bar url
      // to PHP.  As a workaround this security check is now performed
      // by the browser using javascript.  The browser always has
      // access to the address bar url.  Search for basic security checks
      // in Include/Header-functions.php
    }
  }
}
