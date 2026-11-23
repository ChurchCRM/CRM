<?php

namespace ChurchCRM\Service;

class DatabaseVersionService
{
    // The supported floor, kept in step with supported-databases.json by
    // scripts/ci/sync-supported-databases.js. Below it ChurchCRM runs with a warning.
    public const MIN_MYSQL = '8.4';
    public const MIN_MARIADB = '10.11';

    // The hard minimum. An older server can stop an upgrade part-way through
    // (SQLSTATE 1071), so it neither installs nor upgrades.
    public const MYSQL_HARD_MINIMUM = '8.0';
    public const MARIADB_HARD_MINIMUM = '10.6';

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
     * An unreadable version counts as supported: only a version we can read warns or blocks.
     *
     * @return array{supported: bool, installable: bool, engine: string, version: string}
     */
    public static function check(?string $serverVersion): array
    {
        $parsed = self::parse($serverVersion);
        if ($parsed === null) {
            return ['supported' => true, 'installable' => true, 'engine' => '', 'version' => ''];
        }

        $isMariaDb = $parsed['engine'] === 'MariaDB';

        return $parsed + [
            'supported'   => version_compare($parsed['version'], $isMariaDb ? self::MIN_MARIADB : self::MIN_MYSQL, '>='),
            'installable' => version_compare($parsed['version'], $isMariaDb ? self::MARIADB_HARD_MINIMUM : self::MYSQL_HARD_MINIMUM, '>='),
        ];
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
        if (!$result['installable']) {
            return self::tooOldMessage($result);
        }

        return sprintf(
            gettext('Your database (%1$s %2$s) is out of support and might be unsafe. ChurchCRM will run, but we strongly recommend a host with MariaDB %3$s+ or MySQL %4$s+.'),
            $result['engine'],
            $result['version'],
            self::MIN_MARIADB,
            self::MIN_MYSQL
        );
    }

    /**
     * Why ChurchCRM cannot install on or upgrade this database, or null when it can.
     */
    public static function getBlockMessage(?string $serverVersion = null): ?string
    {
        $result = self::check($serverVersion ?? SystemService::getDBServerVersion());

        return $result['installable'] ? null : self::tooOldMessage($result);
    }

    /**
     * The message that stops an upgrade on a database below the hard minimum, or null when it may go on.
     */
    public static function getUpgradeBlockMessage(?string $serverVersion = null): ?string
    {
        $block = self::getBlockMessage($serverVersion);

        return $block === null ? null : $block . ' '
            . gettext('The upgrade was not started, so nothing changed.') . ' '
            . gettext('Ask your host to move the database to a supported version, then try again.');
    }

    /**
     * The warning for an upgrade that runs below the supported floor.
     */
    public static function getUpgradeWarningMessage(?string $serverVersion = null): ?string
    {
        return self::getUnsupportedMessage($serverVersion);
    }

    /**
     * @param array{engine: string, version: string} $result
     */
    private static function tooOldMessage(array $result): string
    {
        return sprintf(
            gettext('Your database server (%1$s %2$s) is too old for ChurchCRM, which needs MySQL %3$s or MariaDB %4$s and later.'),
            $result['engine'],
            $result['version'],
            self::MYSQL_HARD_MINIMUM,
            self::MARIADB_HARD_MINIMUM
        );
    }
}
