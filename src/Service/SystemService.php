<?php

namespace ChurchCRM\Service;

use Exception;
use Propel\Runtime\ActiveQuery\Criteria;
use ChurchCRM\VersionQuery;
use ChurchCRM\Version;
use Propel\Runtime;
use ChurchCRM\dto\SystemConfig;

class SystemService
{

  function getLatestRelese()
  {
    $client = new \Github\Client();
    $release = null;
    try {
      $release = $client->api('repo')->releases()->latest('churchcrm', 'crm');
    } catch (Exception $e) {

    }

    return $release;
  }

  function getInstalledVersion()
  {
    $composerFile = file_get_contents(dirname(__FILE__) . "/../composer.json");
    $composerJson = json_decode($composerFile, true);
    $version = $composerJson["version"];

    return $version;
  }

  function playbackSQLtoDatabase($fileName)
  {
    $query = '';
    $restoreQueries = file($fileName, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($restoreQueries as $line) {
      if ($line != '' && strpos($line, '--') === false) {
        $query .= " $line";
        if (substr($query, -1) == ';') {
          $person = mysql_query($query);
          $query = '';
        }
      }
    }
  }

  function restoreDatabaseFromBackup()
  {
    requireUserGroupMembership("bAdmin");
    $restoreResult = new \stdClass();
    $restoreResult->Messages = array();
    global $sUSER, $sPASSWORD, $sDATABASE, $cnInfoCentral;
    $file = $_FILES['restoreFile'];
    $restoreResult->file = $file;
    $restoreResult->type = pathinfo($file['name'], PATHINFO_EXTENSION);
    $restoreResult->type2 = pathinfo(substr($file['name'], 0, strlen($file['name']) - 3), PATHINFO_EXTENSION);
    $restoreResult->root = dirname(dirname(__FILE__));
    $restoreResult->backupRoot = "$restoreResult->root/tmp_attach/ChurchCRMBackups";
    $restoreResult->imagesRoot = "Images";
    $restoreResult->headers = array();
    // Delete any old backup files
    exec("rm -rf  $restoreResult->backupRoot");
    exec("mkdir  $restoreResult->backupRoot");
    if ($restoreResult->type == "gz") {
      if ($restoreResult->type2 == "tar") {
        exec("mkdir $restoreResult->backupRoot");
        $restoreResult->uncompressCommand = "tar -zxvf " . $file['tmp_name'] . " --directory $restoreResult->backupRoot";
        exec($restoreResult->uncompressCommand, $rs1, $returnStatus);
        $restoreResult->SQLfile = "$restoreResult->backupRoot/ChurchCRM-Database.sql";
        $this->playbackSQLtoDatabase($restoreResult->SQLfile);
        exec("rm -rf $restoreResult->root/Images/*");
        exec("mv -f $restoreResult->backupRoot/Images/* $restoreResult->root/Images");
      } else if ($restoreResult->type2 == "sql") {
        exec("mkdir $restoreResult->backupRoot");
        exec("mv  " . $file['tmp_name'] . " " . $restoreResult->backupRoot . "/" . $file['name']);
        $restoreResult->uncompressCommand = SystemConfig::getValue("sGZIPname") . " -d $restoreResult->backupRoot/" . $file['name'];
        exec($restoreResult->uncompressCommand, $rs1, $returnStatus);;
        $restoreResult->SQLfile = $restoreResult->backupRoot . "/" . substr($file['name'], 0, strlen($file['name']) - 3);
        $this->playbackSQLtoDatabase($restoreResult->SQLfile);
      }
    } else if ($restoreResult->type == "sql") {
      $this->playbackSQLtoDatabase($file['tmp_name']);
    }
    exec("rm -rf $restoreResult->backupRoot");
    $restoreResult->UpgradeStatus = $this->checkDatabaseVersion();
    $this->rebuildWithSQL("/mysql/upgrade/rebuild_nav_menus.sql");
    $this->rebuildWithSQL("/mysql/upgrade/update_config.sql");
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
    global $sUSER, $sPASSWORD, $sDATABASE, $sSERVERNAME;

    $backup = new \stdClass();
    $backup->root = dirname(dirname(__FILE__));
    $backup->backupRoot = "$backup->root/tmp_attach/ChurchCRMBackups";
    $backup->imagesRoot = "Images";
    $backup->headers = array();
    // Delete any old backup files
    exec("rm -rf  $backup->backupRoot");
    exec("mkdir  $backup->backupRoot");
    // Check to see whether this installation has gzip, zip, and gpg
    if (SystemConfig::getValue("sGZIPname"))
      $hasGZIP = true;
    if (SystemConfig::getValue("sZIPname"))
      $hasZIP = true;
    if (SystemConfig::getValue("sPGPname"))
      $hasPGP = true;

    $backup->params = $params;
    $bNoErrors = true;

    $backup->saveTo = "$backup->backupRoot/ChurchCRM-" . date("Ymd-Gis");
    $backup->SQLFile = "$backup->backupRoot/ChurchCRM-Database.sql";

    $backupCommand = "mysqldump -u $sUSER --password=$sPASSWORD --host=$sSERVERNAME $sDATABASE > $backup->SQLFile";
    exec($backupCommand, $returnString, $returnStatus);

    switch ($params->iArchiveType) {
      case 0: # The user wants a gzip'd SQL file.
        $backup->saveTo .= ".sql";
        exec("mv $backup->SQLFile  $backup->saveTo");
        $backup->compressCommand = SystemConfig::getValue("sGZIPname") . " " . $backup->saveTo;
        $backup->saveTo .= ".gz";
        exec($backup->compressCommand, $returnString, $returnStatus);
        $backup->archiveResult = $returnString;
        break;
      case 1: #The user wants a .zip file
        $backup->saveTo .= ".zip";
        $backup->compressCommand = SystemConfig::getValue("sZIPname")." -r -y -q -9 $backup->saveTo $backup->backupRoot";
        exec($backup->compressCommand, $returnString, $returnStatus);
        $backup->archiveResult = $returnString;
        break;
      case 2: #The user wants a plain ol' SQL file
        $backup->saveTo .= ".sql";
        exec("mv $backup->SQLFile  $backup->saveTo");
        break;
      case 3: #the user wants a .tar.gz file
        $backup->saveTo .= ".tar.gz";
        $backup->compressCommand = "tar -zcvf $backup->saveTo -C $backup->backupRoot ChurchCRM-Database.sql -C $backup->root $backup->imagesRoot";
        exec($backup->compressCommand, $returnString, $returnStatus);
        $backup->archiveResult = $returnString;
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
        throw new \Exception("The local path SystemConfig::getValue("sExternalBackupEndpoint") is not writeable.  Unable to store backup.", 500);
      }

    }
  }

