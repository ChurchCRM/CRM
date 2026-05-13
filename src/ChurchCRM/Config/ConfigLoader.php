<?php

namespace ChurchCRM\Config;

use RuntimeException;

/**
 * Loads and validates ChurchCRM configuration.
 *
 * Security note (GHSA-mp2w-4q3r-ppx7 / CVE-2026-39337):
 * The setup wizard previously substituted user-supplied DB credentials into
 * a PHP template, which allowed quote-breaking payloads in DB_PASSWORD to
 * inject executable PHP. This class validates configuration values at read time,
 * rejecting anything that does not match the expected shape.
 */
final class ConfigLoader
{

    /**
     * Load and validate configuration from the existing templated Config.php file.
     * Extracts the global variables that Config.php sets and validates them.
     *
     * @return ConfigDto validated configuration as a DTO
     * @throws RuntimeException on any validation failure
     */
    public static function loadFromConfigPhp(string $configPath): ConfigDto
    {
        if (!is_file($configPath) || !is_readable($configPath)) {
            self::writeLog('ERROR', "Config file not found or not readable: {$configPath}");
            throw new RuntimeException("Config file not found or not readable: {$configPath}");
        }

        // Load the Config.php file in an isolated scope to capture globals
        $sSERVERNAME = null;
        $dbPort = null;
        $sUSER = null;
        $sPASSWORD = null;
        $sDATABASE = null;
        $sRootPath = null;
        $URL = [];

        // Use require (not require_once) so this method always executes Config.php in its
        // own local scope, regardless of whether the caller already included the file.
        // Legacy pages do: require Config.php → Config.php sets globals → Config.php calls
        // require_once LoadConfigs.php → LoadConfigs.php calls us. Using require_once here
        // would be a no-op (file already registered), leaving all local vars null. Using
        // plain require re-executes the file in this method's scope. Config.php's own
        // "require_once LoadConfigs.php" at its end is then a no-op (LoadConfigs already
        // loaded), preventing infinite recursion.
        $error = error_get_last();
        require $configPath;
        $newError = error_get_last();
        if ($newError !== $error && $newError !== null) {
            self::writeLog('ERROR', "Error loading Config.php: " . $newError['message']);
            throw new RuntimeException("Error loading Config.php: " . $newError['message']);
        }

        // Handle blank or missing dbPort: default to 3306 (standard MySQL port)
        if ($dbPort === null || $dbPort === '') {
            $dbPort = '3306';
            self::writeLog('DEBUG', 'DB_SERVER_PORT not set or blank; defaulting to 3306');
        }

        // Validate that required variables were set
        $missing = [];
        if ($sSERVERNAME === null) {
            $missing[] = '$sSERVERNAME (DB_SERVER_NAME)';
        }
        if ($sUSER === null) {
            $missing[] = '$sUSER (DB_USER)';
        }
        if ($sPASSWORD === null) {
            $missing[] = '$sPASSWORD (DB_PASSWORD)';
        }
        if ($sDATABASE === null) {
            $missing[] = '$sDATABASE (DB_NAME)';
        }
        if ($sRootPath === null) {
            $missing[] = '$sRootPath (ROOT_PATH)';
        }
        if (!isset($URL[0])) {
            $missing[] = '$URL[0] (PRIMARY_URL)';
        }

        if (!empty($missing)) {
            $errorMsg = 'Config.php missing required variables: ' . implode(', ', $missing);
            self::writeLog('ERROR', $errorMsg);
            throw new RuntimeException($errorMsg);
        }

        // Validate each value
        try {
            self::validateHostname((string)$sSERVERNAME);
            self::validatePort((string)$dbPort);
            self::validateDbName((string)$sDATABASE);
            self::validateDbUser((string)$sUSER);
            self::validateDbPassword((string)$sPASSWORD);
            self::validateRootPath((string)$sRootPath);
            foreach ($URL as $i => $urlValue) {
                self::validateUrl((string)$urlValue);
            }
        } catch (RuntimeException $e) {
            self::writeLog('ERROR', $e->getMessage());
            throw $e;
        }

        return new ConfigDto(
            dbServerName: (string)$sSERVERNAME,
            dbServerPort: (string)$dbPort,
            dbName: (string)$sDATABASE,
            dbUser: (string)$sUSER,
            dbPassword: (string)$sPASSWORD,
            rootPath: (string)$sRootPath,
            urls: array_map('strval', $URL),
        );
    }

    private static function writeLog(string $level, string $message): void
    {
        $logPath = sys_get_temp_dir() . '/churchcrm-' . date('Y-m-d') . '-config-error.log';
        $timestamp = date('Y-m-d\TH:i:s.uP');
        $logEntry = "[{$timestamp}] CONFIG_{$level}: {$message}\n";
        @file_put_contents($logPath, $logEntry, FILE_APPEND);
    }

    private static function validateHostname(string $value): void
    {
        // RFC 1123 hostname — letters, digits, dash, dot; no @ or :
        if (!preg_match('/^(?=.{1,253}$)([a-zA-Z0-9\-]{1,63}\.)*[a-zA-Z0-9\-]{1,63}$/', $value)) {
            throw new RuntimeException('Invalid DB_SERVER_NAME');
        }
    }

    private static function validatePort(string $value): void
    {
        if (!preg_match('/^[0-9]{1,5}$/', $value)) {
            throw new RuntimeException('Invalid DB_SERVER_PORT');
        }
        $port = (int) $value;
        if ($port < 1 || $port > 65535) {
            throw new RuntimeException('Invalid DB_SERVER_PORT');
        }
    }

    private static function validateDbName(string $value): void
    {
        if (!preg_match('/^[a-zA-Z0-9_\-\.]+$/', $value)) {
            throw new RuntimeException('Invalid DB_NAME');
        }
    }

    private static function validateDbUser(string $value): void
    {
        if (!preg_match('/^[a-zA-Z0-9_\-\.@]+$/', $value)) {
            throw new RuntimeException('Invalid DB_USER');
        }
    }

    private static function validateDbPassword(string $value): void
    {
        // Passwords are opaque and may contain arbitrary bytes. The JSON
        // storage layer prevents grammar injection, so the only invariant
        // enforced at read time is non-empty.
        if ($value === '') {
            throw new RuntimeException('Invalid DB_PASSWORD');
        }
    }

    private static function validateRootPath(string $value): void
    {
        // Empty, or starts with "/" — letters, digits, underscore, dash, dot, slash only.
        if (!preg_match('#^(|\/[a-zA-Z0-9_\-\.\/]*)$#', $value)) {
            throw new RuntimeException('Invalid ROOT_PATH');
        }
    }

    private static function validateUrl(string $value): void
    {
        if (!filter_var($value, FILTER_VALIDATE_URL) || !preg_match('#^https?://[^\s]+/$#i', $value)) {
            throw new RuntimeException('Invalid URL');
        }
    }
}
