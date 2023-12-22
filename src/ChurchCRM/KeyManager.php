<?php

namespace ChurchCRM;

class KeyManager
{
    private static $TwoFASecretKey;

    public static function init($TwoFASecretKey): void
    {
        self::$TwoFASecretKey = $TwoFASecretKey;
    }

    public static function getTwoFASecretKey()
    {
        return self::$TwoFASecretKey;
    }

    public static function getAreAllSecretsDefined(): bool
    {
        return !empty(self::$TwoFASecretKey);
    }
}
