<?php

namespace ChurchCRM\Authentication {

  use ChurchCRM\Authentication\AuthenticationProviders\LocalAuthentication;
  use ChurchCRM\Utils\RedirectUtils;
  use ChurchCRM\Service\NotificationService;

class AuthenticationManager
  {

    private static $authenticationProviders;

    private static $correlationId;
    public static function EndSession() {
      self::initializeAuthentication();
      $result =self::$authenticationProviders[0]->EndSession();
      RedirectUtils::Redirect("/session/begin");
    }

    private static function initializeAuthentication() {
      if ( empty( self::$authenticationProviders )) {
        self::$correlationId = uniqid();
        self::$authenticationProviders = array();
        array_push(self::$authenticationProviders, new LocalAuthentication());
      }
    }

    public static function Authenticate(object $AuthenticationRequest) {
      self::initializeAuthentication();
      $result =self::$authenticationProviders[0]->Authenticate($AuthenticationRequest);
      if (null !== $result->nextStepURL){
        RedirectUtils::Redirect($result->nextStepURL);
      }
      NotificationService::updateNotifications();
    }
    
    public static function GetCorrelationId() {
      self::initializeAuthentication();
      return self::$correlationId;
    }
    public static function GetAuthenticationStatus()
    {
      self::initializeAuthentication();
      $result = self::$authenticationProviders[0]->GetAuthenticationStatus();
      $result = self::$authenticationProviders[0]->isAuthenticated();
      if (null !== $result->nextStepURL){
      return $result;
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
        RedirectUtils::Redirect(self::GetSessionBeginURL());
      }elseif(null !== $result->nextStepURL){
        RedirectUtils::Redirect($result->nextStepURL);
      } 
    }

    public static function GetSessionBeginURL() {
      return SystemURLs::getRootPath() . "/session/begin";
    }
  }
}
