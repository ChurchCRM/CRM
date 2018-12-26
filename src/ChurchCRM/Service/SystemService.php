<?php

namespace ChurchCRM\Service;

use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\FileSystemUtils;
use ChurchCRM\SQLUtils;
use Exception;
use Github\Client;
use Ifsnop\Mysqldump\Mysqldump;
use PharData;
use Propel\Runtime\Propel;
use PDO;
use ChurchCRM\Utils\InputUtils;
use ChurchCRM\Utils\LoggerUtils;
use ChurchCRM\Utils\ExecutionTime;

require SystemURLs::getDocumentRoot() . '/vendor/ifsnop/mysqldump-php/src/Ifsnop/Mysqldump/Mysqldump.php';


class SystemService
{
    public function getLatestRelease()
    {
        $client = new Client();
        $release = null;
        try {
            $release = $client->api('repo')->releases()->latest('churchcrm', 'crm');
        } catch (\Exception $e) {
        }

        return $release;
    }

    static public function getInstalledVersion()
    {
        $composerFile = file_get_contents(SystemURLs::getDocumentRoot() . '/composer.json');
        $composerJson = json_decode($composerFile, true);
        $version = $composerJson['version'];

        return $version;
    }

    static public function getCopyrightDate()
    {
        $composerFile = file_get_contents(SystemURLs::getDocumentRoot() . '/composer.json');
        $composerJson = json_decode($composerFile, true);
        $buildTime = new \DateTime();

        if ((!empty($composerJson)) && array_key_exists('time', $composerJson) && (!empty($composerJson['time'])))
        {
            try{ 
                $buildTime = new \DateTime($composerJson['time']);
            } catch (Exception $e) {
                // will use default
            }
        }
        return $buildTime->format("Y");
    }

    public function restoreDatabaseFromBackup($file)
    {
        requireUserGroupMembership('bAdmin');
        $restoreResult = new \stdClass();
        $restoreResult->Messages = [];
        $connection = Propel::getConnection();
        $restoreResult->file = $file;
        $restoreResult->type = pathinfo($file['name'], PATHINFO_EXTENSION);
        $restoreResult->type2 = pathinfo(mb_substr($file['name'], 0, strlen($file['name']) - 3), PATHINFO_EXTENSION);
        $restoreResult->root = SystemURLs::getDocumentRoot();
        $restoreResult->headers = [];
        $restoreResult->backupRoot = SystemURLs::getDocumentRoot() . '/tmp_attach/';
        $restoreResult->backupDir = $restoreResult->backupRoot  . '/ChurchCRMRestores/';
        $restoreResult->uploadedFileDestination =  $restoreResult->backupDir . '/' . $file['name'];
        // Delete any old backup files
        FileSystemUtils::recursiveRemoveDirectory($restoreResult->backupRoot,true);
        mkdir($restoreResult->backupDir);
        move_uploaded_file($file['tmp_name'], $restoreResult->uploadedFileDestination);
        if ($restoreResult->type == 'gz') {
            if ($restoreResult->type2 == 'tar') {
                $phar = new PharData($restoreResult->uploadedFileDestination);
                $phar->extractTo($restoreResult->backupDir);
                $restoreResult->SQLfile = "$restoreResult->backupDir/ChurchCRM-Database.sql";
                if (file_exists($restoreResult->SQLfile))
                {
                  SQLUtils::sqlImport($restoreResult->SQLfile, $connection);
                  FileSystemUtils::recursiveRemoveDirectory(SystemURLs::getDocumentRoot() . '/Images');
                  FileSystemUtils::recursiveCopyDirectory($restoreResult->backupDir . '/Images/', SystemURLs::getImagesRoot());
                }
                else
                {
                  FileSystemUtils::recursiveRemoveDirectory($restoreResult->backupDir,true);
                  throw new Exception(gettext("Backup archive does not contain a database").": " . $file['name']);
                }

            } elseif ($restoreResult->type2 == 'sql') {
                $restoreResult->SQLfile = $restoreResult->backupDir . str_replace('.gz', '', $file['name']);
                file_put_contents($restoreResult->SQLfile, gzopen($restoreResult->uploadedFileDestination, r));
                SQLUtils::sqlImport($restoreResult->SQLfile, $connection);
            }
        } elseif ($restoreResult->type == 'sql') {
            SQLUtils::sqlImport($restoreResult->uploadedFileDestination, $connection);
        } else {
            FileSystemUtils::recursiveRemoveDirectory($restoreResult->backupDir,true);
            throw new Exception(gettext("Unknown File Type").": " . $restoreResult->type . " ".gettext("from file").": " . $file['name']);
        }
        FileSystemUtils::recursiveRemoveDirectory($restoreResult->backupRoot,true);
        $restoreResult->UpgradeStatus = UpgradeService::upgradeDatabaseVersion();
        SQLUtils::sqlImport(SystemURLs::getDocumentRoot() . '/mysql/upgrade/rebuild_views.sql', $connection);
        //When restoring a database, do NOT let the database continue to create remote backups.
        //This can be very troublesome for users in a testing environment.
        SystemConfig::setValue('bEnableExternalBackupTarget', '0');
        array_push($restoreResult->Messages, gettext('As part of the restore, external backups have been disabled.  If you wish to continue automatic backups, you must manuall re-enable the bEnableExternalBackupTarget setting.'));
        SystemConfig::setValue('sLastIntegrityCheckTimeStamp', null);

        return $restoreResult;
    }

