<?php

namespace ChurchCRM\Utils;

use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;

class LoggerUtils
{
    private static $appLogger;
    private static $cspLogger; 
    public static function getLogLevel()
    {
        return intval(SystemConfig::getValue("sLogLevel"));
    }

    public static function buildLogFilePath($type)
    {
        return $logFilePrefix = SystemURLs::getDocumentRoot() . '/logs/' . date("Y-m-d") . '-' . $type . '.log';
    }

    /**
     * @return Logger
     */
    public static function getAppLogger()
    {
      if (is_null(self::$appLogger)){
        self::$appLogger = new Logger('defaultLogger');
        self::$appLogger->pushHandler(new StreamHandler(self::buildLogFilePath("app"), self::getLogLevel()));
      }
      return self::$appLogger;
    }

    public static function getCSPLogger()
    {
      if (is_null(self::$cspLogger)){
        self::$cspLogger = new Logger('cspLogger');
        self::$cspLogger->pushHandler(new StreamHandler(self::buildLogFilePath("csp"), self::getLogLevel()));
      }
      return self::$cspLogger;
    }

}