<?php

namespace ChurchCRM\Authentication\AuthenticationProviders {
  use ChurchCRM\dto\SystemConfig;
  use ChurchCRM\Utils\RedirectUtils;
  use ChurchCRM\Authentication\AuthenticationProviders;
  use ChurchCRM\Authentication\AuthenticationResult;
  use ChurchCRM\dto\SystemURLs;
  use ChurchCRM\SessionUser;
  use ChurchCRM\Utils\InputUtils;
  use ChurchCRM\UserQuery;
  use DateTime;
  use DateTimeZone;


class LocalAuthentication implements IAuthenticationProvider
{

    public function EndSession() {
      
      if (!empty($_SESSION['user'])) {
          if (!isset($_SESSION['sshowPledges']) || ($_SESSION['sshowPledges'] == '')) {
              $_SESSION['sshowPledges'] = 0;
          }
          if (!isset($_SESSION['sshowPayments']) || ($_SESSION['sshowPayments'] == '')) {
              $_SESSION['sshowPayments'] = 0;
          }
      
          $currentUser = UserQuery::create()->findPk($_SESSION['user']->getId());
          if (!empty($currentUser)) {
              $currentUser->setShowPledges($_SESSION['sshowPledges']);
              $currentUser->setShowPayments($_SESSION['sshowPayments']);
              //$currentUser->setDefaultFY($_SESSION['idefaultFY']);
              $currentUser->setCurrentDeposit($_SESSION['iCurrentDeposit']);
      
              $currentUser->save();
          }
      }
      
      $_COOKIE = [];
      $_SESSION = [];
      session_destroy();
    }

    public function Authenticate(object $AuthenticationRequest) {
      $authenticationResult = new AuthenticationResult();

      if (isset($AuthenticationRequest->User)) {
        // Get the information for the selected user
        $currentUser = UserQuery::create()->findOneByUserName($AuthenticationRequest->User);
        if ($currentUser == null) {
            // Set the error text
            $authenticationResult->isAuthenticated = false;
            $authenticationResult->message = gettext('Invalid login or password');
            $authenticationResult->nextStepURL = SystemURLs::getRootPath()."/session/begin?location=" . urlencode(substr($_SERVER['REQUEST_URI'], 1));
            return $authenticationResult;
        } // Block the login if a maximum login failure count has been reached
        elseif ($currentUser->isLocked()) {
          $authenticationResult->isAuthenticated = false;
          $authenticationResult->message = gettext('Too many failed logins: your account has been locked.  Please contact an administrator.');
          $authenticationResult->nextStepURL = SystemURLs::getRootPath()."/session/begin?location=" . urlencode(substr($_SERVER['REQUEST_URI'], 1));
          return $authenticationResult;
        } // Does the password match?
        elseif (!$currentUser->isPasswordValid($AuthenticationRequest->Password)) {
            // Increment the FailedLogins
            $currentUser->setFailedLogins($currentUser->getFailedLogins() + 1);
            $currentUser->save();
            if (!empty($currentUser->getEmail()) && $currentUser->isLocked()) {
                $lockedEmail = new LockedEmail($currentUser);
                $lockedEmail->send();
            }
            $authenticationResult->isAuthenticated = false;
            $authenticationResult->message = gettext('Invalid login or password');
            $authenticationResult->nextStepURL = SystemURLs::getRootPath()."/session/begin?location=" . urlencode(substr($_SERVER['REQUEST_URI'], 1));
            return $authenticationResult;
        } else {
            // Set the LastLogin and Increment the LoginCount
            $date = new DateTime('now', new DateTimeZone(SystemConfig::getValue('sTimeZone')));
            $currentUser->setLastLogin($date->format('Y-m-d H:i:s'));
            $currentUser->setLoginCount($currentUser->getLoginCount() + 1);
            $currentUser->setFailedLogins(0);
            $currentUser->save();
    
            $_SESSION['user'] = $currentUser;
    
            $_SESSION['bManageGroups'] = $currentUser->isManageGroupsEnabled();
            $_SESSION['bFinance'] = $currentUser->isFinanceEnabled();
    
            // Create the Cart
            $_SESSION['aPeopleCart'] = [];
    
            // Create the variable for the Global Message
            $_SESSION['sGlobalMessage'] = '';
    
            // Initialize the last operation time
            $_SESSION['tLastOperation'] = time();
    
            $_SESSION['bHasMagicQuotes'] = 0;
    
            // Pledge and payment preferences
            $_SESSION['sshowPledges'] = $currentUser->getShowPledges();
            $_SESSION['sshowPayments'] = $currentUser->getShowPayments();
            //$_SESSION['idefaultFY'] = CurrentFY(); // Improve the chance of getting the correct fiscal year assigned to new transactions
            $_SESSION['iCurrentDeposit'] = $currentUser->getCurrentDeposit();


            $authenticationResult->isAuthenticated = true;
            $redirectLocation = $_SESSION['location'];
            $authenticationResult->nextStepURL = isset($redirectLocation) ? $redirectLocation : 'Menu.php';
            return $authenticationResult;
          }
        }
    }

    public function isAuthenticated() : AuthenticationResult
    {

      $authenticationResult = new AuthenticationResult();

      // Basic security: If the UserID isn't set (no session), redirect to the login page

      // First check to see if a `user` key exists on the session.
      if (!array_key_exists('user',$_SESSION) || null == $_SESSION['user']) {
        $authenticationResult->isAuthenticated = false;
        $authenticationResult->nextStepURL = SystemURLs::getRootPath()."/session/begin?location=" . urlencode(substr($_SERVER['REQUEST_URI'], 1));
        return $authenticationResult;
      }

      // Next, make sure the user in the sesion still exists in the database.
      try {
        $_SESSION['user']->reload();
      } catch (\Exception $exc) {
        $authenticationResult->isAuthenticated = false;
        $authenticationResult->nextStepURL = SystemURLs::getRootPath()."/session/begin?location=" . urlencode(substr($_SERVER['REQUEST_URI'], 1));
        return $authenticationResult;
      }


      // Next, check for login timeout.  If login has expired, redirect to login page
      if (SystemConfig::getValue('iSessionTimeout') > 0) {
        if ((time() - $_SESSION['tLastOperation']) > SystemConfig::getValue('iSessionTimeout')) {
          $authenticationResult->isAuthenticated = false;
          $authenticationResult->nextStepURL = SystemURLs::getRootPath()."/session/begin?location=" . urlencode(substr($_SERVER['REQUEST_URI'], 1));
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