    public static function copyBackupToExternalStorage()
    {
        $params = new \stdClass();
        $params->iArchiveType = 3;
        if (strcasecmp(SystemConfig::getValue('sExternalBackupType'), 'WebDAV') == 0) {
            if (SystemConfig::getValue('sExternalBackupUsername') && SystemConfig::getValue('sExternalBackupPassword') && SystemConfig::getValue('sExternalBackupEndpoint')) {
                $backup = self::getDatabaseBackup($params);
                $backup->credentials = SystemConfig::getValue('sExternalBackupUsername') . ':' . SystemConfig::getValue('sExternalBackupPassword');
                $backup->filesize = filesize($backup->saveTo);
                $fh = fopen($backup->saveTo, 'r');
                $backup->remoteUrl = SystemConfig::getValue('sExternalBackupEndpoint');
                $ch = curl_init($backup->remoteUrl . $backup->filename);
                curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_ANY);
                curl_setopt($ch, CURLOPT_USERPWD, $backup->credentials);
                curl_setopt($ch, CURLOPT_PUT, true);
                curl_setopt($ch, CURLOPT_INFILE, $fh);
                curl_setopt($ch, CURLOPT_INFILESIZE, $backup->filesize);
                $backup->result = curl_exec($ch);
                $responseCode = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
                if (substr($responseCode,0,1) != "2")
                {
                  // the remote server didn't respond with 2xx, so it's an error:
                  throw new \Exception("Could not back up '".$backup->filename."' (".$backup->filesize." bytes) to '".$backup->remoteUrl.". Was expecting 2xx, got: ".curl_getinfo($ch, CURLINFO_RESPONSE_CODE), 500);
                }
                fclose($fh);

                return $backup;
            }
        } elseif (strcasecmp(SystemConfig::getValue('sExternalBackupType'), 'Local') == 0) {
            try {
                $backup = self::getDatabaseBackup($params);
                exec('mv ' . $backup->saveTo . ' ' . SystemConfig::getValue('sExternalBackupEndpoint'));

                return $backup;
            } catch (\Exception $exc) {
                throw new \Exception('The local path ' . SystemConfig::getValue('sExternalBackupEndpoint') . ' is not writeable.  Unable to store backup.', 500);
            }
        }
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
        $query = 'Select * from version_ver';
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

    static public function isDBCurrent()
    {
        return SystemService::getDBVersion() == SystemService::getInstalledVersion();
    }

    static public function getDBTableExists($tableName) {
      if (!isset($_SESSION['CRM_DB_TABLES']))
      {
        $connection = Propel::getConnection();
        $statement = $connection->prepare("SHOW FULL TABLES;");
        $statement->execute();
        $_SESSION['CRM_DB_TABLES'] = array_map(function($table) { return $table[0]; } , $statement->fetchAll());
      }
      return in_array($tableName,$_SESSION['CRM_DB_TABLES']);
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
      LoggerUtils::getAppLogger()->addInfo("Starting background job processing");
        //start the external backup timer job
        if (SystemConfig::getBooleanValue('bEnableExternalBackupTarget') && SystemConfig::getValue('sExternalBackupAutoInterval') > 0) {  //if remote backups are enabled, and the interval is greater than zero
          try {
            if (self::IsTimerThresholdExceeded(SystemConfig::getValue('sLastBackupTimeStamp'), SystemConfig::getValue('sExternalBackupAutoInterval'))) {
              // if there was no previous backup, or if the interval suggests we do a backup now.
              LoggerUtils::getAppLogger()->addInfo("Starting a backup job.  Last backup run: ".SystemConfig::getValue('sLastBackupTimeStamp'));
              $backup = self::copyBackupToExternalStorage();  // Tell system service to do an external storage backup.
              $now = new \DateTime();  // update the LastBackupTimeStamp.
              SystemConfig::setValue('sLastBackupTimeStamp', $now->format(SystemConfig::getValue('sDateFilenameFormat')));
              LoggerUtils::getAppLogger()->addInfo("Backup job successful: '".$backup->filename."' (".$backup->filesize." bytes) copied to '".$backup->remoteUrl."'");
            }
            else {
              LoggerUtils::getAppLogger()->addInfo("Not starting a backup job.  Last backup run: ".SystemConfig::getValue('sLastBackupTimeStamp').".");
            }
          } catch (\Exception $exc) {
              // an error in the auto-backup shouldn't prevent the page from loading...
            LoggerUtils::getAppLogger()->addWarning("Failure executing backup job: ". $exc->getMessage() );
          }
        }
        if (SystemConfig::getBooleanValue('bEnableIntegrityCheck') && SystemConfig::getValue('iIntegrityCheckInterval') > 0) {
            if (self::IsTimerThresholdExceeded(SystemConfig::getValue('sLastIntegrityCheckTimeStamp'),SystemConfig::getValue('iIntegrityCheckInterval'))) {
                // if there was no integrity check, or if the interval suggests we do one now.
                LoggerUtils::getAppLogger()->addInfo("Starting application integrity check");
                $integrityCheckFile = SystemURLs::getDocumentRoot() . '/integrityCheck.json';
                $appIntegrity = AppIntegrityService::verifyApplicationIntegrity();
                file_put_contents($integrityCheckFile, json_encode($appIntegrity));
                $now = new \DateTime();  // update the LastBackupTimeStamp.
                SystemConfig::setValue('sLastIntegrityCheckTimeStamp', $now->format(SystemConfig::getValue('sDateFilenameFormat')));
                if ($appIntegrity['status'] == 'success')
                {
                  LoggerUtils::getAppLogger()->addInfo("Application integrity check passed");
                }
                else
                {
                  LoggerUtils::getAppLogger()->addWarning("Application integrity check failed: ".$appIntegrity['message']);
                }
            }
             else {
                  LoggerUtils::getAppLogger()->addInfo("Not starting application integrity check.  Last application integrity check run: ".SystemConfig::getValue('sLastIntegrityCheckTimeStamp'));
                }
        }
        LoggerUtils::getAppLogger()->addInfo("Finished background job processing");
    }

