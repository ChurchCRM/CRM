<?php

namespace ChurchCRM\Utils;

use ChurchCRM\Utils\LoggerUtils;
use ChurchCRM\dto\SystemURLs;
use Composer\InstalledVersions;
use Propel\Runtime\Propel;

class VersionUtils
{
    private const COMPOSER_NAME = 'churchcrm/crm';
    private static ?string $cachedVersion = null;

    public static function getInstalledVersion(): string
    {
        // Return cached version if already fetched in this request
        if (self::$cachedVersion !== null) {
            return self::$cachedVersion;
        }

        $version = InstalledVersions::getPrettyVersion(self::COMPOSER_NAME);
        if ($version) {
            self::$cachedVersion = $version;
            return $version;
        }

        LoggerUtils::getAppLogger()->info('could not determine version from composer autoloader, falling back to legacy composer.json parsing');
        $composerFile = file_get_contents(SystemURLs::getDocumentRoot() . '/composer.json');
        $composerJson = json_decode($composerFile, true, 512, JSON_THROW_ON_ERROR);

        self::$cachedVersion = $composerJson['version'];
        return self::$cachedVersion;
    }

    public static function getDBVersion()
    {
        $connection = Propel::getConnection();
        $query = 'select * from version_ver order by ver_id desc limit 1';
        $statement = $connection->prepare($query);
        $statement->execute();
        $results = $statement->fetchAll(\PDO::FETCH_ASSOC);
        rsort($results);

        return $results[0]['ver_version'];
    }

    /**
     * Return the required PHP version configured in composer.json `config.platform.php`.
     *
     * @param string|null $composerFile Optional path to composer.json
     * @return string
     * @throws \RuntimeException if composer.json cannot be read or config.platform.php is missing
     */
    public static function getRequiredPhpVersion(?string $composerFile = null): string
    {
        if ($composerFile === null) {
            $composerFile = __DIR__ . '/../../composer.json';
        }

        if (!file_exists($composerFile)) {
            throw new \RuntimeException(
                "Cannot determine PHP system state: composer.json not found at {$composerFile}. "
                . "Unable to read required PHP version configuration."
            );
        }

        $json = @file_get_contents($composerFile);
        if ($json === false) {
            throw new \RuntimeException(
                "Cannot determine PHP system state: Unable to read composer.json at {$composerFile}. "
                . "Check file permissions or disk integrity."
            );
        }

        $composer = json_decode($json, true);
        if (!is_array($composer)) {
            throw new \RuntimeException(
                "Cannot determine PHP system state: composer.json at {$composerFile} is not valid JSON. "
                . "System configuration is corrupted."
            );
        }

        if (empty($composer['config']['platform']['php'])) {
            throw new \RuntimeException(
                "Cannot determine PHP system state: composer.json at {$composerFile} does not contain "
                . "'config.platform.php' configuration. System setup is incomplete or corrupted."
            );
        }

        $req = (string)$composer['config']['platform']['php'];
        // Normalize short form like "8.2" -> "8.2.0"
        if (preg_match('/^\d+\.\d+$/', $req)) {
            $req .= '.0';
        }

        return $req;
    }
}