  function download($filename)
  {
    requireUserGroupMembership("bAdmin");
    set_time_limit(0);
    $path = dirname(dirname(__FILE__)) . "/tmp_attach/ChurchCRMBackups/$filename";
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
      exec("rm -rf  " . dirname(dirname(__FILE__)) . "/tmp_attach/ChurchCRMBackups");
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

  function rebuildWithSQL($SQLFile)
  {
    $root = dirname(dirname(__FILE__));
    $this->playbackSQLtoDatabase($root . $SQLFile);
  }

  function getDBVersion() {
    $connection = Runtime\Propel::getConnection();
    $query = "Select * from version_ver";
    $statement = $connection->prepare($query);
    $resultset = $statement->execute();
    $results = $statement->fetchAll(\PDO::FETCH_ASSOC);
    rsort($results);
    return $results[0]['ver_version'];
  }

  function checkDatabaseVersion()
  {

    $db_version = $this->getDBVersion();
    if ($db_version == $_SESSION['sSoftwareInstalledVersion']) {
      return true;
    }

    //the database isn't at the current version.  Start the upgrade
    $dbUpdatesFile = file_get_contents(dirname(__FILE__) . "/../mysql/upgrade.json");
    $dbUpdates = json_decode($dbUpdatesFile, true);
    $upgradeSuccess = false;
    foreach ($dbUpdates as $dbUpdate) {
      if (in_array($this->getDBVersion(), $dbUpdate["versions"])) {
        $version = new Version();
        $version->setVersion($dbUpdate["dbVersion"]);
        $version->setUpdateStart(new \DateTime());
        foreach ($dbUpdate["scripts"] as $dbScript) {
          $this->rebuildWithSQL($dbScript);
        }
        $version->setUpdateEnd(new \DateTime());
        $version->save();
        $upgradeSuccess = true;
      }
    }
    // always rebuild the menu
    $this->rebuildWithSQL("/mysql/upgrade/rebuild_nav_menus.sql");
    $this->rebuildWithSQL("/mysql/upgrade/update_config.sql");
    return $upgradeSuccess;
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
        $integrityCheckFile = dirname(__DIR__) . "/integrityCheck.json";
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
    $CRMInstallRoot = dirname(__DIR__);
    $signatureFile = $CRMInstallRoot."/signatures.json";
    $signatureFailures = array();
    if (file_exists($signatureFile))
    {
      $signatureData = json_decode(file_get_contents($signatureFile));
      if (sha1(json_encode($signatureData->files, JSON_UNESCAPED_SLASHES)) == $signatureData->sha1)
      {
        foreach ($signatureData->files as $file)
        {
          if(file_exists($CRMInstallRoot."/".$file->filename))
          {
            $actualHash = sha1_file($CRMInstallRoot."/".$file->filename);
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
