<?php
/**
 * Created by PhpStorm.
 * User: georg
 * Date: 11/25/2017
 * Time: 1:28 PM
 */

namespace ChurchCRM\Service;

use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Version;
use ChurchCRM\Utils\LoggerUtils;
use ChurchCRM\SQLUtils;
use Propel\Runtime\Propel;

class UpgradeService
{
    static public function upgradeDatabaseVersion()
    {
        $logger = LoggerUtils::getAppLogger();
        $db_version = SystemService::getDBVersion();
        $logger->info("Current Version: " .$db_version);
        if ($db_version == $_SESSION['sSoftwareInstalledVersion']) {
            return true;
        }

        //the database isn't at the current version.  Start the upgrade
        $dbUpdatesFile = file_get_contents(SystemURLs::getDocumentRoot() . '/mysql/upgrade.json');
        $dbUpdates = json_decode($dbUpdatesFile, true);
        $errorFlag = false;
        $connection = Propel::getConnection();
        foreach ($dbUpdates as $dbUpdate) {
            if (in_array(SystemService::getDBVersion(), $dbUpdate['versions'])) {
                $version = new Version();
                $version->setVersion($dbUpdate['dbVersion']);
                $version->setUpdateStart(new \DateTime());
                $logger->info("New Version: " .$version->getVersion());
                foreach ($dbUpdate['scripts'] as $dbScript) {
                    $scriptName = SystemURLs::getDocumentRoot() . $dbScript;
                    $logger->info("Upgrade DB - " . $scriptName);
                    if (pathinfo($scriptName, PATHINFO_EXTENSION) == "sql") {
                        SQLUtils::sqlImport($scriptName, $connection);
                    } else {
                        require_once ($scriptName);
                    }
                }
                if (!$errorFlag) {
                    $version->setUpdateEnd(new \DateTime());
                    $version->save();
                }
            }
        }
        // always rebuild the menu
        SQLUtils::sqlImport(SystemURLs::getDocumentRoot() . '/mysql/upgrade/rebuild_nav_menus.sql', $connection);

        return true;
    }

}
