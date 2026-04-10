<?php

/**
 * Created by PhpStorm.
 * User: dawoudio
 * Date: 11/27/2016
 * Time: 9:33 AM.
 */

namespace ChurchCRM\dto;

use ChurchCRM\Utils\RedirectUtils;

class SystemURLs
{
    private static $rootPath;
    private static $urls;
    private static $documentRoot;
    private static ?string $CSPNonce = null;

    public static function init($rootPath, $urls, $documentRoot): void
    {
        // Avoid consecutive slashes when $sRootPath = '/'
        if ($rootPath === '/') {
            $rootPath = '';
        }
        self::$rootPath = $rootPath;
        self::$urls = $urls;
        self::$documentRoot = $documentRoot;
        self::$CSPNonce = base64_encode(random_bytes(16));
    }

    public static function getRootPath()
    {
        if (self::isValidRootPath()) {
            return self::$rootPath;
        }

        throw new \Exception("Please check the value for '\$sRootPath' in <b>`Include\\Config.php`</b>, the following is not valid [" . self::$rootPath . ']');
    }

    public static function getDocumentRoot()
    {
        return self::$documentRoot;
    }

    public static function getImagesRoot(): string
    {
        return self::$documentRoot . '/Images';
    }

    public static function getURLs()
    {
        return self::$urls;
    }

    public static function getSupportURL($topic = ''): string
    {
        $supportURLs = [
            // User-facing guidance moved to the Documentation site
            'HttpsTask'                     => 'https://docs.churchcrm.io/installation/ssl-https',
            'CheckExecutionTimeTask'        => 'https://docs.churchcrm.io/installation/system-requirements#php-max-execution-time',
            'SecretsConfigurationCheckTask' => 'https://docs.churchcrm.io/administration/secret-keys',
            'UnsupportedPaymentDataCheck'   => 'https://docs.churchcrm.io/user-guide/finances',
            'UnsupportedDepositCheck'       => 'https://docs.churchcrm.io/user-guide/finances',
            // File upload guidance lives in system requirements
            'CheckUploadSizeTask'           => 'https://docs.churchcrm.io/installation/system-requirements#file-uploads',
        ];

        if (array_key_exists($topic, $supportURLs)) {
            return $supportURLs[$topic];
        }

        // Default to the public user documentation site
        return 'https://docs.churchcrm.io';
    }

    public static function getURL($index = 0)
    {
        // Return the URL configured for this server from Include/Config.php
        // Trim any trailing slashes from the configured URL
        $URL = self::$urls[$index];
        if (substr($URL, -1, 1) === '/') {
            return substr($URL, 0, -1);
        }

        return $URL;
    }

    private static function isValidRootPath(): bool
    {
        //if (stripos(self::$rootPath, "http") !== true ) {
        //    return false;
        //}
        return true;
    }

    // check if bLockURL is set and if so if the current page is accessed via an allowed URL
    // including the desired protocol, hostname, and path.
    // An array of authorized URL's is specified in Config.php in the $URL array
    public static function checkAllowedURL($bLockURL, array $URL): void
    {
        if (isset($bLockURL) && ($bLockURL === true)) {
            // get the URL of this page
            $currentURL = 'http' . (isset($_SERVER['HTTPS']) ? 's' : '') . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];

            // chop off the query string
            $currentURL = explode('?', $currentURL)[0];

            // check if this matches any one of the whitelisted login URLS
            $validURL = false;
            foreach ($URL as $value) {
                $base = substr($value, 0, -strlen('/'));
                if (strpos($currentURL, (string) $value) === 0) {
                    $validURL = true;
                    break;
                }
            }

            if (!$validURL) {
                RedirectUtils::absoluteRedirect($URL[0]);
            }
        }
    }

    public static function getCSPNonce(): ?string
    {
        return self::$CSPNonce;
    }

    /**
     * Get versioned asset URL with file modification time for cache-busting.
     * Appends ?v=<filemtime> to asset URLs to force browser cache refresh after deploys.
     *
     * @param string $webPath Asset path relative to document root (e.g., '/skin/v2/churchcrm.min.css')
     * @return string Versioned URL with modification time, or original URL if file doesn't exist
     */
    public static function assetVersioned(string $webPath): string
    {
        $rootUrl = self::getRootPath();
        $docRoot = self::getDocumentRoot();
        $fullPath = rtrim($docRoot, DIRECTORY_SEPARATOR) . $webPath;
        if (file_exists($fullPath)) {
            return $rootUrl . $webPath . '?v=' . filemtime($fullPath);
        }
        return $rootUrl . $webPath;
    }
}
