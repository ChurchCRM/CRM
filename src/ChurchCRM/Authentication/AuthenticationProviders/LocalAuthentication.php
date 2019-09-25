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

      // First check to see if a `user` key exists on the session.
      if (!array_key_exists('user',$_SESSION) || null == $_SESSION['user']) {
        $authenticationResult->isAuthenticated = false;
        $authenticationResult->nextStepURL = 'Login.php?location=' . urlencode(substr($_SERVER['REQUEST_URI'], 1));
        return $authenticationResult;
      }

      // Next, make sure the user in the sesion still exists in the database.
      try {
        $_SESSION['user']->reload();
      } catch (\Exception $exc) {
        $authenticationResult->isAuthenticated = false;
        $authenticationResult->nextStepURL = 'Login.php?location=' . urlencode(substr($_SERVER['REQUEST_URI'], 1));
        return $authenticationResult;
      }


      // Next, check for login timeout.  If login has expired, redirect to login page
      if (SystemConfig::getValue('iSessionTimeout') > 0) {
        if ((time() - $_SESSION['tLastOperation']) > SystemConfig::getValue('iSessionTimeout')) {
          $authenticationResult->isAuthenticated = false;
          $authenticationResult->nextStepURL = 'Login.php?location=' . urlencode(substr($_SERVER['REQUEST_URI'], 1));
          return $authenticationResult;
        } else {
          $_SESSION['tLastOperation'] = time();
        }
      }

      // Next, if this user needs to change password, send to that page
      if ($_SESSION['user']->getNeedPasswordChange() && !isset($bNoPasswordRedirect)) {
        $authenticationResult->isAuthenticated = false;
        $authenticationResult->nextStepURL = 'UserPasswordChange.php?PersonID=' . $_SESSION['user']->getId();
      }

      
      // Finally, if the above tests pass, this user "is authenticated"
      $authenticationResult->isAuthenticated = true;
      return $authenticationResult;
    }
  }
}
