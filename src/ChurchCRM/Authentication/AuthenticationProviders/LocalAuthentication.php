<?php

namespace ChurchCRM\Authentication\AuthenticationProviders {

  use ChurchCRM\Authentication\AuthenticationManager;
  use ChurchCRM\dto\SystemConfig;
  use ChurchCRM\Utils\RedirectUtils;
  use ChurchCRM\Authentication\AuthenticationProviders;
  use ChurchCRM\Authentication\AuthenticationResult;
  use ChurchCRM\dto\SystemURLs;
  use ChurchCRM\Utils\InputUtils;
  use ChurchCRM\UserQuery;
  use ChurchCRM\User;
  use DateTime;
  use DateTimeZone;
  use ChurchCRM\Utils\LoggerUtils;
  use ChurchCRM\KeyManager;
  use PragmaRX\Google2FA\Google2FA;
  use Endroid\QrCode\QrCode;


class LocalAuthentication implements IAuthenticationProvider
{
    private $bNoPasswordRedirect;
    /*** 
     * @var ChurchCRM\User
     */
    private $currentUser;
    private $bPendingTwoFactorAuth;
    private $tLastOperationTimestamp;

    public static function GetIsTwoFactorAuthSupported() {
      return SystemConfig::getBooleanValue("bEnable2FA") && KeyManager::GetAreAllSecretsDefined();
    }

    public static function GetTwoFactorQRCode($username,$secret):QrCode {
      $google2fa = new Google2FA();
      $g2faUrl = $google2fa->getQRCodeUrl(
          SystemConfig::getValue("s2FAApplicationName"),
          $username,
          $secret
      );
      $qrCode = new QrCode($g2faUrl );
      $qrCode->setSize(300);
      return $qrCode;
    }

    public function __construct() {
      $this->bNoPasswordRedirect = false;
    }

    public function GetCurrentUser()
    {
      return $this->currentUser;
    }

    public function EndSession() {
      
      if (!empty($this->currentUser)) {
          if (!isset($_SESSION['sshowPledges']) || ($_SESSION['sshowPledges'] == '')) {
              $_SESSION['sshowPledges'] = 0;
          }
          if (!isset($_SESSION['sshowPayments']) || ($_SESSION['sshowPayments'] == '')) {
              $_SESSION['sshowPayments'] = 0;
          }
      
          if (!empty($this->currentUser)) {
              $this->currentUser->setShowPledges($_SESSION['sshowPledges']);
              $this->currentUser->setShowPayments($_SESSION['sshowPayments']);
              //$this->currentUser->setDefaultFY($_SESSION['idefaultFY']);
              $this->currentUser->setCurrentDeposit($_SESSION['iCurrentDeposit']);
      
              $this->currentUser->save();
          }
          $this->currentUser = null;
      }
   }

    private function prepareSuccessfulLoginOperations() {
      // Set the LastLogin and Increment the LoginCount
      $date = new DateTime('now', new DateTimeZone(SystemConfig::getValue('sTimeZone')));
      $this->currentUser->setLastLogin($date->format('Y-m-d H:i:s'));
      $this->currentUser->setLoginCount($this->currentUser->getLoginCount() + 1);
      $this->currentUser->setFailedLogins(0);
      $this->currentUser->save();

      $_SESSION['bManageGroups'] = $this->currentUser->isManageGroupsEnabled();
      $_SESSION['bFinance'] = $this->currentUser->isFinanceEnabled();

      // Create the Cart
      $_SESSION['aPeopleCart'] = [];

      // Create the variable for the Global Message
      $_SESSION['sGlobalMessage'] = '';

      // Initialize the last operation time
      $this->tLastOperationTimestamp = time();

      $_SESSION['bHasMagicQuotes'] = 0;

      // Pledge and payment preferences
      $_SESSION['sshowPledges'] = $this->currentUser->getShowPledges();
      $_SESSION['sshowPayments'] = $this->currentUser->getShowPayments();
      //$_SESSION['idefaultFY'] = CurrentFY(); // Improve the chance of getting the correct fiscal year assigned to new transactions
      $_SESSION['iCurrentDeposit'] = $this->currentUser->getCurrentDeposit();
    }

