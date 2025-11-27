<?php

/**
 * Created by PhpStorm.
 * User: georg
 * Date: 11/25/2017
 * Time: 1:28 PM.
 */

namespace ChurchCRM\Service;

use ChurchCRM\dto\SystemURLs;
use ChurchCRM\model\ChurchCRM\Version;
use ChurchCRM\SQLUtils;
use ChurchCRM\Utils\LoggerUtils;
use ChurchCRM\Utils\MiscUtils;
use ChurchCRM\Utils\VersionUtils;
use Propel\Runtime\Propel;

class UpgradeService
{
    public static function upgradeDatabaseVersion(): bool
    {
        $logger = LoggerUtils::getAppLogger();
        $db_version = VersionUtils::getDBVersion();
        $installed_version = VersionUtils::getInstalledVersion();

        $logger->info(
            "Current Version: $db_version, Installed Version: $installed_version",
            [
                'dbVersion'                => $db_version,
                'softwareInstalledVersion' => $installed_version,
            ]
        );
        if ($db_version === $installed_version) {
            $logger->info('Database is already at current version, no upgrade needed');
            return true;
        }

        //the database isn't at the current version.  Start the upgrade
        try {
            $connection = Propel::getConnection();

            $dbUpdatesFile = file_get_contents(SystemURLs::getDocumentRoot() . '/mysql/upgrade.json');
            MiscUtils::throwIfFailed($dbUpdatesFile);

            $dbUpdates = json_decode($dbUpdatesFile, true, 512, JSON_THROW_ON_ERROR);

            $errorFlag = false;
            $upgradeScriptsExecuted = 0;
            foreach ($dbUpdates as $dbUpdate) {
                try {
                    if (in_array(VersionUtils::getDBVersion(), $dbUpdate['versions'])) {
                        $version = new Version();
                        $version->setVersion($dbUpdate['dbVersion']);
                        $version->setUpdateStart(new \DateTimeImmutable());

                        $logger->info('New Version: ' . $version->getVersion());
                        $scriptName = null;
                        foreach ($dbUpdate['scripts'] as $dbScript) {
                            $scriptName = SystemURLs::getDocumentRoot() . $dbScript;

                            $logger->info('Upgrade DB - ' . $scriptName);
                            if (pathinfo($scriptName, PATHINFO_EXTENSION) === 'sql') {
                                SQLUtils::sqlImport($scriptName, $connection);
                            } elseif (pathinfo($scriptName, PATHINFO_EXTENSION) === 'php') {
                                require_once $scriptName;
                            } else {
                                throw new \Exception("Invalid upgrade file specified: $scriptName");
                            }
                        }
                        $version->setUpdateEnd(new \DateTimeImmutable());
                        $version->save();
                        sleep(2);

                        // increment the number of scripts executed.
                        // If no scripts run, then there is no supported upgrade path defined in the JSON file
                        $upgradeScriptsExecuted++;
                    }
                } catch (\Exception $exc) {
                    $logger->error(
                        'Failure executing upgrade script(s): ' . $exc->getMessage(),
                        [
                            'exception'                 => $exc,
                            'scriptName'                => $scriptName,
                            'version'                   => $version->getVersion(),
                            'numUpgradeScriptsExecuted' => $upgradeScriptsExecuted,
                        ]
                    );

                    throw $exc;
                }
            }

            if ($upgradeScriptsExecuted === 0) {
                $logger->warning('No upgrade path for ' . VersionUtils::getDBVersion() . ' to ' . $installed_version);
            }
            // always rebuild the views
            SQLUtils::sqlImport(SystemURLs::getDocumentRoot() . '/mysql/upgrade/rebuild_views.sql', $connection);

            // Mark session to avoid immediate redirect loop while bootstrapper re-reads DB version
            if (session_status() !== PHP_SESSION_ACTIVE) {
                @session_start();
            }
            try {
                $_SESSION['dbUpgradeJustRan'] = true;
            } catch (\Exception $e) {
                // ignore session write failures - not critical
            }

            return true;
        } catch (\Exception $exc) {
            $logger->error(
                'Database upgrade failed: ' . $exc->getMessage(),
                ['exception' => $exc]
            );

            throw $exc; //allow the method requesting the upgrade to handle this failure also.
        }
    }
}
