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
        try {
          $dbUpdatesFile = file_get_contents(SystemURLs::getDocumentRoot() . '/mysql/upgrade.json');
          $dbUpdates = json_decode($dbUpdatesFile, true);
          $errorFlag = false;
          $connection = Propel::getConnection();
          $upgradeScriptsExecuted = 0;
          foreach ($dbUpdates as $dbUpdate) {
            try {
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
                      } elseif (pathinfo($scriptName, PATHINFO_EXTENSION) == "php") {
                          require_once ($scriptName);
                      }
                      else {
                        throw new \Exception(gettext("Invalid upgrade file specified").": " . $scriptName);
                      }
                  }
                  if (!$errorFlag) {
                      $version->setUpdateEnd(new \DateTime());
                      $version->save();
                      sleep(2);
                  }
                // increment the number of scripts executed.
                // If no scripts run, then there is no supported upgrade path defined in the JSON file
                $upgradeScriptsExecuted ++;
              }
            }
            catch (\Exception $exc) {
              $logger->error(gettext("Failure executing upgrade script").": ".$scriptName.": ".$exc->getMessage());
              throw $exc;
            }
          }
          if( $upgradeScriptsExecuted === 0 ) {
            $logger->warning("No upgrade path for " . SystemService::getDBVersion() . " to " . $_SESSION['sSoftwareInstalledVersion']);
          }
          // always rebuild the views
          SQLUtils::sqlImport(SystemURLs::getDocumentRoot() . '/mysql/upgrade/rebuild_views.sql', $connection);

          return true;
        }
        catch (\Exception $exc){
           $logger->error(gettext("Database upgrade failed").": " . $exc->getMessage());
           throw $exc; //allow the method requesting the upgrade to handle this failure also.
        }
    }

}
