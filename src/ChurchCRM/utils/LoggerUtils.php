<?php

namespace ChurchCRM\Utils;

use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;
use Monolog\Formatter\JsonFormatter;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Level;
use Monolog\Logger;
use Monolog\Processor\IntrospectionProcessor;
use Monolog\Processor\PsrLogMessageProcessor;

class LoggerUtils
{
    /** Number of daily log files to retain before the oldest is deleted. */
    private const LOG_RETENTION_DAYS = 3;

    private static ?Logger $appLogger = null;
    private static ?RotatingFileHandler $appLogHandler = null;
    private static ?Logger $cspLogger = null;
    private static ?Logger $authLogger = null;
    private static ?Logger $slimLogger = null;
    private static ?RotatingFileHandler $authLogHandler = null;
    private static ?string $correlationId = null;

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
     * Create a formatter based on the current log level.
     * A new instance is returned each time so that loggers initialised before
     * the database is ready (e.g. the app logger during bootstrap) re-evaluate
     * the level correctly when they are later rebuilt — avoids a stale cached
     * JSON formatter being used even when the system is in debug mode.
     *
     * Debug  → LineFormatter  (human-readable, one entry per line)
     * Other  → JsonFormatter  (one JSON object per line, compatible with ELK/Splunk/Datadog)
     */
    private static function createFormatter(): LineFormatter|JsonFormatter
    {
        if (self::isDebugLogLevel()) {
            $formatter = new LineFormatter(null, 'Y-m-d\TH:i:s.uP', false, true);
            $formatter->includeStacktraces(true);
            return $formatter;
        }

        // Monolog v3 JsonFormatter(batchMode, appendNewline, ignoreEmptyContextAndExtra, includeStacktraces)
        return new JsonFormatter(
            JsonFormatter::BATCH_MODE_NEWLINES,
            true,
            true,
            false
        );
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

    /**
     * Build a rotating log file base path for use with RotatingFileHandler.
     * The path MUST include the .log extension so that RotatingFileHandler's
     * getTimedFilename() preserves it, producing the standard YYYY-MM-DD-{type}.log
     * when combined with setFilenameFormat('{date}-{filename}', 'Y-m-d').
     */
    private static function buildRotatingLogBasePath(string $type): string
    {
        try {
            $docRoot = SystemURLs::getDocumentRoot();
            if ($docRoot && is_dir($docRoot . '/logs') && is_writable($docRoot . '/logs')) {
                return $docRoot . '/logs/' . $type . '.log';
            }
        } catch (\Exception $e) {
            // Config not initialized or logs directory not accessible
        }
        
        // Fallback to temp directory
        return sys_get_temp_dir() . '/churchcrm-' . $type . '.log';
    }

    public static function getSlimMVCLogger(): Logger
    {
        if (!self::$slimLogger instanceof Logger) {
            $slimLogger = new Logger('slim-app');
            
            // Use RotatingFileHandler for automatic daily rotation
            try {
                $handler = new RotatingFileHandler(self::buildRotatingLogBasePath('slim'), self::LOG_RETENTION_DAYS, self::getLogLevel()->value);
                $handler->setFilenameFormat('{date}-{filename}', 'Y-m-d');
                $handler->setFormatter(self::createFormatter());
                
                
                $slimLogger->pushHandler($handler);
            } catch (\Throwable $e) {
                // Fallback to error_log if file handler fails during initialization
                error_log('Failed to initialize Slim logger: ' . $e->getMessage());
            }
            
            // Add IntrospectionProcessor for automatic call context - use Emergency level to capture all levels
            $slimLogger->pushProcessor(new IntrospectionProcessor(Level::Emergency->value, ['ChurchCRM\\', 'Slim\\']));
            
            self::$slimLogger = $slimLogger;
        }

        return self::$slimLogger;
    }

    /**
     * @return Logger
     */
    public static function getAppLogger($level = null): ?Logger
    {
        if (!self::$appLogger instanceof Logger) {
            if ($level === null) {
                $level = self::getLogLevelValue();
            } elseif ($level instanceof Level) {
                $level = $level->value;
            }

            self::$appLogger = new Logger('defaultLogger');
            
            try {
                self::$appLogHandler = new RotatingFileHandler(self::buildRotatingLogBasePath('app'), self::LOG_RETENTION_DAYS, $level);
                self::$appLogHandler->setFilenameFormat('{date}-{filename}', 'Y-m-d');
                self::$appLogHandler->setFormatter(self::createFormatter());
                
                
                self::$appLogger->pushHandler(self::$appLogHandler);
            } catch (\Throwable $e) {
                // Fallback to error_log if file handler fails during initialization
                error_log('Failed to initialize app logger: ' . $e->getMessage());
            }
            
            self::$appLogger->pushProcessor(new PsrLogMessageProcessor());
            
            // Add IntrospectionProcessor for automatic call context - use Emergency level to capture all levels
            self::$appLogger->pushProcessor(new IntrospectionProcessor(Level::Emergency->value, ['ChurchCRM\\']));
            
            self::$appLogger->pushProcessor(function (\Monolog\LogRecord $record): \Monolog\LogRecord {
                return $record->with(extra: array_merge($record->extra, [
                    'url'            => $_SERVER['REQUEST_URI'] ?? '',
                    'remote_ip'      => $_SERVER['REMOTE_ADDR'] ?? '',
                    'correlation_id' => self::getCorrelationId(),
                ]));
            });
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

    /**
     * @return Logger
     */
    public static function getAuthLogger(): ?Logger
    {
        if (!self::$authLogger instanceof Logger) {
            self::$authLogger = new Logger('authLogger');
            
            try {
                // Use RotatingFileHandler for consistency with other loggers
                self::$authLogHandler = new RotatingFileHandler(self::buildRotatingLogBasePath('auth'), self::LOG_RETENTION_DAYS, self::getLogLevelValue());
                self::$authLogHandler->setFilenameFormat('{date}-{filename}', 'Y-m-d');
                
                // Use standard formatter to match other system logs
                self::$authLogHandler->setFormatter(self::createFormatter());
                
                
                self::$authLogger->pushHandler(self::$authLogHandler);
            } catch (\Throwable $e) {
                // Fallback to error_log if file handler fails during initialization
                error_log('Failed to initialize auth logger: ' . $e->getMessage());
            }
            
            // Add IntrospectionProcessor for automatic call context - use Emergency level to capture all levels
            self::$authLogger->pushProcessor(new IntrospectionProcessor(Level::Emergency->value, ['ChurchCRM\\']));
            
            self::$authLogger->pushProcessor(function (\Monolog\LogRecord $record): \Monolog\LogRecord {
                return $record->with(extra: array_merge($record->extra, [
                    'url'            => $_SERVER['REQUEST_URI'] ?? '',
                    'remote_ip'      => $_SERVER['REMOTE_ADDR'] ?? '',
                    'correlation_id' => self::getCorrelationId(),
                    'context'        => self::getCaller(),
                ]));
            });
        }

        return self::$authLogger;
    }

    public static function resetAppLoggerLevel(): void
    {
        // Called after DB initialisation so the real timezone and log level are now available.
        // The bootstrapper forces UTC (date_default_timezone_set('UTC')) before the loggers are
        // first created, but later reads sTimeZone from the DB and sets the correct local timezone.
        // Nulling the cached loggers here forces them to be recreated on next use with the correct
        // PHP default timezone, so that RotatingFileHandler names files and schedules rotation based
        // on local midnight rather than UTC midnight.
        self::$appLogger = null;
        self::$appLogHandler = null;
        self::$cspLogger = null;
        self::$authLogger = null;
        self::$slimLogger = null;
    }

    public static function getCSPLogger(): ?Logger
    {
        if (!self::$cspLogger instanceof Logger) {
            self::$cspLogger = new Logger('cspLogger');
            
            // Use RotatingFileHandler for automatic daily rotation and retention
            try {
                $handler = new RotatingFileHandler(self::buildRotatingLogBasePath('csp'), self::LOG_RETENTION_DAYS, self::getLogLevel()->value);
                $handler->setFilenameFormat('{date}-{filename}', 'Y-m-d');
                $handler->setFormatter(self::createFormatter());
                
                
                self::$cspLogger->pushHandler($handler);
            } catch (\Throwable $e) {
                // Fallback to error_log if file handler fails during initialization
                error_log('Failed to initialize CSP logger: ' . $e->getMessage());
            }
            
            // Add IntrospectionProcessor for automatic call context - use Emergency level to capture all levels
            self::$cspLogger->pushProcessor(new IntrospectionProcessor(Level::Emergency->value, ['ChurchCRM\\']));
        }

        return self::$cspLogger;
    }
}
