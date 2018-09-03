<?php

namespace ChurchCRM\Backup
{
  use ChurchCRM\dto\SystemURLs;
  use ChurchCRM\FileSystemUtils;
  use ChurchCRM\SQLUtils;
  use Exception;
  use Ifsnop\Mysqldump\Mysqldump;
  use Propel\Runtime\Propel;
  use PDO;
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
  
  class BackupActionResult 
  {
    public $BackupFilePath;
    public $TempFolder;
    public $BackupType;
  }

  class JobBase {
    
    protected $BackupType;
    protected $BackupActionResult;
   
    protected function CreateEmptyTempFolder() {
       // both backup and restore operations require a clean temporary working folder.  Create it.
      $TempFolder = SystemURLs::getDocumentRoot() . "/tmp_attach/ChurchCRMBackups";
      FileSystemUtils::recursiveRemoveDirectory($TempFolder,false);
      mkdir($TempFolder,0750,true);
      return $TempFolder;
    }
  }
  
  class BackupJob extends JobBase {
    private $BackupFileBaseName;
    
    /**
     *
     * @var SplFileInfo
     */
    private $BackupFile;
    private $IncludeExtraneousFiles;
    
    public function __construct($BaseName, $BackupType, $IncludeExtraneousFiles) {
      $this->BackupType = $BackupType;
      $this->TempFolder =  $this->CreateEmptyTempFolder();
      $this->BackupFileBaseName = $this->TempFolder .'/'.$BaseName;
      $this->BackupType = $BackupType;
      $this->IncludeExtraneousFiles = $IncludeExtraneousFiles;
    }

    public function CopyToWebDAV($Endpoint, $Username, $Password) {

      $fh = fopen($this->BackupFile->getPathname(), 'r');
      $remoteUrl = $Endpoint.urlencode($BackupFile->getFilename());
      $credentials = $Username.":".$Password;
      $ch = curl_init($remoteUrl);
      curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_ANY);
      curl_setopt($ch, CURLOPT_USERPWD, $credentials);
      curl_setopt($ch, CURLOPT_PUT, true);
      curl_setopt($ch, CURLOPT_INFILE, $fh);
      curl_setopt($ch, CURLOPT_INFILESIZE, $BackupFile->getSize());
      $result = curl_exec($ch);
      fclose($fh);
      return $result;
      
    }
    
    private function CaptureSQLFile(\SplFileInfo $SqlFilePath) {
      global $sSERVERNAME, $sDATABASE, $sUSER, $sPASSWORD;
      try {
           $dump = new Mysqldump('mysql:host=' . $sSERVERNAME . ';dbname=' . $sDATABASE, $sUSER, $sPASSWORD, ['add-drop-table' => true]);
           $dump->start($SqlFilePath->getPathname());
       } catch (\Exception $e) {
          throw new Exception("Unable to create backup archive at ". $Backup->SQLFileName,500);
       }
    }
    
    private function ShouldBackupImageFile(SplFileInfo $ImageFile)    {
      $isExtraneousFile = strpos($ImageFile->getFileName(), "-initials") != false || 
        strpos($ImageFile->getFileName(), "-remote") != false ||
        strpos($ImageFile->getPathName(), "thumbnails") != false;
      
      return $ImageFile->isFile() && !(!$this->IncludeExtraneousFiles && $isExtraneousFile); //todo: figure out this logic
      
    }
    
    private function CreateFullArchive() {
      
      $this->BackupFile = new \SplFileInfo($this->BackupFileBaseName.".tar");
      $phar = new PharData($this->BackupFile->getPathname());
      $phar->startBuffering();
   
      $SqlFile =  new \SplFileInfo($this->TempFolder."/".'ChurchCRM-Database.sql');
      $this->CaptureSQLFile($SqlFile);
      $phar->addFile($SqlFile, 'ChurchCRM-Database.sql');
      
      $imageFiles = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(SystemURLs::getImagesRoot()));
      foreach ($imageFiles as $imageFile) {
        if ($this->ShouldBackupImageFile($imageFile)) {
          $localName = substr(str_replace(SystemURLs::getDocumentRoot(), '', $imageFile->getRealPath()),1);
          $phar->addFile($imageFile->getRealPath(), $localName);
        }
      }
      $phar->stopBuffering();
      $phar->compress(\Phar::GZ);
      unset($phar);
      unlink($this->BackupFile->getPathname());
      $this->BackupFile = new \SplFileInfo($this->BackupFileBaseName.".tar.gz");
      unlink($SqlFile);
    }
    
    private function CreateGZSql(){
      $SqlFile =  new \SplFileInfo($this->TempFolder."/".'ChurchCRM-Database.sql');
      $this->CaptureSQLFile($SqlFile);
      $this->BackupFile = new \SplFileInfo($this->BackupFileBaseName.'.sql.gz');
      $gzf = gzopen($this->BackupFile->getPathname(), 'w6');
      gzwrite($gzf, file_get_contents($SqlFile->getPathname()));
      gzclose($gzf);
      unlink($SqlFile->getPathname());
    }
    public function Execute() {
      
      if ($this->BackupType == BackupType::FullBackup) {
        $this->CreateFullArchive();
      }
      elseif ($this->BackupType == BackupType::SQL)
      {
        $this->BackupFile = new \SplFileInfo($this->BackupFileBaseName.".sql");
        $this->CaptureSQLFile($this->BackupFile);
      }
      elseif ($this->BackupType == BackupType::GZSQL)
      {
         $this->CreateGZSql();
      }
      
      return $backup;

    }
  }
  
  class RestoreJob extends JobBase {
    private function DiscoverBackupType(\SplFileInfo $BackupFile)
    {
      if ($BackupFile->getExtension() == ".tar.gz")
      {
        return BackupType::FullBackup; 
      }
      elseif ($BackupFile->getExtension() == ".sql.gz")
      {
        return BackupType::GZSQL;
      }
      elseif ($BackupFile->getExtension() == ".sql")
      {
        return BackupType::SQL;
      }
    }
    
    public function Execute() {
      $Backup = new BackupInstance();
      $Backup->TempFolder =  self::CreateEmptyTempFolder();
       // if the file does not exist, then this object was constructed with intent to backup.  Calculate some paths here.
      $Backup->BackupFilePath = $backup->TempFolder .'/'.preg_replace('/[^a-zA-Z0-9\-_]/','', SystemConfig::getValue('sChurchName')). "-" . date(SystemConfig::getValue("sDateFilenameFormat"));
      $Backup->BackupType = self::DiscoverBackupType(new \SplFileInfo($Backup->BackupFilePath));
    }
  }
}