<?php

namespace ChurchCRM\Service;

use Exception;
use ChurchCRM\Version;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\SQLUtils;
use ChurchCRM\FileSystemUtils;
use Propel\Runtime\Propel;
use PharData;
use Github\Client;
require SystemURLs::getDocumentRoot()."/vendor/ifsnop/mysqldump-php/src/Ifsnop/Mysqldump/Mysqldump.php";
use Ifsnop\Mysqldump\Mysqldump;

class SystemService
{

  function getLatestRelese()
  {
    $client = new Client();
    $release = null;
    try {
      $release = $client->api('repo')->releases()->latest('churchcrm', 'crm');
    } catch (\Exception $e) {

    }

    return $release;
  }

  function getInstalledVersion()
  {
    $composerFile = file_get_contents(SystemURLs::getDocumentRoot() . "/composer.json");
    $composerJson = json_decode($composerFile, true);
    $version = $composerJson["version"];

    return $version;
  }

  function restoreDatabaseFromBackup($file)
  {
    requireUserGroupMembership("bAdmin");
    $restoreResult = new \stdClass();
    $restoreResult->Messages = array();
    $connection = Propel::getConnection();
    $restoreResult->file = $file;
    $restoreResult->type = pathinfo($file['name'], PATHINFO_EXTENSION);
    $restoreResult->type2 = pathinfo(substr($file['name'], 0, strlen($file['name']) - 3), PATHINFO_EXTENSION);
    $restoreResult->root = SystemURLs::getDocumentRoot();
    $restoreResult->backupRoot = SystemURLs::getDocumentRoot() . "/tmp_attach/ChurchCRMBackups";
    $restoreResult->imagesRoot = "Images";
    $restoreResult->headers = array();
    $restoreResult->uploadedFileDestination = SystemURLs::getDocumentRoot()."/tmp_attach/".$file['name'];
    // Delete any old backup files
    FileSystemUtils::recursiveRemoveDirectory($restoreResult->backupRoot);
    mkdir ($restoreResult->backupRoot);
    move_uploaded_file($file['tmp_name'], $restoreResult->uploadedFileDestination );
    if ($restoreResult->type == "gz") {
      if ($restoreResult->type2 == "tar") {
        $phar = new PharData($restoreResult->uploadedFileDestination);
        $phar->extractTo($restoreResult->backupRoot);
        $restoreResult->SQLfile = "$restoreResult->backupRoot/ChurchCRM-Database.sql";
        SQLUtils::sqlImport($restoreResult->SQLfile,$connection);
        FileSystemUtils::recursiveRemoveDirectory(SystemURLs::getDocumentRoot() . "/Images");
        FileSystemUtils::recursiveCopyDirectory($restoreResult->backupRoot."/Images/", SystemURLs::getDocumentRoot() . "/Images");
      } else if ($restoreResult->type2 == "sql") {
        $restoreResult->SQLfile = SystemURLs::getDocumentRoot()."/tmp_attach/".str_replace(".gz", '', $file['name']);
        file_put_contents($restoreResult->SQLfile, gzopen($restoreResult->uploadedFileDestination , r));
        SQLUtils::sqlImport($restoreResult->SQLfile,$connection);
      }
    } else if ($restoreResult->type == "sql") {
      SQLUtils::sqlImport($restoreResult->uploadedFileDestination,$connection);
    }
    FileSystemUtils::recursiveRemoveDirectory($restoreResult->backupRoot);
    unlink($restoreResult->uploadedFileDestination);
    $restoreResult->UpgradeStatus = $this->upgradeDatabaseVersion();
    SQLUtils::sqlImport(SystemURLs::getDocumentRoot() . "/mysql/upgrade/rebuild_nav_menus.sql",$connection);
    SQLUtils::sqlImport(SystemURLs::getDocumentRoot() . "/mysql/upgrade/update_config.sql",$connection);
    //When restoring a database, do NOT let the database continue to create remote backups.
    //This can be very troublesome for users in a testing environment.
    SystemConfig::setValue("sEnableExternalBackupTarget", "0");
    array_push($restoreResult->Messages, gettext("As part of the restore, external backups have been disabled.  If you wish to continue automatic backups, you must manuall re-enable the sEnableExternalBackupTarget setting."));
    SystemConfig::setValue("sLastIntegrityCheckTimeStamp",null);
    return $restoreResult;
  }

