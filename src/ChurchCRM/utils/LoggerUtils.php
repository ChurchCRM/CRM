<?php

namespace ChurchCRM\Utils;

use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;

class LoggerUtils
{
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
        $logger = new Logger('defaultLogger');
        $logger->pushHandler(new StreamHandler(self::buildLogFilePath("app"), self::getLogLevel()));
        return $logger;
    }

    public static function getCSPLogger()
    {
        $logger = new Logger('cspLogger');
        $logger->pushHandler(new StreamHandler(self::buildLogFilePath("csp"), self::getLogLevel()));
        return $logger;
    }

}