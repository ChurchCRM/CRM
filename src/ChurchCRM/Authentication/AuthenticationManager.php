<?php

namespace ChurchCRM\Authentication {

  use ChurchCRM\Authentication\AuthenticationProviders\LocalAuthentication;
  use ChurchCRM\Utils\RedirectUtils;
  use ChurchCRM\Service\NotificationService;
  use ChurchCRM\Utils\LoggerUtils;
  use ChurchCRM\dto\SystemURLs;

class AuthenticationManager
  {

    // This class exists to abstract the implementations of various authentication providers
    // Currently, only local auth is implemented; hence the zero-indexed array elements.

    private static $authenticationProviders;

    public static function EndSession() {
      self::initializeAuthentication();
      $result =self::$authenticationProviders[0]->EndSession();
      RedirectUtils::Redirect(self::GetSessionBeginURL());
    }

    private static function initializeAuthentication() {
      if ( empty( self::$authenticationProviders )) {
        self::$authenticationProviders = array();
        array_push(self::$authenticationProviders, new LocalAuthentication());
      }
    }

    public static function Authenticate(object $AuthenticationRequest) {
      self::initializeAuthentication();
      $result =self::$authenticationProviders[0]->Authenticate($AuthenticationRequest);
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
    
    public static function GetAuthenticationStatus()
    {
      self::initializeAuthentication();
      $result = self::$authenticationProviders[0]->GetAuthenticationStatus();
      LoggerUtils::getAuthLogger()->addDebug("Session authentication status");
      return $result;
    }

    public static function GetCurrentAuthenticationProvider() {
      self::initializeAuthentication();
      return self::$authenticationProviders[0];
    }

    public static function EnsureAuthentication() {
      // This function differs from the sematinc `GetAuthenticationStatus` in that it will
      // take corrective action to redirect the user to an appropriate login location
      // if the current session is not actuall authenticated
      $result = self:: GetAuthenticationStatus();

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
    }

    public static function GetSessionBeginURL() {
      return SystemURLs::getRootPath() . "/session/begin";
    }
  }
}
