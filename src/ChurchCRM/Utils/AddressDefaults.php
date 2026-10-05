<?php

namespace ChurchCRM\Utils;

use ChurchCRM\dto\SystemConfig;

/**
 * Address pre-fill for new people and families: each field uses its configured default
 * and, when that is blank, the church's own value. An admin overrides a field by setting
 * its default. This is the one place that rule lives.
 */
class AddressDefaults
{
    public static function country(): string
    {
        return self::pick('sDefaultCountry', 'sChurchCountry');
    }

    public static function state(): string
    {
        return self::pick('sDefaultState', 'sChurchState');
    }

    public static function city(): string
    {
        return self::pick('sDefaultCity', 'sChurchCity');
    }

    public static function zip(): string
    {
        return self::pick('sDefaultZip', 'sChurchZip');
    }

    private static function pick(string $defaultKey, string $churchKey): string
    {
        $value = self::read($defaultKey);

        return $value !== '' ? $value : self::read($churchKey);
    }

    private static function read(string $key): string
    {
        return trim((string) SystemConfig::getValue($key));
    }
}
