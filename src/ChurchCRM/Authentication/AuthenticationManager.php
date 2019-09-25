<?php

namespace ChurchCRM\Authentication {

  use ChurchCRM\Authentication\AuthenticationProviders\LocalAuthentication;
  use ChurchCRM\Utils\RedirectUtils;
  use ChurchCRM\Service\NotificationService;

class AuthenticationManager
  {

    private static $authenticationProviders;

    public static function EndSession() {
      self::initializeAuthentication();
      $result =self::$authenticationProviders[0]->EndSession();
      RedirectUtils::Redirect("/session/begin");
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
        RedirectUtils::Redirect($result->nextStepURL);
      }
      NotificationService::updateNotifications();
    }
    
    public static function IsAuthenticated()
    {
      self::initializeAuthentication();
      $result = self::$authenticationProviders[0]->isAuthenticated();
      if (null !== $result->nextStepURL){
        RedirectUtils::Redirect($result->nextStepURL);
      } 
    }
  }
}
