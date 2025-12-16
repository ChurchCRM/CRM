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
    
    // Known application subdirectories used for root path detection
    private const KNOWN_SUBDIRS = ['src', 'api', 'v2', 'admin', 'finance', 'setup', 'kiosk', 'session'];

    public static function init($rootPath, $urls, $documentRoot): void
    {
        // Avoid consecutive slashes when $sRootPath = '/'
        if ($rootPath === '/') {
            $rootPath = '';
        }
        
        // Auto-detect and validate root path for subdirectory installations
        $detectedRootPath = self::detectRootPath();
        
        // If configured root path doesn't match detected path, log a warning
        if ($detectedRootPath !== null && $rootPath !== $detectedRootPath) {
            error_log(sprintf(
                '[ChurchCRM] WARNING: Configured root path "%s" does not match detected root path "%s". ' .
                'This may cause issues with API endpoints and assets. ' .
                'Please update $sRootPath in Include/Config.php to: "%s"',
                $rootPath,
                $detectedRootPath,
                $detectedRootPath
            ));
            
            // Auto-correct to detected path to prevent broken functionality
            // This allows the app to work even with misconfiguration
            $rootPath = $detectedRootPath;
        }
        
        self::$rootPath = $rootPath;
        self::$urls = $urls;
        self::$documentRoot = $documentRoot;
        self::$CSPNonce = base64_encode(random_bytes(16));
    }
    
    /**
     * Auto-detect the root path from server variables
     * Returns the detected root path or null if detection fails
     * 
     * For example:
     * - SCRIPT_NAME = /churchcrm/api/index.php -> returns /churchcrm
     * - SCRIPT_NAME = /api/index.php -> returns ''
     * - SCRIPT_NAME = /ChurchCRMxyz/PersonView.php -> returns /ChurchCRMxyz
     * - SCRIPT_NAME = /PersonView.php -> returns '' (root install)
     */
    private static function detectRootPath(): ?string
    {
        // Build regex pattern from known subdirectories
        $subdirPattern = '(' . implode('|', self::KNOWN_SUBDIRS) . ')';
        
        // Try detection from SCRIPT_NAME first
        $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
        if ($scriptName !== '') {
            $scriptName = str_replace('\\', '/', $scriptName);
            
            // Remove filename to get directory
            $scriptDir = dirname($scriptName);
            
            // If script is at root level (SCRIPT_NAME = /file.php), scriptDir will be '/'
            // This means root installation with empty root path
            if ($scriptDir === '/' || $scriptDir === '.') {
                return '';
            }
            
            // Check if we're in a known subdirectory (src, api, v2, etc.)
            // and extract the parent path
            if (preg_match("#^(.*?)/{$subdirPattern}(/|$)#", $scriptDir, $matches)) {
                $rootPath = $matches[1];
                if ($rootPath === '') {
                    return '';
                }
                return $rootPath;
            }
            
            // If not in a known subdirectory, assume we're in a top-level subdirectory
            // For example: /churchcrm/PersonView.php -> /churchcrm
            if ($scriptDir !== '/' && $scriptDir !== '.') {
                // Check if script is directly in a subdirectory (not nested)
                $parts = explode('/', trim($scriptDir, '/'));
                if (count($parts) === 1) {
                    return '/' . $parts[0];
                }
            }
        }
        
        // Fallback: try REQUEST_URI
        $requestUri = $_SERVER['REQUEST_URI'] ?? '';
        if ($requestUri !== '') {
            $requestPath = parse_url($requestUri, PHP_URL_PATH) ?? '';
            if ($requestPath !== '') {
                // Look for known patterns in REQUEST_URI
                if (preg_match("#^(.*?)/{$subdirPattern}/#", $requestPath, $matches)) {
                    $rootPath = $matches[1];
                    if ($rootPath === '') {
                        return '';
                    }
                    return $rootPath;
                }
            }
        }
        
        // Detection failed - return null to indicate we should use configured value
        return null;
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
