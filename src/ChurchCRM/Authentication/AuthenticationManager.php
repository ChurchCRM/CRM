<?php

namespace ChurchCRM\Authentication {

  use ChurchCRM\Utils\RedirectUtils;
  use ChurchCRM\Service\NotificationService;
  use ChurchCRM\Utils\LoggerUtils;
  use ChurchCRM\dto\SystemURLs;
  use ChurchCRM\Authentication\AuthenticationProviders\IAuthenticationProvider;
    use ChurchCRM\Bootstrapper;

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

    public static function GetCurrentUser() {
      return self::GetAuthenticationProvider()->GetCurrentUser();
    }


    public static function EndSession() {
      $currentSessionUserName = "";
      try {
        $currentSessionUserName = self::GetCurrentUser()->getName();
      }
      catch(\Exception $e) {
        //unable to get name of user logging out. Don't really care.
        $currentSessionUserName = "Unknown";
      }
      try {
        $result = self::GetAuthenticationProvider()->EndSession();
        $_COOKIE = [];
        $_SESSION = [];
        session_destroy();
        Bootstrapper::initSession();
        LoggerUtils::getAuthLogger()->addInfo("Ended Local session for user " . $currentSessionUserName);
      }
      catch(\Exception $e) {
        LoggerUtils::getAuthLogger()->addWarning("Error destroying session: " . $e);
      }
      finally {
        RedirectUtils::Redirect(self::GetSessionBeginURL());
      }
    }

    public static function Authenticate(IAuthenticationProvider $AuthenticationProvider, object $AuthenticationRequest) {
      $result = $AuthenticationProvider->Authenticate($AuthenticationRequest);
      self::SetAuthenticationProvider($AuthenticationProvider);
      if (null !== $result->nextStepURL){
        LoggerUtils::getAuthLogger()->addDebug("Authentication requires additional step: " . $result->nextStepURL);
        RedirectUtils::Redirect($result->nextStepURL);
      }
      
      if ($result->isAuthenticated) {
        $redirectLocation = array_key_exists("location", $_SESSION) ? $_SESSION['location'] : 'Menu.php';
        NotificationService::updateNotifications();
        LoggerUtils::getAuthLogger()->addDebug("Authentication Successful; redirecting to: " . $redirectLocation);
        RedirectUtils::Redirect($redirectLocation);
       
      }
      return $result;
    }

    public static function ValidateUserSessionIsActive($updateLastOperationTimestamp = true) {
      try {
        $result = self::GetAuthenticationProvider()->ValidateUserSessionIsActive($updateLastOperationTimestamp);
        return $result->isAuthenticated;

      }
      catch (\Throwable $error){
        LoggerUtils::getAuthLogger()->addDebug("Error determining session authentication status." . $error);
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
          LoggerUtils::getAuthLogger()->addDebug("Session not authenticated.  Redirecting to login page");
          RedirectUtils::Redirect(self::GetSessionBeginURL());
        }elseif(null !== $result->nextStepURL){
          LoggerUtils::getAuthLogger()->addDebug("Session authenticated, but redirect requested by authentication provider.");
          RedirectUtils::Redirect($result->nextStepURL);
        }
        LoggerUtils::getAuthLogger()->addDebug("Session valid");
      } catch (\Throwable $error){
        LoggerUtils::getAuthLogger()->addDebug("Error determining session authentication status.  Redirecting to login page. " . $error);
        RedirectUtils::Redirect(self::GetSessionBeginURL());
      }
    }

    public static function GetSessionBeginURL() {
      return SystemURLs::getRootPath() . "/session/begin";
    }
  }
}
