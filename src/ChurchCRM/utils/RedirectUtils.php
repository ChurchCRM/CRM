<?php

namespace ChurchCRM\Utils;

use ChurchCRM\dto\SystemURLs;

class RedirectUtils
{
    /**
     * Convert a relative URL into an absolute URL and redirect the browser there.
     *
     * @throws \Exception
     */
    public static function redirect(string $sRelativeURL): void
    {
        if (substr($sRelativeURL, 0, 1) != '/') {
            $sRelativeURL = '/' . $sRelativeURL;
        }
        if (substr($sRelativeURL, 0, strlen(SystemURLs::getRootPath())) != SystemURLs::getRootPath()) {
            $finalLocation = SystemURLs::getRootPath() . $sRelativeURL;
        } else {
            $finalLocation = $sRelativeURL;
        }
        header('Location: ' . $finalLocation);
        exit;
    }

    public static function absoluteRedirect(string $sTargetURL): void
    {
        header('Location: ' . $sTargetURL);
        exit;
    }

    public static function securityRedirect(string $missingRole): void
    {
        LoggerUtils::getAppLogger()->warning('Security Redirect Request due to Role: ' . $missingRole);
        self::redirect('v2/access-denied?role=' . urlencode($missingRole));
    }

    /**
     * Gets and validates the 'linkBack' parameter from the request.
     *
     * This is a convenience method that combines:
     * - Getting the linkBack parameter from $_GET
     * - Applying InputUtils filtering
     * - Validating for open redirect attacks
     * - Returning a fallback if empty/invalid
     *
     * @param string $fallback The fallback URL if validation fails (default: 'v2/dashboard')
     *
     * @return string A safe URL for redirection
     */
    public static function getLinkBackFromRequest(string $fallback = 'v2/dashboard'): string
    {
        $linkBack = InputUtils::legacyFilterInputArr($_GET, 'linkBack') ?? '';

        return self::validateRedirectUrl($linkBack, $fallback);
    }

    /**
     * Validates and sanitizes a redirect URL to prevent open redirect attacks.
     *
     * This method ensures the URL is safe for redirection by:
     * - Rejecting URLs with protocol schemes (http://, https://, javascript:, etc.)
     * - Rejecting protocol-relative URLs (//example.com)
     * - Rejecting URLs with encoded characters that could bypass validation
     * - Rejecting URLs containing line breaks or null bytes
     * - Returning a fallback URL if the input is invalid
     *
     * @param string $url The URL to validate
     * @param string $fallback The fallback URL if validation fails (default: 'v2/dashboard')
     *
     * @return string A safe URL for redirection
     */
    public static function validateRedirectUrl(string $url, string $fallback = 'v2/dashboard'): string
    {
        // Trim whitespace
        $url = trim($url);

        // Empty URL - return fallback
        if ($url === '') {
            return $fallback;
        }

        // Check for null bytes or line breaks (HTTP response splitting)
        if (preg_match('/[\x00\r\n]/', $url)) {
            LoggerUtils::getAppLogger()->warning('Rejected redirect URL containing control characters', ['url' => $url]);

            return $fallback;
        }

        // Decode URL to catch encoded attacks
        $decodedUrl = urldecode($url);

        // Check for protocol schemes (case-insensitive)
        // This catches http://, https://, javascript:, data:, vbscript:, etc.
        if (preg_match('/^[a-zA-Z][a-zA-Z0-9+.-]*:/i', $decodedUrl)) {
            LoggerUtils::getAppLogger()->warning('Rejected redirect URL with protocol scheme', ['url' => $url]);

            return $fallback;
        }

        // Check for protocol-relative URLs (//example.com)
        if (preg_match('#^//#', $decodedUrl)) {
            LoggerUtils::getAppLogger()->warning('Rejected protocol-relative redirect URL', ['url' => $url]);

            return $fallback;
        }

        // Check for backslash-based URLs that could be interpreted as protocol-relative
        // Some browsers interpret \\ the same as //
        if (preg_match('#^\\\\#', $decodedUrl)) {
            LoggerUtils::getAppLogger()->warning('Rejected backslash redirect URL', ['url' => $url]);

            return $fallback;
        }

        // URL is safe - return the original (not decoded) URL
        return $url;
    }

    /**
     * Escapes a validated redirect URL for safe output in HTML/JavaScript.
     *
     * This method validates the URL and then escapes it for output.
     * Use this when outputting URLs in onclick handlers or similar contexts.
     *
     * @param string $url The URL to validate and escape
     * @param string $fallback The fallback URL if validation fails
     *
     * @return string An escaped, safe URL for HTML/JavaScript output
     */
    public static function escapeRedirectUrl(string $url, string $fallback = 'v2/dashboard'): string
    {
        $safeUrl = self::validateRedirectUrl($url, $fallback);

        return InputUtils::escapeAttribute($safeUrl);
    }
}