  function getDatabaseBackup($params)
  {
    requireUserGroupMembership("bAdmin");
    global $sSERVERNAME,$sDATABASE,$sUSER,$sPASSWORD;
    $backup = new \stdClass();
    $backup->root = SystemURLs::getDocumentRoot();
    $backup->backupRoot = "$backup->root/tmp_attach/ChurchCRMBackups";
    $backup->imagesRoot = "$backup->root/Images";
    $backup->headers = array();
    // Delete any old backup files
    FileSystemUtils::recursiveRemoveDirectory($backup->backupRoot);
    mkdir ($backup->backupRoot);

    $backup->params = $params;
    $bNoErrors = true;

    $backup->saveTo = "$backup->backupRoot/ChurchCRM-" . date("Ymd-Gis");
    $backup->SQLFile = "$backup->backupRoot/ChurchCRM-Database.sql";
    
    try {
      $dump = new Mysqldump('mysql:host='.$sSERVERNAME.';dbname='.$sDATABASE, $sUSER, $sPASSWORD, array('add-drop-table' => true));
      $dump->start($backup->SQLFile);
    } catch (\Exception $e) {
      //echo 'mysqldump-php error: ' . $e->getMessage();
    }

    switch ($params->iArchiveType) {
      case 0: # The user wants a gzip'd SQL file.
        $backup->saveTo .= ".sql.gz";
        $gzf = gzopen($backup->saveTo, "w6");
        gzwrite($gzf,  file_get_contents($backup->SQLFile));
        gzclose($gzf);
        break;
      case 2: #The user wants a plain ol' SQL file
        $backup->saveTo .= ".sql";
        rename($backup->SQLFile ,$backup->saveTo);
        break;
      case 3: #the user wants a .tar.gz file
        $backup->saveTo .= ".tar";
        $phar = new \PharData($backup->saveTo);
        $phar->startBuffering();
        $phar->addFile($backup->SQLFile,"ChurchCRM-Database.sql");
        $imageFiles = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($backup->imagesRoot));
        foreach ( $imageFiles as $imageFile)
        {
          if (! $imageFile->isDir() )  {
            
           $localName = str_replace(SystemURLs::getDocumentRoot()."/", "",$imageFile->getRealPath());
           $phar->addFile($imageFile->getRealPath(),  $localName);
          }
        }
        $phar->stopBuffering();
        $phar->compress(\Phar::GZ);
        unlink($backup->saveTo);
        $backup->saveTo .= ".gz";
        break;
    }

    if ($params->bEncryptBackup) {  #the user has selected an encrypted backup
      putenv("GNUPGHOME=/tmp");
      $backup->encryptCommand = "echo $params->password | " . SystemConfig::getValue("sPGPname") . " -q -c --batch --no-tty --passphrase-fd 0 $backup->saveTo";
      $backup->saveTo .= ".gpg";
      system($backup->encryptCommand);
      $archiveType = 3;
    }

    switch ($params->iArchiveType) {
      case 0:
        array_push($backup->headers, "");
      case 1:
        array_push($backup->headers, "Content-type: application/x-zip");
      case 2:
        array_push($backup->headers, "Content-type: text/plain");
      case 3:
        array_push($backup->headers, "Content-type: application/pgp-encrypted");
    }

    $backup->filename = substr($backup->saveTo, strrpos($backup->saveTo, "/", -1) + 1);
    array_push($backup->headers, "Content-Disposition: attachment; filename=$backup->filename");

