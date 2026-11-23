<?php

namespace ChurchCRM\Service;

class DatabaseVersionService
{
    // The oldest versions their vendors still patch, kept in step with supported-databases.json by
    // scripts/ci/sync-supported-databases.js. Below them ChurchCRM runs with a security warning.
    public const MIN_MYSQL = '8.4';
    public const MIN_MARIADB = '10.11';

    /**
     * @return array{engine: string, version: string}|null null when the server did not report a version
     */
    public static function parse(?string $serverVersion): ?array
    {
        if ($serverVersion === null || !preg_match('/\d+\.\d+(\.\d+)?/', $serverVersion)) {
            return null;
        }

        if (stripos($serverVersion, 'mariadb') !== false) {
            // MariaDB prefixes its version with "5.5.5-" over some connections
            $serverVersion = preg_replace('/^5\.5\.5-/', '', $serverVersion);
            preg_match('/\d+\.\d+(\.\d+)?/', $serverVersion, $found);

            return ['engine' => 'MariaDB', 'version' => $found[0]];
        }

        preg_match('/\d+\.\d+(\.\d+)?/', $serverVersion, $found);

        return ['engine' => 'MySQL', 'version' => $found[0]];
    }

    /**
     * An unreadable version counts as supported: only a version we can read warns.
     *
     * @return array{supported: bool, engine: string, version: string}
     */
    public static function check(?string $serverVersion): array
    {
        $parsed = self::parse($serverVersion);
        if ($parsed === null) {
            return ['supported' => true, 'engine' => '', 'version' => ''];
        }

        return $parsed + [
            'supported' => version_compare($parsed['version'], $parsed['engine'] === 'MariaDB' ? self::MIN_MARIADB : self::MIN_MYSQL, '>='),
        ];
    }

    /**
     * The security warning for a database its vendor no longer patches, or null when it is supported.
     */
    public static function getSecurityRiskMessage(?string $serverVersion = null): ?string
    {
        $result = self::check($serverVersion ?? SystemService::getDBServerVersion());
        if ($result['supported']) {
            return null;
        }

        return sprintf(
            gettext('Your database (%1$s %2$s) is a security risk: its vendor no longer ships security fixes for that version. ChurchCRM keeps working, but ask your host to move to MariaDB %3$s+ or MySQL %4$s+.'),
            $result['engine'],
            $result['version'],
            self::MIN_MARIADB,
            self::MIN_MYSQL
        );
    }
}
