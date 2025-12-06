<?php

namespace ChurchCRM\Utils;

/**
 * Utility class for validating URLs used in ChurchCRM configuration.
 * Ensures URLs meet strict requirements for use in Config.php and system redirects.
 */
class URLValidator
{
    /**
     * Validate a URL for use in ChurchCRM configuration.
     * 
     * URL must:
     * - Be a valid URL format
     * - Use http or https scheme
     * - End with a trailing slash
     * - Have a valid hostname or IP address
     *
     * @param string $value The URL to validate
     * @return bool True if valid, false otherwise
     */
    public static function isValidConfigURL(string $value): bool
    {
        // First check: basic URL format validation
        if (!filter_var($value, FILTER_VALIDATE_URL)) {
            return false;
        }

        // Second check: must use http or https
        if (!preg_match('#^https?://#i', $value)) {
            return false;
        }

        // Third check: must end with trailing slash
        if (!preg_match('#/$#', $value)) {
            return false;
        }

        // Fourth check: parse and validate components
        $parsed = parse_url($value);
        if ($parsed === false || !isset($parsed['host'])) {
            return false;
        }

        // Fifth check: host should be a valid hostname or IP address
        $host = $parsed['host'];
        if (!preg_match('/^([a-zA-Z0-9]([a-zA-Z0-9\-]{0,61}[a-zA-Z0-9])?\.)*[a-zA-Z0-9]([a-zA-Z0-9\-]{0,61}[a-zA-Z0-9])?$/', $host) &&
            !filter_var($host, FILTER_VALIDATE_IP)) {
            return false;
        }

        return true;
    }

    /**
     * Get validation error details for a URL.
     * Returns an array with specific error information for display.
     *
     * @param string $value The URL to validate
     * @return array|null Array with 'code' and 'message' if invalid, null if valid
     */
    public static function getValidationError(string $value): ?array
    {
        // Check: basic URL format
        if (!filter_var($value, FILTER_VALIDATE_URL)) {
            return [
                'code' => 'invalid_format',
                'message' => gettext('URL is not in valid format'),
            ];
        }

        // Check: http or https scheme
        if (!preg_match('#^https?://#i', $value)) {
            return [
                'code' => 'invalid_scheme',
                'message' => gettext('URL must start with http:// or https://'),
            ];
        }

        // Check: trailing slash
        if (!preg_match('#/$#', $value)) {
            return [
                'code' => 'missing_trailing_slash',
                'message' => gettext('URL must end with a trailing slash (/)'),
            ];
        }

        // Check: valid hostname
        $parsed = parse_url($value);
        if ($parsed === false || !isset($parsed['host'])) {
            return [
                'code' => 'invalid_host',
                'message' => gettext('URL does not contain a valid hostname'),
            ];
        }

        $host = $parsed['host'];
        if (!preg_match('/^([a-zA-Z0-9]([a-zA-Z0-9\-]{0,61}[a-zA-Z0-9])?\.)*[a-zA-Z0-9]([a-zA-Z0-9\-]{0,61}[a-zA-Z0-9])?$/', $host) &&
            !filter_var($host, FILTER_VALIDATE_IP)) {
            return [
                'code' => 'invalid_hostname',
                'message' => gettext('URL hostname is not valid'),
            ];
        }

        return null;
    }
}
