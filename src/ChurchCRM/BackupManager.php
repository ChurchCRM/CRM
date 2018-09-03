<?php

namespace ChurchCRM
{
  use ChurchCRM\dto\SystemConfig;
  use ChurchCRM\dto\SystemURLs;
  use ChurchCRM\FileSystemUtils;
  use ChurchCRM\SQLUtils;
  use Exception;
  use Github\Client;
  use Ifsnop\Mysqldump\Mysqldump;
  use Propel\Runtime\Propel;
  use PDO;
  use ChurchCRM\Utils\InputUtils;
  use PharData;
  use RecursiveIteratorIterator;
  use RecursiveDirectoryIterator;
  use SplFileInfo;
  
  
  abstract class BackupType
  {
      const GZSQL = 0;
      const SQL = 2;
      const FullBackup = 3;
  }
  
  class BackupInstance 
  {
    public $BackupFilePath;  // This is the base filename of whatever we deliver to the client.  Might end in .sql, .tar, or .tar.gz depending on BackupType
    public $TempFolder;
    public $SQLFileName;
    public $BackupType;
  }

  class BackupManager {
   
    private static function CreateEmptyTempFolder() {
       // both backup and restore operations require a clean temporary working folder.  Create it.
      $TempFolder = SystemURLs::getDocumentRoot() . "/tmp_attach/ChurchCRMBackups";
      FileSystemUtils::recursiveRemoveDirectory($TempFolder,true);
      mkdir($TempFolder,0750,true);
      return $TempFolder;
    }
    
    private static function CaptureSQLFile(BackupInstance $Backup) {
      global $sSERVERNAME, $sDATABASE, $sUSER, $sPASSWORD;
      try {
           $dump = new Mysqldump('mysql:host=' . $sSERVERNAME . ';dbname=' . $sDATABASE, $sUSER, $sPASSWORD, ['add-drop-table' => true]);
           $dump->start($Backup->SQLFileName);
       } catch (\Exception $e) {
          throw new Exception("Unable to create backup archive at ". $Backup->SQLFileName,500);
       }
    }
    
    private static function ShouldBackupImageFile(SplFileInfo $ImageFile)
    {
      $isExtraneousFile = strpos($ImageFile->getFileName(), "-initials") != false || 
        strpos($ImageFile->getFileName(), "-remote") != false ||
        strpos($ImageFile->getPathName(), "thumbnails") != false;
      
      return $ImageFile->isFile() && !(!SystemConfig::getBooleanValue("bBackupExtraneousImages") && $isExtraneousFile); //todo: figure out this logic
      
    }
    
    public static function CreateArchive(BackupInstance $Backup) {
      $phar = new PharData($Backup->BackupFilePath);
      $phar->startBuffering();
      $phar->addFile($Backup->SQLFileName, 'ChurchCRM-Database.sql');
      $imageFiles = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(SystemURLs::getImagesRoot()));
      foreach ($imageFiles as $imageFile) {
        if (self::ShouldBackupImageFile($imageFile)) {
          $localName = substr(str_replace(SystemURLs::getDocumentRoot(), '', $imageFile->getRealPath()),1);
          $phar->addFile($imageFile->getRealPath(), $localName);
        }
      }
      $phar->stopBuffering();
      $phar->compress(\Phar::GZ);
      unset($phar);
      unlink($Backup->BackupFilePath);
      unlink($Backup->SQLFileName);
    }
    
    public static function CaptureBackup($BackupType) {
      $backup = new BackupInstance();
      $backup->TempFolder =  self::CreateEmptyTempFolder();
       // if the file does not exist, then this object was constructed with intent to backup.  Calculate some paths here.
      $backup->BackupFilePath = $backup->TempFolder .'/'.preg_replace('/[^a-zA-Z0-9\-_]/','', SystemConfig::getValue('sChurchName')). "-" . date(SystemConfig::getValue("sDateFilenameFormat"));
      $backup->SQLFileName = $backup->TempFolder."/Database.sql";
      $backup->BackupType = $BackupType;
      self::CaptureSQLFile($backup);
      if ($backup->BackupType == BackupType::FullBackup) {
        $backup->BackupFilePath = $backup->BackupFilePath.".tar";
        self::CreateArchive($backup);
        $backup->BackupFilePath = $backup->BackupFilePath.".tar.gz";
      }
      elseif ($backup->BackupType == BackupType::SQL)
      {
        $backup->BackupFilePath = $backup->BackupFilePath.".sql";
        rename($backup->SQLFileName, $backup->BackupFilePath);
      }
      elseif ($backup->BackupType == BackupType::GZSQL)
      {
        $backup->BackupFilePath = $backup->BackupFilePath.'.sql.gz';
        $gzf = gzopen($backup->BackupFilePath, 'w6');
        gzwrite($gzf, file_get_contents($backup->SQLFileName));
        gzclose($gzf);
        unlink($backup->SQLFileName);
      }
      
      return $backup;

    }
    
    public function RestoreBackup() {
      
    }
    public function CopyToWebDAV($URL, $Username, $Password)
    {
      
    }

  }
}