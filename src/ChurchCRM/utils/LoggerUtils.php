<?php

namespace ChurchCRM\Utils;

use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;
use Monolog\Formatter\JsonFormatter;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Level;
use Monolog\Logger;
use Monolog\LogRecord;
use Monolog\Processor\IntrospectionProcessor;
use Monolog\Processor\PsrLogMessageProcessor;

class LoggerUtils
{
    private static ?Logger $appLogger = null;
    private static ?RotatingFileHandler $appLogHandler = null;
    private static ?Logger $cspLogger = null;
    private static ?Logger $authLogger = null;
    private static ?Logger $slimLogger = null;
    private static ?RotatingFileHandler $authLogHandler = null;
    private static ?string $correlationId = null;
    private static LineFormatter|JsonFormatter|null $formatter = null;

    public static function getCorrelationId(): ?string
    {
        if (empty(self::$correlationId)) {
            self::$correlationId = uniqid();
        }

        return self::$correlationId;
    }

    public static function getLogLevel(): Level
    {
        try {
            $level = SystemConfig::getValue('sLogLevel');
            return Level::tryFrom(intval($level)) ?? Level::Info;
        } catch (\Exception $e) {
            // Config not initialized (e.g., during setup) - use INFO level
            return Level::Info;
        }
    }

    public static function isDebugLogLevel(): bool
    {
        return self::getLogLevel() === Level::Debug;
    }

    /**
     * Get the log level as an integer value (for backward compatibility)
     * @return int The numeric log level value
     */
    public static function getLogLevelValue(): int
    {
        return self::getLogLevel()->value;
    }