    public function downloadLatestRelease()
    {
      $logger = LoggerUtils::getAppLogger();
      $logger->debug("Querying for latest release");
      $release = $this->getLatestRelease();
      $logger->debug("Query result: " . print_r($release,true));
      $UpgradeDir = SystemURLs::getDocumentRoot() . '/Upgrade';
      foreach ($release['assets'] as $asset) {
        if ($asset['name'] == "ChurchCRM-" . $release['name'] . ".zip") {
          $url = $asset['browser_download_url'];
        }
      }
      $logger->debug("Creating upgrade directory: " . $UpgradeDir);
      mkdir($UpgradeDir);
      $logger->info("Downloading release from: " . $url . " to: ". $UpgradeDir . '/' . basename($url));
      $executionTime = new ExecutionTime();
      file_put_contents($UpgradeDir . '/' . basename($url), file_get_contents($url));
      $logger->info("Finished downloading file.  Execution time: " .$executionTime->getMiliseconds()." ms");
      $returnFile = [];
      $returnFile['fileName'] = basename($url);
      $returnFile['releaseNotes'] = $release['body'];
      $returnFile['fullPath'] = $UpgradeDir . '/' . basename($url);
      $returnFile['sha1'] = sha1_file($UpgradeDir . '/' . basename($url));
      $logger->info("SHA1 hash for ". $returnFile['fullPath'] .": " . $returnFile['sha1']);
      $logger->info("Release notes: " . $returnFile['releaseNotes'] );
      return $returnFile;
    }

    public function moveDir($src, $dest)
    {
        $files = array_diff(scandir($src), ['.', '..']);
        foreach ($files as $file) {
            if (is_dir("$src/$file")) {
                mkdir("$dest/$file");
                $this->moveDir("$src/$file", "$dest/$file");
            } else {
                rename("$src/$file", "$dest/$file");
            }
        }

        return rmdir($src);
    }

    public function doUpgrade($zipFilename, $sha1)
    {
      $logger = LoggerUtils::getAppLogger();
      $logger->info("Beginnging upgrade process");
      $logger->info("PHP max_execution_time is now: " . ini_get("max_execution_time"));
      $logger->info("Beginning hash validation on " . $zipFilename);
      if ($sha1 == sha1_file($zipFilename)) {
        $logger->info("Hash validation succeeded on " . $zipFilename . " Got: " . sha1_file($zipFilename));
        $zip = new \ZipArchive();
        if ($zip->open($zipFilename) == true) {
          $logger->info("Extracting " . $zipFilename." to: " . SystemURLs::getDocumentRoot() . '/Upgrade');
          $executionTime = new ExecutionTime();
          $zip->extractTo(SystemURLs::getDocumentRoot() . '/Upgrade');
          $zip->close();
          $logger->info("Extraction completed.  Took:" . $executionTime->getMiliseconds());
          $logger->info("Moving extracted zip into place");
          $executionTime = new ExecutionTime();
          $this->moveDir(SystemURLs::getDocumentRoot() . '/Upgrade/churchcrm', SystemURLs::getDocumentRoot());
          $logger->info("Move completed.  Took:" . $executionTime->getMiliseconds());
        }
        $logger->info("Deleting zip archive: ".$zipFilename);
        unlink($zipFilename);
        SystemConfig::setValue('sLastIntegrityCheckTimeStamp', null);
        $logger->debug("Set sLastIntegrityCheckTimeStamp to null");
        $logger->info("Upgrade process complete");
        return 'success';
      } else {
        $logger->err("Hash validation failed on " . $zipFilename.". Expected: ".$sha1. ". Got: ".sha1_file($zipFilename));
        return 'hash validation failure';
      }
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
