<?php

/**
 * Created by PhpStorm.
 * User: dawoudio
 * Date: 11/27/2016
 * Time: 9:33 AM.
 */

namespace ChurchCRM\dto;

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
            'HttpsTask'                     => 'https://github.com/ChurchCRM/CRM/wiki/SSL',
            'CheckExecutionTimeTask'        => 'https://github.com/ChurchCRM/CRM/wiki/PHP-Max-Execution-Time',
            'SecretsConfigurationCheckTask' => 'https://github.com/ChurchCRM/CRM/wiki/Secret-Keys-in-Config.php',
            'UnsupportedPaymentDataCheck'   => 'https://github.com/ChurchCRM/CRM/wiki/Finances',
            'UnsupportedDepositCheck'       => 'https://github.com/ChurchCRM/CRM/wiki/Finances',
            'CheckUploadSizeTask'           => 'https://mediatemple.net/community/products/dv/204404784/how-do-i-increase-the-php-upload-limits',
        ];

        if (array_key_exists($topic, $supportURLs)) {
            return $supportURLs[$topic];
        } else {
            return 'https://github.com/ChurchCRM/CRM/wiki';
        }
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

            // jump to the first whitelisted url (TODO: maybe pick a ranodm URL?)
            if (!$validURL) {
                header('Location: ' . $URL[0]);
                exit;
            }
        }
    }

    public static function getCSPNonce(): ?string
    {
        return self::$CSPNonce;
    }
}
