<?php

namespace ChurchCRM\Utils;

use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;

class LoggerUtils
{
    private static ?\Monolog\Logger $appLogger = null;
    private static ?\Monolog\Handler\StreamHandler $appLogHandler = null;
    private static ?\Monolog\Logger $cspLogger = null;
    private static ?\Monolog\Logger $authLogger = null;
    private static ?\Monolog\Logger $slimLogger = null;
    private static ?\Monolog\Handler\StreamHandler $authLogHandler = null;
    private static ?string $correlationId = null;

    public static function getCorrelationId()
    {
        if (empty(self::$correlationId)) {
            self::$correlationId = uniqid();
        }

        return self::$correlationId;
    }

    public static function getLogLevel()
    {
        return intval(SystemConfig::getValue('sLogLevel'));
    }

    public static function buildLogFilePath($type)
    {
        return SystemURLs::getDocumentRoot().'/logs/'.date('Y-m-d').'-'.$type.'.log';
    }

    /**
     * @return Logger
     */
    public static function getSlimMVCLogger(): Logger
    {
        if (self::$slimLogger === null) {
            $slimLogger = new Logger('slim-app');
            $streamHandler = new StreamHandler(self::buildLogFilePath('slim'), SystemConfig::getValue('sLogLevel'));
            $slimLogger->pushHandler($streamHandler);
            self::$slimLogger = $slimLogger;
        }

        return self::$slimLogger;
    }

    /**
     * @return Logger
     */
    public static function getAppLogger($level = null)
    {
        if (self::$appLogger === null) {
            // if $level is null
            // (meaning this function was invoked without explicitly setting the level),
            //  then get the level from the database
            if ($level === null) {
                $level = self::getLogLevel();
            }
            self::$appLogger = new Logger('defaultLogger');
            //hold a reference to the handler object so that resetAppLoggerLevel can be called later on
            self::$appLogHandler = new StreamHandler(self::buildLogFilePath('app'), $level);
            self::$appLogger->pushHandler(self::$appLogHandler);
            self::$appLogger->pushProcessor(function ($entry) {
                $entry['extra']['url'] = $_SERVER['REQUEST_URI'];
                $entry['extra']['remote_ip'] = $_SERVER['REMOTE_ADDR'];
                $entry['extra']['correlation_id'] = self::getCorrelationId();

                return $entry;
            });
        }

        return self::$appLogger;
    }

    private static function getCaller()
    {
        $callers = debug_backtrace();
        $call = [];
        if ($callers[5]) {
            $call = $callers[5];
        }

        return [
            'ContextClass'  => array_key_exists('class', $call) ? $call['class'] : '',
            'ContextMethod' => $call['function'],
        ];
    }

    /**
     * @return Logger
     */
    public static function getAuthLogger($level = null)
    {
        if (self::$authLogger === null) {
            // if $level is null
            // (meaning this function was invoked without explicitly setting the level),
            //  then get the level from the database
            if ($level === null) {
                $level = self::getLogLevel();
            }
            self::$authLogger = new Logger('authLogger');
            //hold a reference to the handler object so that resetAppLoggerLevel can be called later on
            self::$authLogHandler = new StreamHandler(self::buildLogFilePath('auth'), $level);
            self::$authLogger->pushHandler(self::$authLogHandler);
            self::$authLogger->pushProcessor(function ($entry) {
                $entry['extra']['url'] = $_SERVER['REQUEST_URI'];
                $entry['extra']['remote_ip'] = $_SERVER['REMOTE_ADDR'];
                $entry['extra']['correlation_id'] = self::getCorrelationId();
                $entry['extra']['context'] = self::getCaller();

                return $entry;
            });
        }

        return self::$authLogger;
    }

    public static function resetAppLoggerLevel()
    {
        // if the app log handler was initialized (in the boostrapper) to a specific level
        // before the database initialization occurred
        // we provide a function to reset the app logger to what's defined in the database.
        self::$appLogHandler->setLevel(self::getLogLevel());
    }

    public static function getCSPLogger()
    {
        if (self::$cspLogger === null) {
            self::$cspLogger = new Logger('cspLogger');
            self::$cspLogger->pushHandler(new StreamHandler(self::buildLogFilePath('csp'), self::getLogLevel()));
        }

        return self::$cspLogger;
    }
}
