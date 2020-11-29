<?php

namespace ChurchCRM
{

  class KeyManager
  {
      private static $TwoFASecretKey;

      public static function init($TwoFASecretKey)
      {
          self::$TwoFASecretKey = $TwoFASecretKey;
      }

      public static function GetTwoFASecretKey()
      {
          return self::$TwoFASecretKey;
      }

      public static function GetAreAllSecretsDefined()
      {
          return !empty(self::$TwoFASecretKey);
      }
  }

}
