<?php

namespace ChurchCRM\Service;

class DatabaseVersionService
{
    // The documented minimums, kept in step with supported-databases.json by
    // scripts/ci/sync-supported-databases.js. An older server can stop an upgrade
    // part-way through (SQLSTATE 1071), so the wizard does not start one.
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

        $minimum = $parsed['engine'] === 'MariaDB' ? self::MIN_MARIADB : self::MIN_MYSQL;

        return $parsed + ['supported' => version_compare($parsed['version'], $minimum, '>=')];
    }

    /**
     * What is wrong with the database server, or null when it is supported.
     */
    public static function getUnsupportedMessage(?string $serverVersion = null): ?string
    {
        $result = self::check($serverVersion ?? SystemService::getDBServerVersion());
        if ($result['supported']) {
            return null;
        }

        return sprintf(
            gettext('Your database server (%1$s %2$s) is older than ChurchCRM supports (MySQL %3$s or MariaDB %4$s and later).'),
            $result['engine'],
            $result['version'],
            self::MIN_MYSQL,
            self::MIN_MARIADB
        );
    }

    /**
     * The message that stops an upgrade on an unsupported database, or null when it may go on.
     */
    public static function getUpgradeBlockMessage(?string $serverVersion = null): ?string
    {
        $unsupported = self::getUnsupportedMessage($serverVersion);

        return $unsupported === null ? null : $unsupported . ' '
            . gettext('The upgrade was not started, so nothing changed.') . ' '
            . gettext('Ask your host to move the database to a supported version, then try again.');
    }

    /**
     * The warning for an upgrade that runs anyway, such as after a manual file upload.
     */
    public static function getUpgradeWarningMessage(?string $serverVersion = null): ?string
    {
        $unsupported = self::getUnsupportedMessage($serverVersion);

        return $unsupported === null ? null : $unsupported . ' '
            . gettext('Ask your host to move the database to a supported version.');
    }
}
