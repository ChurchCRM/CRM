<?php

namespace ChurchCRM\Service;

class DatabaseVersionService
{
    // First releases whose default table format indexes 3072 bytes. Older servers
    // stop with SQLSTATE 1071 part-way through an upgrade, so they must not start one.
    public const MIN_UPGRADE_MYSQL = '5.7.7';
    public const MIN_UPGRADE_MARIADB = '10.2.2';

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
     * An unreadable version counts as supported: only a version we can read blocks an upgrade.
     *
     * @return array{supported: bool, engine: string, version: string}
     */
    public static function check(?string $serverVersion): array
    {
        $parsed = self::parse($serverVersion);
        if ($parsed === null) {
            return ['supported' => true, 'engine' => '', 'version' => ''];
        }

        $minimum = $parsed['engine'] === 'MariaDB' ? self::MIN_UPGRADE_MARIADB : self::MIN_UPGRADE_MYSQL;

        return $parsed + ['supported' => version_compare($parsed['version'], $minimum, '>=')];
    }

    /**
     * The message that stops an upgrade on an unsupported database, or null when it may go on.
     */
    public static function getUpgradeBlockMessage(?string $serverVersion = null): ?string
    {
        $result = self::check($serverVersion ?? SystemService::getDBServerVersion());
        if ($result['supported']) {
            return null;
        }

        return sprintf(
            gettext('Your database server (%1$s %2$s) is too old to upgrade ChurchCRM. The upgrade was not started, so nothing changed. Ask your host to move the database to a current MySQL or MariaDB release, then try again.'),
            $result['engine'],
            $result['version']
        );
    }
}
