<?php

namespace ChurchCRM\Config;

use RuntimeException;

/**
 * Loads ChurchCRM configuration from a JSON values file and validates every
 * field at read time.
 *
 * Security note (GHSA-mp2w-4q3r-ppx7 / CVE-2026-39337):
 * The setup wizard previously substituted user-supplied DB credentials into
 * a PHP template, which allowed quote-breaking payloads in DB_PASSWORD to
 * inject executable PHP. Configuration values are now stored in JSON and
 * loaded through this class, so user input cannot reach the PHP grammar.
 * Validation is enforced on read — the loader rejects anything that does
 * not match the expected shape regardless of how it was written to disk.
 */
final class ConfigLoader
{
    public const REQUIRED_KEYS = [
        'DB_SERVER_NAME',
        'DB_SERVER_PORT',
        'DB_NAME',
        'DB_USER',
        'DB_PASSWORD',
        'ROOT_PATH',
        'URL',
    ];

    /**
     * Load and validate config values from a JSON file.
     *
     * @return array<string, string> validated values keyed by REQUIRED_KEYS
     * @throws RuntimeException on any validation failure
     */
    public static function load(string $path): array
    {
        if (!is_file($path) || !is_readable($path)) {
            throw new RuntimeException("Config values file not found or not readable: {$path}");
        }

        $raw = file_get_contents($path);
        if ($raw === false) {
            throw new RuntimeException("Unable to read config values file: {$path}");
        }

        $data = json_decode($raw, true);
        if (!is_array($data)) {
            throw new RuntimeException("Config values file is not a valid JSON object: {$path}");
        }

        foreach (self::REQUIRED_KEYS as $key) {
            if (!array_key_exists($key, $data)) {
                throw new RuntimeException("Missing required config key: {$key}");
            }
            if (!is_string($data[$key])) {
                throw new RuntimeException("Config key must be a string: {$key}");
            }
        }

        self::validateHostname($data['DB_SERVER_NAME']);
        self::validatePort($data['DB_SERVER_PORT']);
        self::validateDbName($data['DB_NAME']);
        self::validateDbUser($data['DB_USER']);
        self::validateDbPassword($data['DB_PASSWORD']);
        self::validateRootPath($data['ROOT_PATH']);
        self::validateUrl($data['URL']);

        return [
            'DB_SERVER_NAME' => $data['DB_SERVER_NAME'],
            'DB_SERVER_PORT' => $data['DB_SERVER_PORT'],
            'DB_NAME'        => $data['DB_NAME'],
            'DB_USER'        => $data['DB_USER'],
            'DB_PASSWORD'    => $data['DB_PASSWORD'],
            'ROOT_PATH'      => $data['ROOT_PATH'],
            'URL'            => $data['URL'],
        ];
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
