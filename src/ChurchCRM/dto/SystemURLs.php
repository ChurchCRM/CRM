<?php
/**
 * Created by PhpStorm.
 * User: dawoudio
 * Date: 11/27/2016
 * Time: 9:33 AM
 */

namespace ChurchCRM\dto;


class SystemURLs
{
    private static $rootPath;
    private static $urls;
    private static $documentRoot;

    public static function init($rootPath, $urls, $documentRoot)
    {
        // Avoid consecutive slashes when $sRootPath = '/'
        if ($rootPath == "/") {
            $rootPath = "";
        }
        self::$rootPath = $rootPath;
        self::$urls = $urls;
        self::$documentRoot = $documentRoot;
    }

    public static function getRootPath()
    {
        //if (self::isValidRootPath()) {
            return self::$rootPath;
        //}
        //throw new \Exception("Please check the value for '\$sRootPath' in <b>`Include\\Config.php`</b>, the following is not valid [". self::$rootPath . "]");
    }

    public static function getDocumentRoot()
    {
        return self::$documentRoot;
    }

    public static function getURLs()
    {
        return self::$urls;
    }

    public static function getURL($index)
    {
        return self::$urls[$index];
    }

    private static function isValidRootPath()
    {
        if (stripos(self::$rootPath, "http") !== true ) {
            return false;
        }
        return true;

    }
}