    return $backup;
  }

  function copyBackupToExternalStorage()
  {
    if (strcasecmp(SystemConfig::getValue("sExternalBackupType"), "WebDAV") == 0) {
      if (SystemConfig::getValue("sExternalBackupUsername") && SystemConfig::getValue("sExternalBackupPassword") && SystemConfig::getValue("sExternalBackupEndpoint")) {
        $params = new \stdClass();
        $params->iArchiveType = 3;
        $backup = $this->getDatabaseBackup($params);
        $backup->credentials = SystemConfig::getValue("sExternalBackupUsername") . ":" . SystemConfig::getValue("sExternalBackupPassword");
        $backup->filesize = filesize($backup->saveTo);
        $fh = fopen($backup->saveTo, 'r');
        $backup->remoteUrl = SystemConfig::getValue("sExternalBackupEndpoint");
        $ch = curl_init($backup->remoteUrl . $backup->filename);
        curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_ANY);
        curl_setopt($ch, CURLOPT_USERPWD, $backup->credentials);
        curl_setopt($ch, CURLOPT_PUT, true);
        curl_setopt($ch, CURLOPT_INFILE, $fh);
        curl_setopt($ch, CURLOPT_INFILESIZE, $backup->filesize);
        $backup->result = curl_exec($ch);
        fclose($fh);
        return ($backup);
      } else {
        throw new \Exception("WebDAV backups are not correctly configured.  Please ensure endpoint, username, and password are set", 500);
      }
    } elseif (strcasecmp(SystemConfig::getValue("sExternalBackupType"), "Local") == 0) {
      try {
        $backup = $this->getDatabaseBackup($params);
        exec("mv " . $backup->saveTo . " " . SystemConfig::getValue("sExternalBackupEndpoint"));
        return ($backup);
      } catch (\Exception $exc) {
        throw new \Exception("The local path " . SystemConfig::getValue("sExternalBackupEndpoint") . " is not writeable.  Unable to store backup.", 500);
      }

    }
  }

  function download($filename)
  {
    requireUserGroupMembership("bAdmin");
    set_time_limit(0);
    $path = SystemURLs::getDocumentRoot() . "/tmp_attach/ChurchCRMBackups/$filename";
    if (file_exists($path)) {
      if ($fd = fopen($path, "r")) {
        $fsize = filesize($path);
        $path_parts = pathinfo($path);
        $ext = strtolower($path_parts["extension"]);
        switch ($ext) {
          case "gz":
            header("Content-type: application/x-gzip");
            header("Content-Disposition: attachment; filename=\"" . $path_parts["basename"] . "\"");
            break;
          case "tar.gz":
            header("Content-type: application/x-gzip");
            header("Content-Disposition: attachment; filename=\"" . $path_parts["basename"] . "\"");
            break;
          case "sql":
            header("Content-type: text/plain");
            header("Content-Disposition: attachment; filename=\"" . $path_parts["basename"] . "\"");
            break;
          case "gpg":
            header("Content-type: application/pgp-encrypted");
            header("Content-Disposition: attachment; filename=\"" . $path_parts["basename"] . "\"");
            break;
          case "zip":
            header("Content-type: application/zip");
            header("Content-Disposition: attachment; filename=\"" . $path_parts["basename"] . "\"");
            break;
          // add more headers for other content types here
          default;
            header("Content-type: application/octet-stream");
            header("Content-Disposition: filename=\"" . $path_parts["basename"] . "\"");
            break;
        }
        header("Content-length: $fsize");
        header("Cache-control: private"); //use this to open files directly
        while (!feof($fd)) {
          $buffer = fread($fd, 2048);
          echo $buffer;
        }
      }
      fclose($fd);
      FileSystemUtils::recursiveRemoveDirectory(SystemURLs::getDocumentRoot() . "/tmp_attach/ChurchCRMBackups");
    }
  }

  function getConfigurationSetting($settingName, $settingValue)
  {
    requireUserGroupMembership("bAdmin");
  }

  function setConfigurationSetting($settingName, $settingValue)
  {
    requireUserGroupMembership("bAdmin");
  }

  function getDBVersion() {
    $connection = Propel::getConnection();
    $query = "Select * from version_ver";
    $statement = $connection->prepare($query);
    $resultset = $statement->execute();
    $results = $statement->fetchAll(\PDO::FETCH_ASSOC);
    rsort($results);
    return $results[0]['ver_version'];
  }
  
  function isDBCurrent()
  {
    return $this->getDBVersion() ==  $this->getInstalledVersion();
  }

  function upgradeDatabaseVersion()
  {
    $connection = Propel::getConnection();
    $db_version = $this->getDBVersion();
    if ($db_version == $_SESSION['sSoftwareInstalledVersion']) {
      return true;
    }

    //the database isn't at the current version.  Start the upgrade
    $dbUpdatesFile = file_get_contents(SystemURLs::getDocumentRoot()."/mysql/upgrade.json");
    $dbUpdates = json_decode($dbUpdatesFile, true);
    $errorFlag = false;
    foreach ($dbUpdates as $dbUpdate) {
      if (in_array($this->getDBVersion(), $dbUpdate["versions"])) {
        $version = new Version();
        $version->setVersion($dbUpdate["dbVersion"]);
        $version->setUpdateStart(new \DateTime());
        foreach ($dbUpdate["scripts"] as $dbScript) {
            SQLUtils::sqlImport(SystemURLs::getDocumentRoot()."/".$dbScript, $connection);
        }
        if (!$errorFlag)
        {
          $version->setUpdateEnd(new \DateTime());
          $version->save();
        }
       
      }
    }
    // always rebuild the menu
    SQLUtils::sqlImport(SystemURLs::getDocumentRoot()."/mysql/upgrade/rebuild_nav_menus.sql", $connection);
    SQLUtils::sqlImport(SystemURLs::getDocumentRoot()."/mysql/upgrade/update_config.sql", $connection);
    return "success";
  }

  function reportIssue($data)
  {

    $serviceURL = "http://demo.churchcrm.io/issues/";
    $headers = array();
    $headers[] = "Content-type: application/json";

    $issueDescription = FilterInput($data->issueDescription) . "\r\n\r\n\r\n" .
      "Collected Value Title |  Data \r\n" .
      "----------------------|----------------\r\n" .
      "Page Name |" . $data->pageName . "\r\n" .
      "Screen Size |" . $data->screenSize->height . "x" . $data->screenSize->width . "\r\n" .
      "Window Size |" . $data->windowSize->height . "x" . $data->windowSize->width . "\r\n" .
      "Page Size |" . $data->pageSize->height . "x" . $data->pageSize->width . "\r\n" .
      "Platform Information | " . php_uname($mode = "a") . "\r\n" .
      "PHP Version | " . phpversion() . "\r\n" .
      "ChurchCRM Version |" . $_SESSION['sSoftwareInstalledVersion'] . "\r\n" .
      "Reporting Browser |" . $_SERVER['HTTP_USER_AGENT'] . "\r\n";
    if (function_exists("apache_get_modules")) {
      $issueDescription .= "Apache Modules    |" . implode(",", apache_get_modules());
    }

    $postdata = new \stdClass();
    $postdata->issueTitle = FilterInput($data->issueTitle);
    $postdata->issueDescription = $issueDescription;

    $curlService = curl_init($serviceURL);

    curl_setopt($curlService, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($curlService, CURLOPT_POST, true);
    curl_setopt($curlService, CURLOPT_POSTFIELDS, json_encode($postdata));
    curl_setopt($curlService, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curlService, CURLOPT_CONNECTTIMEOUT, 1);


    $result = curl_exec($curlService);
    if ($result === FALSE) {
      throw new \Exception("Unable to reach the issue bridge", 500);
    }
    return $result;
  }

  function runTimerJobs()
  {
    //start the external backup timer job
    if (SystemConfig::getValue("sEnableExternalBackupTarget") && SystemConfig::getValue("sExternalBackupAutoInterval") > 0)  //if remote backups are enabled, and the interval is greater than zero
    {
      try {
        $now = new \DateTime();  //get the current time
        $previous = new \DateTime(SystemConfig::getValue("sLastBackupTimeStamp")); // get a DateTime object for the last time a backup was done.
        $diff = $previous->diff($now);  // calculate the difference.
        if (!SystemConfig::getValue("sLastBackupTimeStamp") || $diff->h >= SystemConfig::getValue("sExternalBackupAutoInterval"))  // if there was no previous backup, or if the interval suggests we do a backup now.
        {
          $this->copyBackupToExternalStorage();  // Tell system service to do an external storage backup.
          $now = new \DateTime();  // update the LastBackupTimeStamp.
          SystemConfig::setValue("sLastBackupTimeStamp", $now->format('Y-m-d H:i:s'));
        }
      } catch (Exception $exc) {
        // an error in the auto-backup shouldn't prevent the page from loading...
      }
    }
    if (SystemConfig::getValue("sEnableIntegrityCheck") && SystemConfig::getValue("sIntegrityCheckInterval") > 0)
    {
      $now = new \DateTime();  //get the current time
      $previous = new \DateTime(SystemConfig::getValue("sLastIntegrityCheckTimeStamp")); // get a DateTime object for the last time a backup was done.
      $diff = $previous->diff($now);  // calculate the difference.
      if (!SystemConfig::getValue("sLastIntegrityCheckTimeStamp") || $diff->h >= SystemConfig::getValue("sIntegrityCheckInterval"))  // if there was no previous backup, or if the interval suggests we do a backup now.
      {
        $integrityCheckFile = SystemURLs::getDocumentRoot() . "/integrityCheck.json";
        $appIntegrity = $this->verifyApplicationIntegrity();
        file_put_contents($integrityCheckFile, json_encode($appIntegrity));
        $now = new \DateTime();  // update the LastBackupTimeStamp.
        SystemConfig::setValue("sLastIntegrityCheckTimeStamp",  $now->format('Y-m-d H:i:s'));
      }
    }
  }

  function downloadLatestRelease()
  {
    $release = $this->getLatestRelese();
    $CRMInstallRoot = dirname(__DIR__);
    $UpgradeDir = $CRMInstallRoot."/Upgrade";
    $url = $release['assets'][0]['browser_download_url'];
    mkdir($UpgradeDir);
    file_put_contents($UpgradeDir."/".basename($url), file_get_contents($url));
    $returnFile= array();
    $returnFile['fileName'] = basename($url);
    $returnFile['fullPath'] = $UpgradeDir."/".basename($url);
    $returnFile['sha1'] = sha1_file($UpgradeDir."/".basename($url));
    return $returnFile;
  }

  function moveDir($src,$dest) {
    $files = array_diff(scandir($src), array('.', '..'));
    foreach ($files as $file) {
      if (is_dir("$src/$file")) {
        mkdir("$dest/$file");
        $this->moveDir("$src/$file","$dest/$file");
      }
      else
      {
        rename("$src/$file","$dest/$file");
      }
    }
    return rmdir($src);
  }

  function doUpgrade($zipFilename,$sha1)
  {
    ini_set('max_execution_time',60);
    $CRMInstallRoot = dirname(__DIR__);
    if($sha1 == sha1_file($zipFilename))
    {
      $zip = new \ZipArchive();
      if ($zip->open($zipFilename) == TRUE)
      {
        $zip->extractTo($CRMInstallRoot."/Upgrade");
        $zip->close();
        $this->moveDir($CRMInstallRoot."/Upgrade/churchcrm", $CRMInstallRoot);
      }
      unlink($zipFilename);
      SystemConfig::setValue("sLastIntegrityCheckTimeStamp",null);
      return "success";
    }
    else
    {
       return "hash validation failure";
    }

  }

  function verifyApplicationIntegrity()
  {
    $signatureFile = SystemURLs::getDocumentRoot()."/signatures.json";
    $signatureFailures = array();
    if (file_exists($signatureFile))
    {
      $signatureData = json_decode(file_get_contents($signatureFile));
      if (sha1(json_encode($signatureData->files, JSON_UNESCAPED_SLASHES)) == $signatureData->sha1)
      {
        foreach ($signatureData->files as $file)
        {
          $currentFile = SystemURLs::getDocumentRoot()."/".$file->filename;
          if(file_exists($currentFile))
          {
            $actualHash = sha1_file($currentFile);
            if ( $actualHash != $file->sha1 )
            {
              array_push($signatureFailures, array("filename"=>$file->filename,"status"=>"Hash Mismatch", "expectedhash"=>$file->sha1,"actualhash"=>$actualHash));
            }
          }
          else
          {
            array_push($signatureFailures, array("filename"=>$file->filename,"status"=>"File Missing"));
          }
        }
      }
      else
      {
        return array("status"=>"failure","message"=>gettext("Signature definition file signature failed validation"));
      }
    }
    else
    {
      return array("status"=>"failure","message"=>gettext("Signature definition File Missing"));
    }

    if(count($signatureFailures) > 0 )
    {
      return array("status"=>"failure","message"=>gettext("One or more files failed signature validation"),"files"=>$signatureFailures);
    }
    else
    {
       return array("status"=>"success");
    }

  }
}
