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

    public static function randomToken()
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

    public static function randomWord($length = 6)
    {
        $cons = ['b', 'c', 'd', 'f', 'g', 'h', 'j', 'k', 'l', 'm', 'n', 'p', 'r', 's', 't', 'v', 'w', 'x', 'z', 'pt', 'gl', 'gr', 'ch', 'ph', 'ps', 'sh', 'st', 'th', 'wh'];
        $cons_cant_start = ['ck', 'cm', 'dr', 'ds', 'ft', 'gh', 'gn', 'kr', 'ks', 'ls', 'lt', 'lr', 'mp', 'mt', 'ms', 'ng', 'ns', 'rd', 'rg', 'rs', 'rt', 'ss', 'ts', 'tch'];
        $vows = ['a', 'e', 'i', 'o', 'u', 'y', 'ee', 'oa', 'oo'];
        $current = ( random_int(0, 1) == '0' ? 'cons' : 'vows' );
        $word = '';
        while (strlen($word) < $length) {
            if (strlen($word) == 2) {
                $cons = array_merge($cons, $cons_cant_start);
            }
            $rnd = ${$current}[ random_int(0, count(${$current}) - 1) ];
            if (strlen($word . $rnd) <= $length) {
                $word .= $rnd;
                $current = ( $current == 'cons' ? 'vows' : 'cons' );
            }
        }
        return $word;
    }

    public static function getRandomCache($baseCacheTime, $variability)
    {
        $var = random_int(0, $variability);
        $dir = random_int(0, 1);
        if ($dir) {
            return $baseCacheTime - $var;
        } else {
            return $baseCacheTime + $var;
        }
    }

    public static function getPhotoCacheExpirationTimestamp()
    {
        $cacheLength = SystemConfig::getValue("iPhotoClientCacheDuration");
        $cacheLength = MiscUtils::getRandomCache($cacheLength, 0.5 * $cacheLength);
        return time() + $cacheLength ;
    }

    public static function formatAge(int $Month, int $Day, int $Year)
    {
        if ($Year === null || $Year === '') {
            return;
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
    public static function formatBirthDate($per_BirthYear, $per_BirthMonth, $per_BirthDay, $sSeparator, $bFlags)
    {
        $birthDate = MiscUtils::birthDate($per_BirthYear, $per_BirthMonth, $per_BirthDay);
        if (!$birthDate) {
            return false;
        }
        if ($bFlags || $per_BirthYear === null || $per_BirthYear === '') {
            return $birthDate->format(SystemConfig::getValue("sDateFormatNoYear"));
        }

        return $birthDate->format(SystemConfig::getValue("sDateFormatLong"));
    }

    public static function birthDate($year, $month, $day)
    {
        if (!$day !== null && $day !== '' && $month !== null && $month !== '') {
            if ($year === null || $year === '') {
                 $year = 1900;
            }

            return date_create($year . '-' . $month . '-' . $day);
        }

        return false;
    }

    public static function getGitHubWikiAnchorLink($text)
    {
      // roughly adapted from https://gist.github.com/asabaylus/3071099#gistcomment-1593627
        $anchor = strtolower($text);
        $anchor = preg_replace('/[^\w\d\- ]+/', '', $anchor);
        $anchor = preg_replace('/\s/', '-', $anchor);
        $anchor = preg_replace('/\-+$/', '', $anchor);
        $anchor = str_replace(" ", "-", $anchor);

        return $anchor;
    }

    public static function dashesToCamelCase($string, $capitalizeFirstCharacter = false)
    {
        $str = str_replace(' ', '', ucwords(str_replace('-', ' ', $string)));

        if (!$capitalizeFirstCharacter) {
            $str[0] = strtolower($str[0]);
        }

        return $str;
    }
}
