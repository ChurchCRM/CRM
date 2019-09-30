<?php

namespace ChurchCRM\Utils;

use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;

class LoggerUtils
{
    private static $appLogger;
    private static $appLogHandler;
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
    public static function getAppLogger($level=null)
    {
      if (is_null(self::$appLogger)){
        // if $level is null 
        // (meaning this function was invoked without explicitly setting the level),
        //  then get the level from the database
        if (is_null($level)) {
          $level = self::getLogLevel();
        }
        self::$appLogger = new Logger('defaultLogger');
        //hold a reference to the handler object so that ResetAppLoggerLevel can be called later on
        self::$appLogHandler = new StreamHandler(self::buildLogFilePath("app"), $level);
        self::$appLogger->pushHandler(self::$appLogHandler);
      }
      return self::$appLogger;
    }
    
    /**
     * @return Logger
     */
    public static function getAuthLogger($level=null)
    {
      if (is_null(self::$authLogger)){
        // if $level is null 
        // (meaning this function was invoked without explicitly setting the level),
        //  then get the level from the database
        if (is_null($level)) {
          $level = self::getLogLevel();
        }
        self::$authLogger = new Logger('authLogger');
        //hold a reference to the handler object so that ResetAppLoggerLevel can be called later on
        self::$authLogHandler = new StreamHandler(self::buildLogFilePath("auth"), $level);
        self::$authLogger->pushHandler(self::$authLogHandler);
        self::$authLogger->pushProcessor(function ($entry) {
          $entry['extra']['url'] = $_SERVER['REQUEST_URI'];
          $entry['extra']['remote_ip'] = $_SERVER['REMOTE_ADDR'];
          $entry['extra']['correlation_id'] = AuthenticationManager::GetCorrelationId();
          return $entry;
        });
      }
      return self::$authLogger;
    }
    
    public static function ResetAppLoggerLevel() {
      // if the app log hander was initialized (in the boostrapper) to a specific level
      // before the database initialization occurred
      // we provide a function to reset the app logger to what's defined in the databse.
      self::$appLogHandler->setLevel(self::getLogLevel());
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