    public function Authenticate(object $AuthenticationRequest) {
      $authenticationResult = new AuthenticationResult();

      if (isset($AuthenticationRequest->User)) {
        LoggerUtils::getAuthLogger()->addDebug("Processing local login for" . $AuthenticationRequest->User);
        // Get the information for the selected user
        $this->currentUser = UserQuery::create()->findOneByUserName($AuthenticationRequest->User);
        if ($this->currentUser == null) {
          // Set the error text
          $authenticationResult->isAuthenticated = false;
          $authenticationResult->message = gettext('Invalid login or password');
          return $authenticationResult;
        } // Block the login if a maximum login failure count has been reached
        elseif ($this->currentUser->isLocked()) {
          $authenticationResult->isAuthenticated = false;
          $authenticationResult->message = gettext('Too many failed logins: your account has been locked.  Please contact an administrator.');
          LoggerUtils::getAuthLogger()->addWarning("Authentication attempt for locked account: " . $this->currentUser->getUserName());
          return $authenticationResult;
        } // Does the password match?
        elseif (!$this->currentUser->isPasswordValid($AuthenticationRequest->Password)) {
          // Increment the FailedLogins
          $this->currentUser->setFailedLogins($this->currentUser->getFailedLogins() + 1);
          $this->currentUser->save();
          if (!empty($this->currentUser->getEmail()) && $this->currentUser->isLocked()) {
              LoggerUtils::getAuthLogger()->addWarning("Too many failed logins for: " . $this->currentUser->getUserName() . ". The account has been locked");
              $lockedEmail = new LockedEmail($this->currentUser);
              $lockedEmail->send();
          }
          $authenticationResult->isAuthenticated = false;
          $authenticationResult->message = gettext('Invalid login or password');
          LoggerUtils::getAuthLogger()->addWarning("Invalid login attempt for: " . $this->currentUser->getUserName());
          return $authenticationResult;
        } elseif(SystemConfig::getBooleanValue("bEnable2FA") && $this->currentUser->is2FactorAuthEnabled()) {
          // Only redirect the user to the 2FA sign-ing page if it's 
          // enabled both at system AND user level. 
          $authenticationResult->isAuthenticated = false;
          $authenticationResult->nextStepURL = SystemURLs::getRootPath()."/session/two-factor";
          $this->bPendingTwoFactorAuth = true;
          return $authenticationResult;
        } elseif(SystemConfig::getBooleanValue("bRequire2FA") && ! $this->currentUser->is2FactorAuthEnabled()) {
          $authenticationResult->isAuthenticated = false;
          $authenticationResult->message = gettext('Invalid login or password');
          LoggerUtils::getAuthLogger()->addWarning("User attempted login with valid credentials, but missing mandatory 2FA enrollment.  Denying access for user: " . $this->currentUser->getUserName());
          return $authenticationResult;
        } else {
            $this->prepareSuccessfulLoginOperations();
            $authenticationResult->isAuthenticated = true;
            LoggerUtils::getAuthLogger()->addInfo("User succefully logged in without 2FA: " . $this->currentUser->getUserName());
            return $authenticationResult;
          }
        }
        elseif(isset($AuthenticationRequest->TwoFACode) && $this->bPendingTwoFactorAuth) {
          if ($this->currentUser->isTwoFACodeValid($AuthenticationRequest->TwoFACode)) {
            $this->prepareSuccessfulLoginOperations();
            $authenticationResult->isAuthenticated = true;
            $this->bPendingTwoFactorAuth = false;
            LoggerUtils::getAuthLogger()->addInfo("User succefully logged in with 2FA: " . $this->currentUser->getUserName());
            return $authenticationResult;
          }
          elseif($this->currentUser->isTwoFaRecoveryCodeValid($AuthenticationRequest->TwoFACode)){
            LoggerUtils::getAuthLogger()->addInfo("testing recovery code" . $AuthenticationRequest->TwoFACode);
            $this->prepareSuccessfulLoginOperations();
            $authenticationResult->isAuthenticated = true;
            $this->bPendingTwoFactorAuth = false;
            LoggerUtils::getAuthLogger()->addInfo("User succefully logged in with 2FA Recovery Code: " . $this->currentUser->getUserName());
            return $authenticationResult;
          }
          else {
            $authenticationResult->isAuthenticated = false;
            $authenticationResult->nextStepURL = SystemURLs::getRootPath()."/session/two-factor";
            return $authenticationResult;
          }
        }
    }

    public function DisablePasswordChangeRedirect() {
      $this->bNoPasswordRedirect = true;
    }

    public function ValidateUserSessionIsActive($updateLastOperationTimestamp) : AuthenticationResult
    {

      $authenticationResult = new AuthenticationResult();

      // First check to see if a `user` key exists on the session.
      if (null == $this->currentUser) {
        $authenticationResult->isAuthenticated = false;
        LoggerUtils::getAuthLogger()->addDebug("No active user session.");
        return $authenticationResult;
      }
      LoggerUtils::getAuthLogger()->addDebug("Processing session for user: " . $this->currentUser->getName());

      // Next, make sure the user in the sesion still exists in the database.
      try {
        $this->currentUser->reload();
      } catch (\Exception $exc) {
        LoggerUtils::getAuthLogger()->addDebug("User with active session no longer exists in the database.  Expiring session");
        AuthenticationManager::EndSession();
        $authenticationResult->isAuthenticated = false;
        return $authenticationResult;
      }

      // Next, check for login timeout.  If login has expired, redirect to login page
      if (SystemConfig::getValue('iSessionTimeout') > 0) {
        if ((time() - $this->tLastOperationTimestamp) > SystemConfig::getValue('iSessionTimeout')) {
          LoggerUtils::getAuthLogger()->addDebug("User session timed out");
          $authenticationResult->isAuthenticated = false;
          return $authenticationResult;
        } else if( $updateLastOperationTimestamp ) {
          $this->tLastOperationTimestamp = time();
        }
      }
      // Next, if this user needs to change password, send to that page
      if ($this->currentUser->getNeedPasswordChange() && !$this->bNoPasswordRedirect ) {
        LoggerUtils::getAuthLogger()->addDebug("User needs password change; redirecting to password change");
        $authenticationResult->isAuthenticated = false;
        $authenticationResult->nextStepURL = 'UserPasswordChange.php?PersonID=' .$this->currentUser->getId();
      }

      
      // Finally, if the above tests pass, this user "is authenticated"
      $authenticationResult->isAuthenticated = true;
      LoggerUtils::getAuthLogger()->addDebug("Session validated for user: " . $this->currentUser->getName());
      return $authenticationResult;
    }
  }
}
