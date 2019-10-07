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
  use ChurchCRM\User;
  use DateTime;
  use DateTimeZone;
  use ChurchCRM\Utils\LoggerUtils;


class LocalAuthentication implements IAuthenticationProvider
{
    private $bNoPasswordRedirect;
    public function __construct() {
      $this->bNoPasswordRedirect = false;
    }

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
      LoggerUtils::getAuthLogger()->addInfo("Ended Local session for user " . $currentUser->getName());
    }

    private function prepareSuccessfulLoginOperations(User $currentUser) {
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
    }

    public function Authenticate(object $AuthenticationRequest) {
      $authenticationResult = new AuthenticationResult();

      if (isset($AuthenticationRequest->User)) {
        LoggerUtils::getAuthLogger()->addDebug("Processing local login for" . $AuthenticationRequest->User);
        // Get the information for the selected user
        $currentUser = UserQuery::create()->findOneByUserName($AuthenticationRequest->User);
        if ($currentUser == null) {
          // Set the error text
          $authenticationResult->isAuthenticated = false;
          $authenticationResult->message = gettext('Invalid login or password');
          return $authenticationResult;
        } // Block the login if a maximum login failure count has been reached
        elseif ($currentUser->isLocked()) {
          $authenticationResult->isAuthenticated = false;
          $authenticationResult->message = gettext('Too many failed logins: your account has been locked.  Please contact an administrator.');
          LoggerUtils::getAuthLogger()->addWarning("Authentication attempt for locked account: " . $currentUser->getUserName());
          return $authenticationResult;
        } // Does the password match?
        elseif (!$currentUser->isPasswordValid($AuthenticationRequest->Password)) {
          // Increment the FailedLogins
          $currentUser->setFailedLogins($currentUser->getFailedLogins() + 1);
          $currentUser->save();
          if (!empty($currentUser->getEmail()) && $currentUser->isLocked()) {
              LoggerUtils::getAuthLogger()->addWarning("Too many failed logins for: " . $currentUser->getUserName() . ". The account has been locked");
              $lockedEmail = new LockedEmail($currentUser);
              $lockedEmail->send();
          }
          $authenticationResult->isAuthenticated = false;
          $authenticationResult->message = gettext('Invalid login or password');
          LoggerUtils::getAuthLogger()->addWarning("Invalid login attempt for: " . $currentUser->getUserName());
          return $authenticationResult;
        } elseif(SystemConfig::getBooleanValue("bEnable2FA") && $currentUser->is2FactorAuthEnabled()) {
          // Only redirect the user to the 2FA sign-ing page if it's 
          // enabled both at system AND user level. 
          $authenticationResult->isAuthenticated = false;
          $authenticationResult->nextStepURL = SystemURLs::getRootPath()."/session/two-factor";
          $_SESSION['TwoFAUser'] = $currentUser;
          return $authenticationResult;
        } elseif(SystemConfig::getBooleanValue("bRequire2FA") && ! $currentUser->is2FactorAuthEnabled()) {
          $authenticationResult->isAuthenticated = false;
          $authenticationResult->message = gettext('Invalid login or password');
          LoggerUtils::getAuthLogger()->addWarning("User attempted login with valid credentials, but missing mandatory 2FA enrollment.  Denying access for user: " . $currentUser->getUserName());
          return $authenticationResult;
        } else {
            $this->prepareSuccessfulLoginOperations($currentUser);
            $authenticationResult->isAuthenticated = true;
            LoggerUtils::getAuthLogger()->addInfo("User succefully logged in without 2FA: " . $currentUser->getUserName());
            return $authenticationResult;
          }
        }
        elseif(isset($AuthenticationRequest->TwoFACode) && array_key_exists('TwoFAUser', $_SESSION)) {
          $currentUser = $_SESSION['TwoFAUser'];
          if ($currentUser->isTwoFACodeValid($AuthenticationRequest->TwoFACode)) {
            $this->prepareSuccessfulLoginOperations($currentUser);
            $authenticationResult->isAuthenticated = true;
            LoggerUtils::getAuthLogger()->addInfo("User succefully logged in with 2FA: " . $currentUser->getUserName());
            return $authenticationResult;
          }
          else {
            $authenticationResult->isAuthenticated = false;
            $authenticationResult->nextStepURL = SystemURLs::getRootPath()."/session/two-factor";
            $_SESSION['TwoFAUser'] = $currentUser;
            return $authenticationResult;
          }
        }
    }

    public function DisablePasswordChangeRedirect() {
      $this->bNoPasswordRedirect = true;
    }

    public function GetAuthenticationStatus() : AuthenticationResult
    {

      $authenticationResult = new AuthenticationResult();

      // First check to see if a `user` key exists on the session.
      if (!array_key_exists('user',$_SESSION) || null == $_SESSION['user']) {
        $authenticationResult->isAuthenticated = false;
        LoggerUtils::getAuthLogger()->addDebug("No active user session.");
        return $authenticationResult;
      }

      $currentUser = $_SESSION['user'];
      
      LoggerUtils::getAuthLogger()->addDebug("Processing session for user: " . $currentUser->getName());

      // Next, make sure the user in the sesion still exists in the database.
      try {
        $currentUser->reload();
      } catch (\Exception $exc) {
        LoggerUtils::getAuthLogger()->addDebug("User with active session no longer exists in the database.  Expiring session");
        $this->EndSession();
        $authenticationResult->isAuthenticated = false;
        return $authenticationResult;
      }


      // Next, check for login timeout.  If login has expired, redirect to login page
      if (SystemConfig::getValue('iSessionTimeout') > 0) {
        if ((time() - $_SESSION['tLastOperation']) > SystemConfig::getValue('iSessionTimeout')) {
          LoggerUtils::getAuthLogger()->addDebug("User session timed out");
          $authenticationResult->isAuthenticated = false;
          return $authenticationResult;
        } else {
          $_SESSION['tLastOperation'] = time();
        }
      }
      // Next, if this user needs to change password, send to that page
      if ($currentUser->getNeedPasswordChange() && !$this->bNoPasswordRedirect ) {
        LoggerUtils::getAuthLogger()->addDebug("User needs password change; redirecting to password change");
        $authenticationResult->isAuthenticated = false;
        $authenticationResult->nextStepURL = 'UserPasswordChange.php?PersonID=' .$currentUser->getId();
      }

      
      // Finally, if the above tests pass, this user "is authenticated"
      $authenticationResult->isAuthenticated = true;
      LoggerUtils::getAuthLogger()->addDebug("Session validated for user: " . $currentUser->getName());
      return $authenticationResult;
    }
  }
}
