<?php

namespace ChurchCRM\Authentication {

  use ChurchCRM\Authentication\AuthenticationProviders\LocalAuthentication;
    use ChurchCRM\Utils\RedirectUtils;

class AuthenticationManager
  {

    private static $authenticationProviders;

    private static function initializeAuthentication() {
      if ( empty( self::$authenticationProviders )) {
        self::$authenticationProviders = array();
        array_push(self::$authenticationProviders, new LocalAuthentication());
      }
    }
    public static function Authencticate()
    {
      self::initializeAuthentication();
      $result = self::$authenticationProviders[0]->Authenticate();
      if (null !== $result->nextStepURL){
        RedirectUtils::Redirect($result->nextStepURL);
      } 
    }
  }
}
