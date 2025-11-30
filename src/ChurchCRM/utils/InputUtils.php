<?php

namespace ChurchCRM\Utils;

use ChurchCRM\dto\SystemConfig;

class InputUtils
{
    private static string $AllowedHTMLTags = '<a><b><i><u><h1><h2><h3><h4><h5><h6><pre><address><img><table><td><tr><ol><li><ul><p><sub><sup><s><hr><span><blockquote><div><small><big><tt><code><kbd><samp><del><ins><cite><q>';

    public static function legacyFilterInputArr(array $arr, $key, $type = 'string', $size = 1)
    {
        if (array_key_exists($key, $arr)) {
            return InputUtils::legacyFilterInput($arr[$key], $type, $size);
        } else {
            return InputUtils::legacyFilterInput('', $type, $size);
        }
    }

    public static function translateSpecialCharset($string): string
    {
        if (empty($string)) {
            return '';
        }

        if (SystemConfig::getValue('sCSVExportCharset') === 'UTF-8') {
            return gettext($string);
        }

        $resultString = iconv(
            'UTF-8',
            SystemConfig::getValue('sCSVExportCharset'),
            gettext($string)
        );
        MiscUtils::throwIfFailed($resultString);

        return $resultString;
    }

    public static function filterString($sInput): string
    {
        // or use htmlspecialchars( stripslashes( ))
        return strip_tags(trim($sInput));
    }

    public static function filterSanitizeString($sInput): string
    {
        return filter_var(trim($sInput), FILTER_SANITIZE_SPECIAL_CHARS);
    }

    /**
     * Sanitize HTML input by stripping disallowed tags and removing dangerous attributes.
     * This prevents XSS attacks via event handlers (onclick, onerror, etc.) and javascript: URLs.
     */
    public static function filterHTML($sInput): string
    {
        $filtered = strip_tags(trim($sInput), self::$AllowedHTMLTags);

        // Strip event handler attributes (on*) to prevent XSS
        $filtered = preg_replace('/\bon\w+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]*)/i', '', $filtered);

        // Strip javascript: and data: URLs from href and src attributes
        $filtered = preg_replace('/\b(href|src)\s*=\s*("[^"]*javascript:[^"]*"|\'[^\']*javascript:[^\']*\')/i', '$1="#"', $filtered);
        $filtered = preg_replace('/\b(href|src)\s*=\s*("[^"]*data:[^"]*"|\'[^\']*data:[^\']*\')/i', '$1="#"', $filtered);

        return $filtered;
    }

    public static function filterChar($sInput, $size = 1): string
    {
        return mb_substr(trim($sInput), 0, $size);
    }

    public static function filterInt($sInput): int
    {
        // added this to prevent deprecation warning:
        //   PHP Deprecated:  trim(): Passing null to parameter #1 ($string) of type string is deprecated
        if ($sInput === null) {
            return 0;
        }

        return intval(trim($sInput));
    }

    public static function filterFloat($sInput): float
    {
        // added this to prevent deprecation warning:
        //   PHP Deprecated:  trim(): Passing null to parameter #1 ($string) of type string is deprecated
        if ($sInput === null) {
            return 0;
        }

        return floatval(trim($sInput));
    }

    public static function filterDate($sInput): string
    {
        // Attempts to take a date in any format and convert it to YYYY-MM-DD format
        // Logel Philippe
        if (empty($sInput)) {
            return '';
        } else {
            return date('Y-m-d', strtotime(str_replace('/', '-', $sInput)));
        }
    }

    // Sanitizes user input as a security measure
    // Optionally, a filtering type and size may be specified.  By default, strip any tags from a string.
    // Note that a database connection must already be established for the mysqli_real_escape_string function to work.
    public static function legacyFilterInput($sInput, $type = 'string', $size = 1)
    {
        global $cnInfoCentral;
        if (strlen($sInput) > 0) {
            switch ($type) {
                case 'string':
                    return mysqli_real_escape_string($cnInfoCentral, self::filterString($sInput));
                case 'htmltext':
                    return mysqli_real_escape_string($cnInfoCentral, self::filterHTML($sInput));
                case 'char':
                    return mysqli_real_escape_string($cnInfoCentral, self::filterChar($sInput, $size));
                case 'int':
                    return self::filterInt($sInput);
                case 'float':
                    return self::filterFloat($sInput);
                case 'date':
                    return self::filterDate($sInput);
                default:
                    throw new \InvalidArgumentException('Invalid "type" for legacyFilterInput provided');
            }
        } else {
            return '';
        }
    }
}
