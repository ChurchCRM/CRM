<?php

namespace ChurchCRM\Service;

use ChurchCRM\Backup\BackupJob;
use ChurchCRM\Backup\BackupType;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Utils\ChurchCRMReleaseManager;
use ChurchCRM\Utils\InputUtils;
use ChurchCRM\Utils\LoggerUtils;
use PDO;
use Propel\Runtime\Propel;

require SystemURLs::getDocumentRoot() . '/vendor/ifsnop/mysqldump-php/src/Ifsnop/Mysqldump/Mysqldump.php';


class SystemService
{


    static public function getInstalledVersion()
    {
        $composerFile = file_get_contents(SystemURLs::getDocumentRoot() . '/composer.json');
        $composerJson = json_decode($composerFile, true);
        $version = $composerJson['version'];

        return $version;
    }

    static public function getCopyrightDate()
    {
        return (new \DateTime())->format("Y");
    }

    public function getConfigurationSetting($settingName, $settingValue)
    {
        requireUserGroupMembership('bAdmin');
    }

    public function setConfigurationSetting($settingName, $settingValue)
    {
        requireUserGroupMembership('bAdmin');
    }

   static public function getDBVersion()
    {
        $connection = Propel::getConnection();
        $query = 'Select * from version_ver order by ver_update_end desc limit 1';
        $statement = $connection->prepare($query);
        $statement->execute();
        $results = $statement->fetchAll(\PDO::FETCH_ASSOC);
        rsort($results);
        return $results[0]['ver_version'];
    }

    public static function getDBServerVersion()
    {
      try{
        return Propel::getServiceContainer()->getConnection()->getAttribute(PDO::ATTR_SERVER_VERSION);
      }
      catch (\Exception $exc)
      {
        return "Could not obtain DB Server Version";
      }
    }

    static public function getPrerequisiteStatus() {
      if (AppIntegrityService::arePrerequisitesMet())
      {
        return "All Prerequisites met";
      }
      else
      {
        $unmet = AppIntegrityService::getUnmetPrerequisites();
        $unmetNames = array_map(function($o) {
            return (string)($o->GetName());
          }, $unmet);
        return "Missing Prerequisites: ".json_encode(array_values($unmetNames));
      }
    }

