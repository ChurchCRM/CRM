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

    public function getDatabaseBackup($params)
    {
        requireUserGroupMembership('bAdmin');
        global $sSERVERNAME, $sDATABASE, $sUSER, $sPASSWORD;
        $backup = new \stdClass();
        $backup->backupRoot = SystemURLs::getDocumentRoot() . "/tmp_attach";
        $backup->backupDir = $backup->backupRoot."/ChurchCRMBackups";
        FileSystemUtils::recursiveRemoveDirectory($backup->backupRoot,true);
        mkdir($backup->backupDir,0750,true);
        $backup->headers = [];
        $backup->params = $params;

        $safeFileName = preg_replace('/[^a-zA-Z0-9\-_]/','', SystemConfig::getValue('sChurchName'));
        $baseFileName = "$backup->backupDir/" . $safeFileName . "-";

        $backup->saveTo = $baseFileName . date(SystemConfig::getValue("sDateFilenameFormat"));
        $backup->SQLFile = $baseFileName . "Database.sql";

        try {
            $dump = new Mysqldump('mysql:host=' . $sSERVERNAME . ';dbname=' . $sDATABASE, $sUSER, $sPASSWORD, ['add-drop-table' => true]);
            $dump->start($backup->SQLFile);
        } catch (\Exception $e) {
           throw new Exception("Unable to create backup archive at ". $backup->SQLFile,500);
        }

        switch ($params->iArchiveType) {
            case 0: // The user wants a gzip'd SQL file.
                $backup->saveTo .= '.sql.gz';
                $gzf = gzopen($backup->saveTo, 'w6');
                gzwrite($gzf, file_get_contents($backup->SQLFile));
                gzclose($gzf);
                break;
            case 2: //The user wants a plain ol' SQL file
                $backup->saveTo .= '.sql';
                rename($backup->SQLFile, $backup->saveTo);
                break;
            case 3: //the user wants a .tar.gz file
                $backup->saveTo .= '.tar';
                $phar = new \PharData($backup->saveTo);
                $phar->startBuffering();
                $phar->addFile($backup->SQLFile, 'ChurchCRM-Database.sql');
                $imageFiles = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(SystemURLs::getImagesRoot()));
                foreach ($imageFiles as $imageFile) {
                    if (!$imageFile->isDir()) {
                        $localName = str_replace(SystemURLs::getDocumentRoot() . '/', '', $imageFile->getRealPath());
                        $phar->addFile($imageFile->getRealPath(), $localName);
                    }
                }
                $phar->stopBuffering();
                $phar->compress(\Phar::GZ);
                unlink($backup->saveTo);
                $backup->saveTo .= '.gz';
                break;
        }

        if ($params->bEncryptBackup) {  //the user has selected an encrypted backup
            putenv('GNUPGHOME=/tmp');
            $backup->encryptCommand = "echo $params->password | " . SystemConfig::getValue('sPGPname') . " -q -c --batch --no-tty --passphrase-fd 0 $backup->saveTo";
            $backup->saveTo .= '.gpg';
            system($backup->encryptCommand);
            $archiveType = 3;
        }

        switch ($params->iArchiveType) {
            case 0:
                array_push($backup->headers, '');
            case 1:
                array_push($backup->headers, 'Content-type: application/x-zip');
            case 2:
                array_push($backup->headers, 'Content-type: text/plain');
            case 3:
                array_push($backup->headers, 'Content-type: application/pgp-encrypted');
        }

        $backup->filename = mb_substr($backup->saveTo, strrpos($backup->saveTo, '/', -1) + 1);
        array_push($backup->headers, "Content-Disposition: attachment; filename=$backup->filename");

        return $backup;
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
                fclose($fh);

                return $backup;
            } else {
                throw new \Exception('WebDAV backups are not correctly configured.  Please ensure endpoint, username, and password are set', 500);
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

    public function download($filename)
    {
        requireUserGroupMembership('bAdmin');
        set_time_limit(0);
        $path = SystemURLs::getDocumentRoot() . "/tmp_attach/ChurchCRMBackups/$filename";
        if (file_exists($path)) {
            if ($fd = fopen($path, 'r')) {
                $fsize = filesize($path);
                $path_parts = pathinfo($path);
                $ext = strtolower($path_parts['extension']);
                switch ($ext) {
                    case 'gz':
                        header('Content-type: application/x-gzip');
                        header('Content-Disposition: attachment; filename="' . $path_parts['basename'] . '"');
                        break;
                    case 'tar.gz':
                        header('Content-type: application/x-gzip');
                        header('Content-Disposition: attachment; filename="' . $path_parts['basename'] . '"');
                        break;
                    case 'sql':
                        header('Content-type: text/plain');
                        header('Content-Disposition: attachment; filename="' . $path_parts['basename'] . '"');
                        break;
                    case 'gpg':
                        header('Content-type: application/pgp-encrypted');
                        header('Content-Disposition: attachment; filename="' . $path_parts['basename'] . '"');
                        break;
                    case 'zip':
                        header('Content-type: application/zip');
                        header('Content-Disposition: attachment; filename="' . $path_parts['basename'] . '"');
                        break;
                    // add more headers for other content types here
                    default:
                        header('Content-type: application/octet-stream');
                        header('Content-Disposition: filename="' . $path_parts['basename'] . '"');
                        break;
                }
                header("Content-length: $fsize");
                header('Cache-control: private'); //use this to open files directly
                while (!feof($fd)) {
                    $buffer = fread($fd, 2048);
                    echo $buffer;
                }
            }
            fclose($fd);
            FileSystemUtils::recursiveRemoveDirectory(SystemURLs::getDocumentRoot() . '/tmp_attach/',true);
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
            'Prerequisite Status |' . ( AppIntegrityService::arePrerequisitesMet() ? "All Prerequisites met" : "Missing Prerequisites: " .json_encode(AppIntegrityService::getUnmetPrerequisites()))."\r\n".
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

    public static function runTimerJobs()
    {
        //start the external backup timer job
        if (SystemConfig::getBooleanValue('bEnableExternalBackupTarget') && SystemConfig::getValue('sExternalBackupAutoInterval') > 0) {  //if remote backups are enabled, and the interval is greater than zero
            try {
                $now = new \DateTime();  //get the current time
                $previous = new \DateTime(SystemConfig::getValue('sLastBackupTimeStamp')); // get a DateTime object for the last time a backup was done.
                $diff = $previous->diff($now);  // calculate the difference.
                if (!SystemConfig::getValue('sLastBackupTimeStamp') || $diff->h >= SystemConfig::getValue('sExternalBackupAutoInterval')) {  // if there was no previous backup, or if the interval suggests we do a backup now.
                    self::copyBackupToExternalStorage();  // Tell system service to do an external storage backup.
                    $now = new \DateTime();  // update the LastBackupTimeStamp.
                    SystemConfig::setValue('sLastBackupTimeStamp', $now->format('Y-m-d H:i:s'));
                }
            } catch (\Exception $exc) {
                // an error in the auto-backup shouldn't prevent the page from loading...
            }
        }
        if (SystemConfig::getBooleanValue('bEnableIntegrityCheck') && SystemConfig::getValue('iIntegrityCheckInterval') > 0) {
            $now = new \DateTime();  //get the current time
            $previous = new \DateTime(SystemConfig::getValue('sLastIntegrityCheckTimeStamp')); // get a DateTime object for the last time a backup was done.
            $diff = $previous->diff($now);  // calculate the difference.
            if (!SystemConfig::getValue('sLastIntegrityCheckTimeStamp') || $diff->h >= SystemConfig::getValue('iIntegrityCheckInterval')) {  // if there was no previous backup, or if the interval suggests we do a backup now.
                $integrityCheckFile = SystemURLs::getDocumentRoot() . '/integrityCheck.json';
                $appIntegrity = AppIntegrityService::verifyApplicationIntegrity();
                file_put_contents($integrityCheckFile, json_encode($appIntegrity));
                $now = new \DateTime();  // update the LastBackupTimeStamp.
                SystemConfig::setValue('sLastIntegrityCheckTimeStamp', $now->format('Y-m-d H:i:s'));
            }
        }
    }

    public function downloadLatestRelease()
    {
        $release = $this->getLatestRelease();
        $UpgradeDir = SystemURLs::getDocumentRoot() . '/Upgrade';
        foreach ($release['assets'] as $asset) {
            if ($asset['name'] == "ChurchCRM-" . $release['name'] . ".zip") {
                $url = $asset['browser_download_url'];
            }
        }
        mkdir($UpgradeDir);
        file_put_contents($UpgradeDir . '/' . basename($url), file_get_contents($url));
        $returnFile = [];
        $returnFile['fileName'] = basename($url);
        $returnFile['releaseNotes'] = $release['body'];
        $returnFile['fullPath'] = $UpgradeDir . '/' . basename($url);
        $returnFile['sha1'] = sha1_file($UpgradeDir . '/' . basename($url));

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
        ini_set('max_execution_time', 60);
        if ($sha1 == sha1_file($zipFilename)) {
            $zip = new \ZipArchive();
            if ($zip->open($zipFilename) == true) {
                $zip->extractTo(SystemURLs::getDocumentRoot() . '/Upgrade');
                $zip->close();
                $this->moveDir(SystemURLs::getDocumentRoot() . '/Upgrade/churchcrm', SystemURLs::getDocumentRoot());
            }
            unlink($zipFilename);
            SystemConfig::setValue('sLastIntegrityCheckTimeStamp', null);

            return 'success';
        } else {
            return 'hash validation failure';
        }
    }

        // Returns a file size limit in bytes based on the PHP upload_max_filesize
    // and post_max_size
    public function getMaxUploadFileSize($humanFormat=true) {
      //select maximum upload size
      $max_upload = $this->parse_size(ini_get('upload_max_filesize'));
      //select post limit
      $max_post = $this->parse_size(ini_get('post_max_size'));
      //select memory limit
      $memory_limit = $this->parse_size(ini_get('memory_limit'));
      // return the smallest of them, this defines the real limit
      if ($humanFormat)
      {
        return $this->human_filesize(min($max_upload, $max_post, $memory_limit));
      }
      else
      {
         return min($max_upload, $max_post, $memory_limit);
      }
    }

    private function parse_size($size) {
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

    function human_filesize($bytes, $decimals = 2) {
      $size = array('B','kB','MB','GB','TB','PB','EB','ZB','YB');
      $factor = floor((strlen($bytes) - 1) / 3);
      return sprintf("%.{$decimals}f", $bytes / pow(1024, $factor)) . @$size[$factor];
    }
}
