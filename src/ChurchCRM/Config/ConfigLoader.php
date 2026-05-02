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
            self::writeErrorLog("Config file not found or not readable: {$configPath}");
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

        // Suppress errors during require; we'll validate ourselves
        $error = error_get_last();
        require_once $configPath;
        $newError = error_get_last();
        if ($newError !== $error && $newError !== null) {
            self::writeErrorLog("Error loading Config.php: " . $newError['message']);
            throw new RuntimeException("Error loading Config.php: " . $newError['message']);
        }

        // Backward compatibility: legacy pages include Config.php directly, which sets
        // globals, then Config.php calls require_once LoadConfigs.php. When LoadConfigs.php
        // calls us, require_once above is a no-op (file already included), so local vars
        // remain null. Fall back to reading the globals that Config.php already set.
        if ($sSERVERNAME === null && isset($GLOBALS['sSERVERNAME'])) {
            $sSERVERNAME = $GLOBALS['sSERVERNAME'];
            $dbPort      = $GLOBALS['dbPort'] ?? null;
            $sUSER       = $GLOBALS['sUSER'] ?? null;
            $sPASSWORD   = $GLOBALS['sPASSWORD'] ?? null;
            $sDATABASE   = $GLOBALS['sDATABASE'] ?? null;
            $sRootPath   = $GLOBALS['sRootPath'] ?? null;
            $URL         = $GLOBALS['URL'] ?? [];
        }

        // Validate that required variables were set
        $missing = [];
        if ($sSERVERNAME === null) {
            $missing[] = '$sSERVERNAME (DB_SERVER_NAME)';
        }
        if ($dbPort === null) {
            $missing[] = '$dbPort (DB_SERVER_PORT)';
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
            self::writeErrorLog($errorMsg);
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
            self::validateUrl((string)$URL[0]);
        } catch (RuntimeException $e) {
            self::writeErrorLog($e->getMessage());
            throw $e;
        }

        return new ConfigDto(
            dbServerName: (string)$sSERVERNAME,
            dbServerPort: (string)$dbPort,
            dbName: (string)$sDATABASE,
            dbUser: (string)$sUSER,
            dbPassword: (string)$sPASSWORD,
            rootPath: (string)$sRootPath,
            url: (string)$URL[0],
        );
    }

    private static function writeErrorLog(string $message): void
    {
        $logPath = sys_get_temp_dir() . '/churchcrm-' . date('Y-m-d') . '-config-error.log';
        $timestamp = date('Y-m-d\TH:i:s.uP');
        $logEntry = "[{$timestamp}] CONFIG_ERROR: {$message}\n";
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