    public function reportIssue($data)
    {
        $serviceURL = 'http://demo.churchcrm.io/issues/';
        $headers = [];
        $headers[] = 'Content-type: application/json';

        $issueDescription = $data->issueDescription . "\r\n\r\n\r\n" .
            "Collected Value Title |  Data \r\n" .
            "----------------------|----------------\r\n" .
            'Page Name |' . $data->pageName . "\r\n" .
            'Screen Size |' . $data->screenSize->height . 'x' . $data->screenSize->width . "\r\n" .
            'Window Size |' . $data->windowSize->height . 'x' . $data->windowSize->width . "\r\n" .
            'Page Size |' . $data->pageSize->height . 'x' . $data->pageSize->width . "\r\n" .
            'Platform Information | ' . php_uname($mode = 'a') . "\r\n" .
            'PHP Version | ' . phpversion() . "\r\n" .
            'SQL Version | ' . self::getDBServerVersion() . "\r\n" .
            'ChurchCRM Version |' . $_SESSION['sSoftwareInstalledVersion'] . "\r\n" .
            'Reporting Browser |' . $_SERVER['HTTP_USER_AGENT'] . "\r\n".
            'Prerequisite Status |' . self::getPrerequisiteStatus()."\r\n".
            'Integrity check status |' . file_get_contents(SystemURLs::getDocumentRoot() . '/integrityCheck.json')."\r\n";

        if (function_exists('apache_get_modules')) {
            $issueDescription .= 'Apache Modules    |' . implode(',', apache_get_modules());
        }

        $postdata = new \stdClass();
        $postdata->issueTitle = InputUtils::LegacyFilterInput($data->issueTitle);
        $postdata->issueDescription = $issueDescription;

        $curlService = curl_init($serviceURL);

        curl_setopt($curlService, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($curlService, CURLOPT_POST, true);
        curl_setopt($curlService, CURLOPT_POSTFIELDS, json_encode($postdata));
        curl_setopt($curlService, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curlService, CURLOPT_CONNECTTIMEOUT, 1);

        $result = curl_exec($curlService);
        if ($result === false) {
            throw new \Exception('Unable to reach the issue bridge', 500);
        }

        return $result;
    }

    private static function IsTimerThresholdExceeded(string $LastTime, int $ThresholdHours)
    {
      if (empty($LastTime)) {
        return true;
      }
      $tz = new \DateTimeZone(SystemConfig::getValue('sTimeZone'));
      $now = new \DateTime("now", $tz);  //get the current time
      $previous = \DateTime::createFromFormat(SystemConfig::getValue('sDateFilenameFormat'),$LastTime, $tz); // get a DateTime object for the last time a backup was done.
      if ($previous === false)
      {
        return true;
      }
      $diff = abs($now->getTimestamp() - $previous->getTimestamp()) / 60 / 60 ;
      return $diff >= $ThresholdHours;
    }

    public static function runTimerJobs()
    {
      LoggerUtils::getAppLogger()->debug("Starting background job processing");
        //start the external backup timer job
        if (SystemConfig::getBooleanValue('bEnableExternalBackupTarget') && SystemConfig::getValue('sExternalBackupAutoInterval') > 0) {  //if remote backups are enabled, and the interval is greater than zero
          try {
            if (self::IsTimerThresholdExceeded(SystemConfig::getValue('sLastBackupTimeStamp'), SystemConfig::getValue('sExternalBackupAutoInterval'))) {
              // if there was no previous backup, or if the interval suggests we do a backup now.
              LoggerUtils::getAppLogger()->info("Starting a backup job.  Last backup run: ".SystemConfig::getValue('sLastBackupTimeStamp'));
              $BaseName = preg_replace('/[^a-zA-Z0-9\-_]/','', SystemConfig::getValue('sChurchName')). "-" . date(SystemConfig::getValue("sDateFilenameFormat"));
              $Backup = new BackupJob($BaseName, BackupType::FullBackup, SystemConfig::getValue('bBackupExtraneousImages'),false,'');
              $Backup->Execute();
              $Backup->CopyToWebDAV(SystemConfig::getValue('sExternalBackupEndpoint'), SystemConfig::getValue('sExternalBackupUsername'), SystemConfig::getValue('sExternalBackupPassword'));
              $now = new \DateTime();  // update the LastBackupTimeStamp.
              SystemConfig::setValue('sLastBackupTimeStamp', $now->format(SystemConfig::getValue('sDateFilenameFormat')));
              LoggerUtils::getAppLogger()->info("Backup job successful");
            }
            else {
              LoggerUtils::getAppLogger()->info("Not starting a backup job.  Last backup run: ".SystemConfig::getValue('sLastBackupTimeStamp').".");
            }
          } catch (\Exception $exc) {
              // an error in the auto-backup shouldn't prevent the page from loading...
            LoggerUtils::getAppLogger()->warning("Failure executing backup job: ". $exc->getMessage() );
          }
        }
        if (SystemConfig::getBooleanValue('bEnableIntegrityCheck') && SystemConfig::getValue('iIntegrityCheckInterval') > 0) {
            if (self::IsTimerThresholdExceeded(SystemConfig::getValue('sLastIntegrityCheckTimeStamp'),SystemConfig::getValue('iIntegrityCheckInterval'))) {
                // if there was no integrity check, or if the interval suggests we do one now.
                LoggerUtils::getAppLogger()->info("Starting application integrity check");
                $integrityCheckFile = SystemURLs::getDocumentRoot() . '/integrityCheck.json';
                $appIntegrity = AppIntegrityService::verifyApplicationIntegrity();
                file_put_contents($integrityCheckFile, json_encode($appIntegrity));
                $now = new \DateTime();  // update the LastBackupTimeStamp.
                SystemConfig::setValue('sLastIntegrityCheckTimeStamp', $now->format(SystemConfig::getValue('sDateFilenameFormat')));
                if ($appIntegrity['status'] == 'success')
                {
                  LoggerUtils::getAppLogger()->info("Application integrity check passed");
                }
                else
                {
                  LoggerUtils::getAppLogger()->warning("Application integrity check failed: ".$appIntegrity['message']);
                }
            }
             else {
                  LoggerUtils::getAppLogger()->debug("Not starting application integrity check.  Last application integrity check run: ".SystemConfig::getValue('sLastIntegrityCheckTimeStamp'));
                }
        }
        if (self::IsTimerThresholdExceeded(SystemConfig::getValue('sLastSoftwareUpdateCheckTimeStamp'),SystemConfig::getValue('iSoftwareUpdateCheckInterval'))) {
          // Since checking for updates from GitHub is a potentailly expensive operation,
          // Run this task as part of the "background jobs" API call
          // Inside ChurchCRMReleaseManager, the restults are stored to the $_SESSION
          ChurchCRMReleaseManager::checkForUpdates();
          $now = new \DateTime();  // update the LastBackupTimeStamp.
          SystemConfig::setValue('sLastSoftwareUpdateCheckTimeStamp', $now->format(SystemConfig::getValue('sDateFilenameFormat')));
        }

        LoggerUtils::getAppLogger()->debug("Finished background job processing");
    }




        // Returns a file size limit in bytes based on the PHP upload_max_filesize
    // and post_max_size
    public static function getMaxUploadFileSize($humanFormat=true) {
      //select maximum upload size
      $max_upload = SystemService::parse_size(ini_get('upload_max_filesize'));
      //select post limit
      $max_post = SystemService::parse_size(ini_get('post_max_size'));
      //select memory limit
      $memory_limit = SystemService::parse_size(ini_get('memory_limit'));
      // return the smallest of them, this defines the real limit
      if ($humanFormat)
      {
        return SystemService::human_filesize(min($max_upload, $max_post, $memory_limit));
      }
      else
      {
         return min($max_upload, $max_post, $memory_limit);
      }
    }

    public static function parse_size($size) {
      $unit = preg_replace('/[^bkmgtpezy]/i', '', $size); // Remove the non-unit characters from the size.
      $size = preg_replace('/[^0-9\.]/', '', $size); // Remove the non-numeric characters from the size.
      if ($unit) {
        // Find the position of the unit in the ordered string which is the power of magnitude to multiply a kilobyte by.
        return round($size * pow(1024, stripos('bkmgtpezy', $unit[0])));
      }
      else {
        return round($size);
      }
    }

    static function human_filesize($bytes, $decimals = 2) {
      $size = array('B','kB','MB','GB','TB','PB','EB','ZB','YB');
      $factor = floor((strlen($bytes) - 1) / 3);
      return sprintf("%.{$decimals}f", $bytes / pow(1024, $factor)) . @$size[$factor];
    }
}
