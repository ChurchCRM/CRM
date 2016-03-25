<?php

class SystemService
{

  function playbackSQLtoDatabase($fileName)
  {
    $query = '';
    $restoreQueries = file($fileName, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($restoreQueries as $line) {
      if ($line != '' && strpos($line, '--') === false) {
        $query .= $line;
        if (substr($query, -1) == ';') {
          $person = RunQuery($query);
          $query = '';
        }
      }
    }

  }

  function restoreDatabaseFromBackup()
  {
    $restoreResult = new StdClass();
    global $sUSER, $sPASSWORD, $sDATABASE, $cnInfoCentral, $sGZIPname;
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
        $restoreResult->uncompressCommand = "sGZIPname -d $restoreResult->backupRoot/" . $file['name'];
        exec($restoreResult->uncompressCommand, $rs1, $returnStatus);;
        $restoreResult->SQLfile = $restoreResult->backupRoot . "/" . substr($file['name'], 0, strlen($file['name']) - 3);
        $this->playbackSQLtoDatabase($restoreResult->SQLfile);
      }
    } else if ($restoreResult->type == "sql") {
      $this->playbackSQLtoDatabase($file['tmp_name']);
    }
    exec("rm -rf $restoreResult->backupRoot");
    $this->rebuildWithSQL("/mysql/upgrade/rebuild_nav_menus.sql");
    $this->rebuildWithSQL("/mysql/upgrade/update_config.sql");
    $restoreResult->UpgradeStatus = $this->checkDatabaseVersion();
    return $restoreResult;

  }

  function getDatabaseBackup($params)
  {
    global $sUSER, $sPASSWORD, $sDATABASE, $sSERVERNAME, $sGZIPname, $sZIPname, $sPGPname;

    $backup = new StdClass();
    $backup->root = dirname(dirname(__FILE__));
    $backup->backupRoot = "$backup->root/tmp_attach/ChurchCRMBackups";
    $backup->imagesRoot = "Images";
    $backup->headers = array();
    // Delete any old backup files
    exec("rm -rf  $backup->backupRoot");
    exec("mkdir  $backup->backupRoot");
    // Check to see whether this installation has gzip, zip, and gpg
    if (isset($sGZIPname)) $hasGZIP = true;
    if (isset($sZIPname)) $hasZIP = true;
    if (isset($sPGPname)) $hasPGP = true;

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
        $backup->compressCommand = "$sGZIPname $backup->saveTo";
        $backup->saveTo .= ".gz";
        exec($backup->compressCommand, $returnString, $returnStatus);
        $backup->archiveResult = $returnString;
        break;
      case 1: #The user wants a .zip file
        $backup->saveTo .= ".zip";
        $backup->compressCommand = "$sZIPname -r -y -q -9 $backup->saveTo $backup->backupRoot";
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

    if ($params->bEncryptBackup)  #the user has selected an encrypted backup
    {
      putenv("GNUPGHOME=/tmp");
      $backup->encryptCommand = "echo $params->password | $sPGPname -q -c --batch --no-tty --passphrase-fd 0 $backup->saveTo";
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

  function download($filename)
  {
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

  }

  function setConfigurationSetting($settingName, $settingValue)
  {

  }

  function getDatabaseVersion()
  {
    // Check if the table version_ver exists.  If the table does not exist then
    // SQL scripts must be manually run to get the database up to version 1.2.7
    $bVersionTableExists = FALSE;
    if (mysql_num_rows(RunQuery("SHOW TABLES LIKE 'version_ver'")) == 1) {
      $bVersionTableExists = TRUE;
    }

    // Let's see if the MySQL version matches the PHP version.  If we have a match then
    // proceed to Menu.php.  Otherwise further error checking is needed.
    $ver_version = "unknown";
    if ($bVersionTableExists) {
      $sSQL = 'SELECT * FROM version_ver ORDER BY ver_ID DESC';
      $aRow = mysql_fetch_array(RunQuery($sSQL));
      extract($aRow);
      return $ver_version;
    }
    return false;

  }

  function rebuildWithSQL($SQLFile)
  {
    $root = dirname(dirname(__FILE__));
    $this->playbackSQLtoDatabase($root . $SQLFile);
  }

  function checkDatabaseVersion()
  {
    $root = dirname(dirname(__FILE__));
    $ver_version = $this->getDatabaseVersion();
    if ($ver_version == $_SESSION['sSoftwareInstalledVersion']) {
      return "Current";
    }

    // This code will automatically update from 1.2.14 (last good churchinfo build to 2.0.0 for ChurchCRM
    if (strncmp($ver_version, "1.2.14", 6) == 0) {
      $old_ver_version = $ver_version;
      $sSQL = "INSERT IGNORE INTO `version_ver` (`ver_version`, `ver_date`) VALUES ('" . $_SESSION['sSoftwareInstalledVersion'] . "',NOW())";
      RunQuery($sSQL); // False means do not stop on error
      //TODO upgrade script
      $this->rebuildWithSQL("/mysql/upgrade/rebuild_nav_menus.sql");
      $this->playbackSQLtoDatabase($root . "/mysql/upgrade/1.2.14-2.0.0.sql");
      return "Upgraded";
    }

    // This code will automatically update from 1.3.0 (early build of ChurchCRM)
    if (strncmp($ver_version, "1.3.0", 6) == 0) {
      $old_ver_version = $ver_version;
      $sSQL = "INSERT IGNORE INTO `version_ver` (`ver_version`, `ver_date`) VALUES ('" . $_SESSION['sSoftwareInstalledVersion'] . "',NOW())";
      RunQuery($sSQL); // False means do not stop on error
      //TODO upgrade script
      $this->rebuildWithSQL("/mysql/upgrade/rebuild_nav_menus.sql");
      $this->playbackSQLtoDatabase($root . "/mysql/upgrade/1.3.0-2.0.0.sql");
      return "Upgraded";
    }

    return false;

  }
}
