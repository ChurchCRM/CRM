<?php

namespace ChurchCRM\Authentication {

  use ChurchCRM\Utils\RedirectUtils;
  use ChurchCRM\Service\NotificationService;
  use ChurchCRM\Utils\LoggerUtils;
  use ChurchCRM\dto\SystemURLs;
  use ChurchCRM\Authentication\AuthenticationProviders\IAuthenticationProvider;
  use ChurchCRM\Bootstrapper;
  use ChurchCRM\Authentication\Requests\AuthenticationRequest;
  use ChurchCRM\Authentication\AuthenticationProviders\LocalAuthentication;
  use ChurchCRM\Authentication\AuthenticationProviders\APITokenAuthentication;
    use ChurchCRM\User;

class AuthenticationManager
  {

    // This class exists to abstract the implementations of various authentication providers
    // Currently, only local auth is implemented; hence the zero-indexed array elements.

    public static function GetAuthenticationProvider() {
      if ( key_exists("AuthenticationProvider", $_SESSION) && $_SESSION['AuthenticationProvider'] instanceof IAuthenticationProvider) {
        return  $_SESSION['AuthenticationProvider'];
      }
      else {
        throw new \Exception("No active authentication provider");
      }
    }

    private static function SetAuthenticationProvider(IAuthenticationProvider $AuthenticationProvider) {
      $_SESSION['AuthenticationProvider'] = $AuthenticationProvider;
    }

    public static function GetCurrentUser() : User {
      try {
        $currentUser = self::GetAuthenticationProvider()->GetCurrentUser();
        if (empty($currentUser)) {
          throw new \Exception("No current user provided by current authentication provider: " . get_class(self::GetAuthenticationProvider()));
        }
        return $currentUser;
      }
      catch (\Exception $e){
        LoggerUtils::getAppLogger()->debug("Failed to get current user: " . $e);
        throw $e;
      }
    }

    public static function EndSession($preventRedirect=false) {
      $currentSessionUserName = "Unknown";
      try {
        if (self::GetCurrentUser() != null) {
          $currentSessionUserName = self::GetCurrentUser()->getName();
        }
      }
      catch(\Exception $e) {
        //unable to get name of user logging out. Don't really care.
      }
      try {
        $result = self::GetAuthenticationProvider()->EndSession();
        $_COOKIE = [];
        $_SESSION = [];
        session_destroy();
        Bootstrapper::initSession();
        LoggerUtils::getAuthLogger()->info("Ended Local session for user " . $currentSessionUserName);
      }
      catch(\Exception $e) {
        LoggerUtils::getAuthLogger()->warning("Error destroying session: " . $e);
      }
      finally {
        if(!$preventRedirect) {
          RedirectUtils::Redirect(self::GetSessionBeginURL());
        }
      }
    }

    public static function Authenticate(AuthenticationRequest $AuthenticationRequest) {
      switch (get_class($AuthenticationRequest)){
        case "ChurchCRM\Authentication\Requests\APITokenAuthenticationRequest":
          $AuthenticationProvider = new APITokenAuthentication();
          self::SetAuthenticationProvider($AuthenticationProvider);
        break;
        case "ChurchCRM\Authentication\Requests\LocalUsernamePasswordRequest":
          $AuthenticationProvider = new LocalAuthentication();
          self::SetAuthenticationProvider($AuthenticationProvider);
        break;
        case "ChurchCRM\Authentication\Requests\LocalTwoFactorTokenRequest":
          try {
            self::GetAuthenticationProvider();
          }
          catch (\Exception $e)
          {
            LoggerUtils::getAppLogger()->warning("Tried to supply two factor authentication code, but didn't have an existing session.  This shouldn't ever happen");
          }
        break;
        default:
          LoggerUtils::getAppLogger()->critical("Unknown AuthenticationRequest type supplied");
        break;
      }

      $result = self::GetAuthenticationProvider()->Authenticate($AuthenticationRequest);

      if (null !== $result->nextStepURL){
        LoggerUtils::getAuthLogger()->debug("Authentication requires additional step: " . $result->nextStepURL);
        RedirectUtils::Redirect($result->nextStepURL);
      }

      if ($result->isAuthenticated && ! $result->preventRedirect) {
        $redirectLocation = array_key_exists("location", $_SESSION) ? $_SESSION['location'] : 'Menu.php';
        NotificationService::updateNotifications();
        LoggerUtils::getAuthLogger()->debug("Authentication Successful; redirecting to: " . $redirectLocation);
        RedirectUtils::Redirect($redirectLocation);

      }
      return $result;
    }

    public static function ValidateUserSessionIsActive($updateLastOperationTimestamp = true) {
      try {
        $result = self::GetAuthenticationProvider()->ValidateUserSessionIsActive($updateLastOperationTimestamp);
        return $result->isAuthenticated;
      }
      catch (\Exception $error){
        LoggerUtils::getAuthLogger()->debug("Error determining session authentication status." . $error);
        return false;
      }
    }

    public static function EnsureAuthentication() {
      // This function differs from the sematinc `ValidateUserSessionIsActive` in that it will
      // take corrective action to redirect the user to an appropriate login location
      // if the current session is not actuall authenticated

      try {
        $result = self::GetAuthenticationProvider()->ValidateUserSessionIsActive(true);
        // Auth providers will always include a `nextStepURL` if authentication fails.
        // Sometimes other actions may require a `nextStepURL` that should be enforced with
        // an autentication request (2FA, Expired Password, etc).
        if (!$result->isAuthenticated){
          LoggerUtils::getAuthLogger()->debug("Session not authenticated.  Redirecting to login page");
          RedirectUtils::Redirect(self::GetSessionBeginURL());
        }elseif(null !== $result->nextStepURL){
          LoggerUtils::getAuthLogger()->debug("Session authenticated, but redirect requested by authentication provider.");
          RedirectUtils::Redirect($result->nextStepURL);
        }
        LoggerUtils::getAuthLogger()->debug("Session valid");
      } catch (\Throwable $error){
        LoggerUtils::getAuthLogger()->debug("Error determining session authentication status.  Redirecting to login page. " . $error);
        RedirectUtils::Redirect(self::GetSessionBeginURL());
      }
    }

    public static function GetSessionBeginURL() {
      return SystemURLs::getRootPath() . "/session/begin";
    }

    public static function GetForgotPasswordURL() {
      // this assumes we're using local authentication
      // TODO: when we implement other authentication providers (SAML/etc)
      // this URL will need to be configuable by the system administrator
      // since they likely will not want users attempting to reset ChurchCRM passwords
      // but rather redirect users to some other password reset mechanism.
      return SystemURLs::getRootPath() . "/session/forgot-password/reset-request";
    }
  }
}
