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
            throw new RuntimeException("Error loading Config.php: " . $newError['message']);
        }

        // Validate that required variables were set
        if ($sSERVERNAME === null || $dbPort === null || $sUSER === null ||
            $sPASSWORD === null || $sDATABASE === null || $sRootPath === null ||
            !isset($URL[0])) {
            throw new RuntimeException("Config.php did not set required variables");
        }

        // Validate each value
        self::validateHostname((string)$sSERVERNAME);
        self::validatePort((string)$dbPort);
        self::validateDbName((string)$sDATABASE);
        self::validateDbUser((string)$sUSER);
        self::validateDbPassword((string)$sPASSWORD);
        self::validateRootPath((string)$sRootPath);
        self::validateUrl((string)$URL[0]);

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
