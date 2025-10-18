<?php

namespace ChurchCRM\Utils;

use ChurchCRM\Utils\LoggerUtils;
use ChurchCRM\dto\SystemURLs;
use Composer\InstalledVersions;
use Propel\Runtime\Propel;

class VersionUtils
{
    private const COMPOSER_NAME = 'churchcrm/crm';

    public static function getInstalledVersion()
    {
        $version = InstalledVersions::getPrettyVersion(self::COMPOSER_NAME);
        if ($version) {
            return $version;
        }

        LoggerUtils::getAppLogger()->info('could not determine version from composer autoloader, falling back to legacy composer.json parsing');
        $composerFile = file_get_contents(SystemURLs::getDocumentRoot() . '/composer.json');
        $composerJson = json_decode($composerFile, true, 512, JSON_THROW_ON_ERROR);

        return $composerJson['version'];
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
}
