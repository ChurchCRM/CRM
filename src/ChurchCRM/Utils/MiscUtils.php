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

        return implode('', $apiKey); //turn the array into a string
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

    /** Time from a birth date to $asOf, or null when the birth date is after $asOf. */
    public static function ageOn(\DateTimeInterface $birthDate, \DateTimeInterface $asOf): ?\DateInterval
    {
        return $birthDate > $asOf ? null : $birthDate->diff($asOf);
    }

    public static function formatAge(int $Month, int $Day, ?int $Year = null): string
    {
        if (empty($Year) || empty($Month) || empty($Day)) {
            return '';
        }

        $birthDate = MiscUtils::birthDate($Year, $Month, $Day);

        $age = MiscUtils::ageOn($birthDate, date_create('today'));
        if ($age === null) {
            return '';
        }

        if ($age->y < 1) {
            $monthStr = ngettext('%d month old', '%d months old', $age->m);
            try {
                return sprintf($monthStr, $age->m);
            } catch (\Throwable $e) {
                error_log('Age formatting failed for locale string "' . $monthStr . '": ' . $e->getMessage());
                return $age->m . ' ' . trim(preg_replace('/%\d*\$?[sdifuoxX]\s*/u', '', $monthStr) ?? $monthStr);
            }
        }

        $yearStr = ngettext('%d year old', '%d years old', $age->y);
        try {
            return sprintf($yearStr, $age->y);
        } catch (\Throwable $e) {
            error_log('Age formatting failed for locale string "' . $yearStr . '": ' . $e->getMessage());
            return $age->y . ' ' . trim(preg_replace('/%\d*\$?[sdifuoxX]\s*/u', '', $yearStr) ?? $yearStr);
        }
    }

    // Format a BirthDate
    // Optionally, the separator may be specified.  Default is YEAR-MN-DY
    public static function formatBirthDate($per_BirthYear, ?string $per_BirthMonth, ?string $per_BirthDay, ?bool $bFlags = false): string
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

        return str_replace(' ', '-', $anchor);
    }

    public static function dashesToCamelCase(string $string, bool $capitalizeFirstCharacter = false): string
    {
        $str = str_replace(' ', '', ucwords(str_replace('-', ' ', $string)));

        if (!$capitalizeFirstCharacter) {
            $str[0] = strtolower($str[0]);
        }

        return $str;
    }

    /**
     * Returns a string of a person's full name formatted by style.
     * Style 0: "Title FirstName MiddleName LastName, Suffix"
     * Style 1: "Title FirstName MiddleInitial. LastName, Suffix"
     * Style 2: "LastName, Title FirstName MiddleName, Suffix"
     * Style 3: "LastName, Title FirstName MiddleInitial., Suffix"
     * Migrated from FormatFullName() in Functions.php.
     */
    public static function formatFullName(?string $Title, ?string $FirstName, ?string $MiddleName, ?string $LastName, ?string $Suffix, $Style): string
    {
        $nameString = '';

        switch ($Style) {
            case 0:
                if ($Title) {
                    $nameString .= $Title . ' ';
                }
                $nameString .= $FirstName;
                if ($MiddleName) {
                    $nameString .= ' ' . $MiddleName;
                }
                if ($LastName) {
                    $nameString .= ' ' . $LastName;
                }
                if ($Suffix) {
                    $nameString .= ', ' . $Suffix;
                }
                break;

            case 1:
                if ($Title) {
                    $nameString .= $Title . ' ';
                }
                $nameString .= $FirstName;
                if ($MiddleName) {
                    $nameString .= ' ' . mb_strtoupper(mb_substr($MiddleName, 0, 1)) . '.';
                }
                if ($LastName) {
                    $nameString .= ' ' . $LastName;
                }
                if ($Suffix) {
                    $nameString .= ', ' . $Suffix;
                }
                break;

            case 2:
                if ($LastName) {
                    $nameString .= $LastName . ', ';
                }
                if ($Title) {
                    $nameString .= $Title . ' ';
                }
                $nameString .= $FirstName;
                if ($MiddleName) {
                    $nameString .= ' ' . $MiddleName;
                }
                if ($Suffix) {
                    $nameString .= ', ' . $Suffix;
                }
                break;

            case 3:
                if ($LastName) {
                    $nameString .= $LastName . ', ';
                }
                if ($Title) {
                    $nameString .= $Title . ' ';
                }
                $nameString .= $FirstName;
                if ($MiddleName) {
                    $nameString .= ' ' . mb_strtoupper(mb_substr($MiddleName, 0, 1)) . '.';
                }
                if ($Suffix) {
                    $nameString .= ', ' . $Suffix;
                }
                break;
        }

        return $nameString;
    }

    /**
     * Generates a formatted address line: " - Address / City, State".
     * Migrated from FormatAddressLine() in Functions.php.
     */
    public static function formatAddressLine(?string $Address, ?string $City, ?string $State): string
    {
        $sText = '';

        if ($Address !== '' || $City !== '' || $State !== '') {
            $sText = ' - ';
        }
        $sText .= $Address;
        if ($Address !== '' && ($City !== '' || $State !== '')) {
            $sText .= ' / ';
        }
        $sText .= $City;
        if ($City !== '' && $State !== '') {
            $sText .= ', ';
        }

        return $sText . $State;
    }

    /**
     * Converts a font filename to a human-readable font name with style modifiers.
     * Migrated from FilenameToFontname() in Functions.php.
     */
    public static function filenameToFontname(string $filename, string $family): string
    {
        if ($filename == $family) {
            return ucfirst($family);
        } else {
            if (strlen($filename) - strlen($family) === 2) {
                return ucfirst($family) . ' Bold Italic';
            } else {
                if (mb_substr($filename, strlen($filename) - 1) === 'i') {
                    return ucfirst($family) . ' Italic';
                } else {
                    return ucfirst($family) . ' Bold';
                }
            }
        }
    }

    /**
     * Parses a font name string into [family, modifiers] components.
     * Migrated from FontFromName() in Functions.php.
     */
    public static function fontFromName(string $fontname)
    {
        $fontinfo = explode(' ', $fontname);
        switch (count($fontinfo)) {
            case 1:
                return [$fontinfo[0], ''];
            case 2:
                return [$fontinfo[0], mb_substr($fontinfo[1], 0, 1)];
            case 3:
                return [$fontinfo[0], mb_substr($fontinfo[1], 0, 1) . mb_substr($fontinfo[2], 0, 1)];
        }
    }
}