    /**
     * Create a formatter based on system configuration
     * Uses JsonFormatter for production/structured logging, LineFormatter for development
     */
    private static function createFormatter(): LineFormatter|JsonFormatter
    {
        if (self::$formatter === null) {
            // In production, use JSON for better log viewer compatibility (ELK, Splunk, Datadog)
            // In development, use text format for readability
            $useJson = SystemConfig::getValue('sLogLevel') == Level::Debug->value ? false : true;
            
            if ($useJson) {
                // JsonFormatter with optimized settings for structured logging
                self::$formatter = new JsonFormatter(
                    JsonFormatter::BATCH_MODE_JSON,     // Batch mode for log aggregators
                    false,                               // No pretty printing for compact output
                    true,                                // Append newline for log viewer compatibility
                    true                                 // Ignore empty context/extra
                );
                // Use unescaped output for better readability in log viewers
                self::$formatter->setJsonEncodeOptions(JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            } else {
                // Plain text format for development with full context
                self::$formatter = new LineFormatter(null, null, false, true);
                
                try {
                    // Set explicit date format with timezone offset
                    // Monolog uses PHP's timezone set by date_default_timezone_set() in Bootstrapper
                    self::$formatter->setDateFormat('Y-m-d\TH:i:s.uP');
                } catch (\Exception $e) {
                    // Config not initialized - will use default format
                }
            }
        }
        
        return self::$formatter;
    }

    public static function buildLogFilePath(string $type): string
    {
        try {
            $docRoot = SystemURLs::getDocumentRoot();
            if ($docRoot && is_dir($docRoot . '/logs') && is_writable($docRoot . '/logs')) {
                return $docRoot . '/logs/' . date('Y-m-d') . '-' . $type . '.log';
            }
        } catch (\Exception $e) {
            // Config not initialized or logs directory not accessible
        }
        
        // Fallback to temp directory
        return sys_get_temp_dir() . '/churchcrm-' . date('Y-m-d') . '-' . $type . '.log';
    }

    public static function getSlimMVCLogger(): Logger
    {
        if (!self::$slimLogger instanceof Logger) {
            $slimLogger = new Logger('slim-app');
            
            // Use RotatingFileHandler for automatic daily rotation
            $baseLogPath = SystemURLs::getDocumentRoot() . '/logs/slim';
            $handler = new RotatingFileHandler($baseLogPath . '.log', 30, self::getLogLevel()->value);
            $handler->setFormatter(self::createFormatter());
            
            // Add error callback for graceful failure handling
            $handler->setOnFailureCallback(function (\Throwable $error) {
                error_log('Slim logger handler failed: ' . $error->getMessage());
            });
            
            $slimLogger->pushHandler($handler);
            
            // Add IntrospectionProcessor for automatic call context
            $slimLogger->pushProcessor(new IntrospectionProcessor(Level::Debug, ['ChurchCRM\\', 'Slim\\']));
            
            self::$slimLogger = $slimLogger;
        }

        return self::$slimLogger;
    }

    public static function getAppLogger($level = null): ?Logger
    {
        if (!self::$appLogger instanceof Logger) {
            if ($level === null) {
                $level = self::getLogLevel()->value;
            } elseif ($level instanceof Level) {
                $level = $level->value;
            }

            self::$appLogger = new Logger('defaultLogger');
            
            // Use RotatingFileHandler for automatic daily rotation and retention
            $baseLogPath = SystemURLs::getDocumentRoot() . '/logs/app';
            $handler = new RotatingFileHandler($baseLogPath . '.log', 30, $level);
            $handler->setFormatter(self::createFormatter());
            
            // Add error callback for graceful failure handling
            $handler->setOnFailureCallback(function (\Throwable $error) {
                error_log('App logger handler failed: ' . $error->getMessage());
            });
            
            self::$appLogHandler = $handler;
            self::$appLogger->pushHandler(self::$appLogHandler);
            
            // Add IntrospectionProcessor for automatic call context (file, class, method, line)
            self::$appLogger->pushProcessor(new IntrospectionProcessor(Level::Debug, ['ChurchCRM\\']));
            
            // Add context processor to capture raw request data
            self::$appLogger->pushProcessor(function (LogRecord $record): LogRecord {
                $record->extra['url'] = $_SERVER['REQUEST_URI'] ?? $_SERVER['REQUEST_PATH'] ?? 'CLI';
                $record->extra['remote_ip'] = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
                $record->extra['correlation_id'] = self::getCorrelationId();
                return $record;
            });
            
            // Then add PSR-3 message processor for parameter interpolation
            self::$appLogger->pushProcessor(new PsrLogMessageProcessor());
        }

        return self::$appLogger;
    }

    private static function getCaller(): array
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

    public static function getAuthLogger(): ?Logger
    {
        if (!self::$authLogger instanceof Logger) {
            self::$authLogger = new Logger('authLogger');
            
            // Use RotatingFileHandler for automatic daily rotation and retention
            $baseLogPath = SystemURLs::getDocumentRoot() . '/logs/auth';
            $handler = new RotatingFileHandler($baseLogPath . '.log', 30, self::getLogLevel()->value);
            $handler->setFormatter(self::createFormatter());
            
            // Add error callback for graceful failure handling
            $handler->setOnFailureCallback(function (\Throwable $error) {
                error_log('Auth logger handler failed: ' . $error->getMessage());
            });
            
            self::$authLogHandler = $handler;
            self::$authLogger->pushHandler(self::$authLogHandler);
            
            // Add IntrospectionProcessor for automatic call context
            self::$authLogger->pushProcessor(new IntrospectionProcessor(Level::Debug, ['ChurchCRM\\']));
            
            // Add context processor to capture raw request data
            self::$authLogger->pushProcessor(function (LogRecord $record): LogRecord {
                $record->extra['url'] = $_SERVER['REQUEST_URI'] ?? $_SERVER['REQUEST_PATH'] ?? 'CLI';
                $record->extra['remote_ip'] = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
                $record->extra['correlation_id'] = self::getCorrelationId();
                $record->extra['context'] = self::getCaller();
                return $record;
            });
            
            // Add PSR-3 message processor
            self::$authLogger->pushProcessor(new PsrLogMessageProcessor());
        }

        return self::$authLogger;
    }

    public static function resetAppLoggerLevel(): void
    {
        // If the app log handler was initialized (in the bootstrapper) to a specific level
        // before the database initialization occurred,
        // we provide a function to reset the app logger to what's defined in the database.
        self::$appLogHandler->setLevel(self::getLogLevel()->value);
    }

    public static function getCSPLogger(): ?Logger
    {
        if (!self::$cspLogger instanceof Logger) {
            self::$cspLogger = new Logger('cspLogger');
            
            // Use RotatingFileHandler for automatic daily rotation and retention
            $baseLogPath = SystemURLs::getDocumentRoot() . '/logs/csp';
            $handler = new RotatingFileHandler($baseLogPath . '.log', 30, self::getLogLevel()->value);
            $handler->setFormatter(self::createFormatter());
            
            // Add error callback for graceful failure handling
            $handler->setOnFailureCallback(function (\Throwable $error) {
                error_log('CSP logger handler failed: ' . $error->getMessage());
            });
            
            self::$cspLogger->pushHandler($handler);
            
            // Add IntrospectionProcessor for automatic call context
            self::$cspLogger->pushProcessor(new IntrospectionProcessor(Level::Debug, ['ChurchCRM\\']));
        }

        return self::$cspLogger;
    }
}
