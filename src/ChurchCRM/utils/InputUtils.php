<?php

namespace ChurchCRM\Utils;

use ChurchCRM\dto\SystemConfig;

class InputUtils
{
    private static string $AllowedHTMLTags = '<a><b><i><u><h1><h2><h3><h4><h5><h6><pre><address><img><table><td><tr><ol><li><ul><p><sub><sup><s><hr><span><blockquote><div><small><big><tt><code><kbd><samp><del><ins><cite><q>';

    public static function legacyFilterInputArr($arr, $key, $type = 'string', $size = 1)
    {
        if (array_key_exists($key, $arr)) {
            return InputUtils::legacyFilterInput($arr[$key], $type, $size);
        } else {
            return InputUtils::legacyFilterInput('', $type, $size);
        }
    }

    public static function translateSpecialCharset($string)
    {
        if (empty($string)) {
            return '';
        }

        return (SystemConfig::getValue('sCSVExportCharset') == 'UTF-8') ? gettext($string) : iconv('UTF-8', SystemConfig::getValue('sCSVExportCharset'), gettext($string));
    }

    public static function filterString($sInput)
    {
        // or use htmlspecialchars( stripslashes( ))
        return strip_tags(trim($sInput));
    }

    public static function filterHTML($sInput)
    {
        return strip_tags(trim($sInput), self::$AllowedHTMLTags);
    }

    public static function filterChar($sInput, $size = 1)
    {
        return mb_substr(trim($sInput), 0, $size);
    }

    public static function filterInt($sInput)
    {
        return (int) intval(trim($sInput));
    }

    public static function filterFloat($sInput)
    {
        return (float) floatval(trim($sInput));
    }

    public static function filterDate($sInput)
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
            }
        } else {
            return '';
        }
    }
}
