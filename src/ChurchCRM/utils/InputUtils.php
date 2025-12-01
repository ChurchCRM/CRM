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

    /**
     * Sanitize plain text by removing all HTML tags
     * Use this for non-HTML values like names, descriptions that should never contain markup
     * 
     * @param string $sInput Input text
     * @return string Plain text with HTML tags removed
     */
    public static function sanitizeText($sInput): string
    {
        return strip_tags(trim($sInput));
    }

    /**
     * Sanitize plain text and prepare for safe HTML display
     * Removes HTML tags, whitespace, and escapes remaining special characters
     * Best for: User-submitted form data that should be plain text
     * 
     * @param string $sInput Input text to sanitize and escape
     * @return string Safe plain text for HTML output
     */
    public static function sanitizeAndEscapeText($sInput): string
    {
        return htmlspecialchars(strip_tags(trim($sInput)), ENT_QUOTES, 'UTF-8');
    }

    /**
     * Sanitize rich text HTML with XSS protection using HTML Purifier
     * Use this for user-provided HTML content (e.g., event descriptions from Quill editor)
     * 
     * @param string $sInput HTML input to sanitize
     * @return string Clean HTML with dangerous tags/attributes removed
     */
    public static function sanitizeHTML($sInput): string
    {
        $sInput = trim($sInput);
        
        if (empty($sInput)) {
            return '';
        }
        
        // Configure HTML Purifier with strict XSS protection
        $config = \HTMLPurifier_Config::createDefault();
        
        // Define allowed HTML tags for safe content (rich text)
        $config->set('HTML.Allowed', 
            'a[href],b,i,u,h1,h2,h3,h4,h5,h6,pre,address,img[src|alt|width|height],table,td,tr,ol,li,ul,p,sub,sup,s,hr,span,blockquote,div,small,big,tt,code,kbd,samp,del,ins,cite,q,br,strong,em'
        );
        
        // Block dangerous protocols: only allow safe URLs
        $config->set('URI.AllowedSchemes', ['http' => true, 'https' => true, 'ftp' => true, 'mailto' => true]);
        
        // Disable dangerous elements that could bypass sanitization
        $config->set('HTML.ForbiddenElements', ['script', 'iframe', 'embed', 'object', 'form', 'style', 'meta']);
        
        // Disable automatic paragraph wrapping
        $config->set('AutoFormat.AutoParagraph', false);
        
        // Enable ID attributes for accessibility
        $config->set('Attr.EnableID', true);
        
        $purifier = new \HTMLPurifier($config);
        
        return $purifier->purify($sInput);
    }

    /**
     * Escape HTML for safe display in HTML context (body content and attributes)
     * Converts special characters to HTML entities: &, <, >, ", '
     * Automatically handles stripslashes() for magic quotes compatibility
     * Use this when outputting user/database values in HTML
     * 
     * @param string $sInput Text to escape
     * @return string HTML-escaped text safe for display
     */
    public static function escapeHTML($sInput): string
    {
        return htmlspecialchars(stripslashes($sInput), ENT_QUOTES, 'UTF-8');
    }

    /**
     * Escape HTML for safe use in HTML attributes
     * Alias for escapeHTML() - both use ENT_QUOTES for full safety
     * Automatically handles stripslashes() for magic quotes compatibility
     * Use this when outputting user/database values in HTML attributes
     * 
     * @param string $sInput Text to escape
     * @return string HTML-escaped text safe for attribute use
     */
    public static function escapeAttribute($sInput): string
    {
        return htmlspecialchars(stripslashes($sInput), ENT_QUOTES, 'UTF-8');
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
                    return mysqli_real_escape_string($cnInfoCentral, self::sanitizeText($sInput));
                case 'htmltext':
                    return mysqli_real_escape_string($cnInfoCentral, self::sanitizeHTML($sInput));
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
