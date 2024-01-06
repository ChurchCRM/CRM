<?php

namespace ChurchCRM\Utils;

use ChurchCRM\dto\SystemConfig;

class MiscUtils
{
    /**
     * @param bool|mixed $isSuccessful
     */
    public static function throwIfFailed($isSuccessful): void
    {
        if ($isSuccessful === false) {
            throw new \Exception('Operation failed.');
        }
    }

    public static function randomToken(): string
    {
        $alphabet = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ1234567890';
        $apiKey = []; //remember to declare $apiKey as an array
        $alphaLength = strlen($alphabet) - 1; //put the length -1 in cache
        for ($i = 0; $i < 50; $i++) {
            $n = random_int(0, $alphaLength);
            $apiKey[] = $alphabet[$n];
        }

        return implode($apiKey); //turn the array into a string
    }

    public static function randomWord(int $length = 6): string
    {
        $cons = ['b', 'c', 'd', 'f', 'g', 'h', 'j', 'k', 'l', 'm', 'n', 'p', 'r', 's', 't', 'v', 'w', 'x', 'z', 'pt', 'gl', 'gr', 'ch', 'ph', 'ps', 'sh', 'st', 'th', 'wh'];
        $cons_cant_start = ['ck', 'cm', 'dr', 'ds', 'ft', 'gh', 'gn', 'kr', 'ks', 'ls', 'lt', 'lr', 'mp', 'mt', 'ms', 'ng', 'ns', 'rd', 'rg', 'rs', 'rt', 'ss', 'ts', 'tch'];
        $vows = ['a', 'e', 'i', 'o', 'u', 'y', 'ee', 'oa', 'oo'];
        $current = (random_int(0, 1) === 0 ? 'cons' : 'vows');
        $word = '';
        while (strlen($word) < $length) {
            if (strlen($word) === 2) {
                $cons = array_merge($cons, $cons_cant_start);
            }
            $rnd = ${$current}[random_int(0, count(${$current}) - 1)];
            if (strlen($word . $rnd) <= $length) {
                $word .= $rnd;
                $current = ($current === 'cons' ? 'vows' : 'cons');
            }
        }

        return $word;
    }

    public static function getRandomCache(int $baseCacheTime, int $variability): int
    {
        $var = random_int(0, $variability);
        $dir = random_int(0, 1);
        if ($dir) {
            return $baseCacheTime - $var;
        } else {
            return $baseCacheTime + $var;
        }
    }

    public static function getPhotoCacheExpirationTimestamp(): int
    {
        $cacheLength = SystemConfig::getValue('iPhotoClientCacheDuration');
        $cacheLength = MiscUtils::getRandomCache($cacheLength, 0.5 * $cacheLength);

        return time() + $cacheLength;
    }

    public static function formatAge(int $Month, int $Day, ?int $Year = null): string
    {
        if (empty($Year)) {
            return '';
        }

        $birthDate = MiscUtils::birthDate($Year, $Month, $Day);

        $now = date_create('today');
        $age = date_diff($now, $birthDate);

        if ($age->y < 1) {
            return sprintf(ngettext('%d month old', '%d months old', $age->m), $age->m);
        }

        return sprintf(ngettext('%d year old', '%d years old', $age->y), $age->y);
    }

    // Format a BirthDate
    // Optionally, the separator may be specified.  Default is YEAR-MN-DY
    public static function formatBirthDate($per_BirthYear, ?string $per_BirthMonth, ?string $per_BirthDay, $sSeparator, $bFlags): string
    {
        try {
            $birthDate = MiscUtils::birthDate($per_BirthYear, $per_BirthMonth, $per_BirthDay);
        } catch (\Throwable $ex) {
            return false;
        }

        if ($bFlags || empty($per_BirthYear)) {
            return $birthDate->format(SystemConfig::getValue('sDateFormatNoYear'));
        }

        return $birthDate->format(SystemConfig::getValue('sDateFormatLong'));
    }

    public static function birthDate($year, ?string $month, ?string $day): \DateTimeImmutable
    {
        if (!empty($day) && !empty($month)) {
            if (empty($year)) {
                $year = 0;
            }

            return new \DateTimeImmutable($year . '-' . $month . '-' . $day);
        }

        throw new \Exception('unexpected error');
    }

    public static function getGitHubWikiAnchorLink(string $text): string
    {
        // roughly adapted from https://gist.github.com/asabaylus/3071099#gistcomment-1593627
        $anchor = strtolower($text);
        $anchor = preg_replace('/[^\w\d\- ]+/', '', $anchor);
        $anchor = preg_replace('/\s/', '-', $anchor);
        $anchor = preg_replace('/\-+$/', '', $anchor);
        $anchor = str_replace(' ', '-', $anchor);

        return $anchor;
    }

    public static function dashesToCamelCase(string $string, bool $capitalizeFirstCharacter = false): string
    {
        $str = str_replace(' ', '', ucwords(str_replace('-', ' ', $string)));

        if (!$capitalizeFirstCharacter) {
            $str[0] = strtolower($str[0]);
        }

        return $str;
    }
}
