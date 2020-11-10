<?php

namespace ChurchCRM\Backup
{
  use ChurchCRM\dto\SystemURLs;
  use ChurchCRM\dto\SystemConfig;
  use ChurchCRM\FileSystemUtils;
  use ChurchCRM\Utils\LoggerUtils;
  use ChurchCRM\SQLUtils;
  use Exception;
  use Ifsnop\Mysqldump\Mysqldump;
  use Propel\Runtime\Propel;
  use PharData;
  use RecursiveIteratorIterator;
  use RecursiveDirectoryIterator;
  use SplFileInfo;
  use ChurchCRM\Utils\InputUtils;
  use Defuse\Crypto\File;
  use ChurchCRM\Bootstrapper;

  abstract class BackupType
  {
      const GZSQL = 0;
      const SQL = 2;
      const FullBackup = 3;
  }

  class JobBase
  {
      /**
        *
        * @var BackupType
        */
      protected $BackupType;

      /**
       *
       * @var String
       */
      protected $TempFolder;

      protected function CreateEmptyTempFolder()
      {
          // both backup and restore operations require a clean temporary working folder.  Create it.
          $TempFolder = SystemURLs::getDocumentRoot() . "/tmp_attach/ChurchCRMBackups";
          LoggerUtils::getAppLogger()->debug("Removing temp folder tree at ". $TempFolder);
          FileSystemUtils::recursiveRemoveDirectory($TempFolder, false);
          LoggerUtils::getAppLogger()->debug("Creating temp folder at ". $TempFolder);
          mkdir($TempFolder, 0750, true);
          LoggerUtils::getAppLogger()->debug("Temp folder created");
          return $TempFolder;
      }
  }

  class BackupJob extends JobBase
  {
      /**
       *
       * @var String
       */
      private $BackupFileBaseName;
      /**
       *
       * @var SplFileInfo
       */
      private $BackupFile;
      /**
       *
       * @var Boolean
       */
      private $IncludeExtraneousFiles;
      /**
       *
       * @var String
       */
      public $BackupDownloadFileName;
      /**
       *
       * @var Boolean
       */
      public $shouldEncrypt;

      /**
       *
       * @var String
       */
      public $BackupPassword;


      /**
       *
       * @param String $BaseName
       * @param BackupType $BackupType
       * @param Boolean $IncludeExtraneousFiles
       */
      public function __construct($BaseName, $BackupType, $IncludeExtraneousFiles, $EncryptBackup, $BackupPassword)
      {
          $this->BackupType = $BackupType;
          $this->TempFolder =  $this->CreateEmptyTempFolder();
          $this->BackupFileBaseName = $this->TempFolder .'/'.$BaseName;
          $this->IncludeExtraneousFiles = $IncludeExtraneousFiles;
          $this->shouldEncrypt = $EncryptBackup;
          $this->BackupPassword = $BackupPassword;
          LoggerUtils::getAppLogger()->debug(
              "Backup job created; ready to execute: Type: '" .
                  $this->BackupType .
                  "' Temp Folder: '" .
                  $this->TempFolder .
                  "' BaseName: '" . $this->BackupFileBaseName.
                  "' Include extra files: '". ($this->IncludeExtraneousFiles ? 'true':'false') ."'"
          );
      }

      public function CopyToWebDAV($Endpoint, $Username, $Password)
      {
          LoggerUtils::getAppLogger()->info("Beginning to copy backup to: " . $Endpoint);
          try {
              $fh = fopen($this->BackupFile->getPathname(), 'r');
              $remoteUrl = $Endpoint.urlencode($this->BackupFile->getFilename());
              LoggerUtils::getAppLogger()->debug("Full remote URL: " .$remoteUrl);
              $credentials = $Username.":".$Password;
              $ch = curl_init($remoteUrl);
              curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_ANY);
              curl_setopt($ch, CURLOPT_USERPWD, $credentials);
              curl_setopt($ch, CURLOPT_PUT, true);
              curl_setopt($ch, CURLOPT_FAILONERROR, true);
              curl_setopt($ch, CURLOPT_INFILE, $fh);
              curl_setopt($ch, CURLOPT_INFILESIZE, $this->BackupFile->getSize());
              LoggerUtils::getAppLogger()->debug("Beginning to send file");
              $time = new \ChurchCRM\Utils\ExecutionTime();
              $result = curl_exec($ch);
              if (curl_error($ch)) {
                  $error_msg = curl_error($ch);
              }
              curl_close($ch);
              fclose($fh);

              if (isset($error_msg)) {
                  throw new \Exception("Error backing up to remote: ". $error_msg);
              }
              LoggerUtils::getAppLogger()->debug("File send complete.  Took: " . $time->getMiliseconds() . "ms");
          } catch (\Exception $e) {
              LoggerUtils::getAppLogger()->error("Error copying backup: " . $e);
          }
          LoggerUtils::getAppLogger()->info("Backup copy completed.  Curl result: " . $result);
          return $result;
      }

      private function CaptureSQLFile(\SplFileInfo $SqlFilePath)
      {
          global $sSERVERNAME, $sDATABASE, $sUSER, $sPASSWORD;
          LoggerUtils::getAppLogger()->debug("Beginning to backup datbase to: " . $SqlFilePath->getPathname());
          try {
              $dump = new Mysqldump(Bootstrapper::GetDSN(), $sUSER, $sPASSWORD, ['add-drop-table' => true]);
              $dump->start($SqlFilePath->getPathname());
              LoggerUtils::getAppLogger()->debug("Finisehd backing up datbase to " . $SqlFilePath->getPathname());
          } catch (\Exception $e) {
              $message = "Failed to backup database to: " . $SqlFilePath->getPathname(). " Exception: " . $e;
              LoggerUtils::getAppLogger()->error($message);
              throw new Exception($message, 500);
          }
      }

      private function ShouldBackupImageFile(SplFileInfo $ImageFile)
      {
          $isExtraneousFile = strpos($ImageFile->getFileName(), "-initials") != false ||
        strpos($ImageFile->getFileName(), "-remote") != false ||
        strpos($ImageFile->getPathName(), "thumbnails") != false;

          return $ImageFile->isFile() && !(!$this->IncludeExtraneousFiles && $isExtraneousFile); //todo: figure out this logic
      }

      private function CreateFullArchive()
      {
          $imagesAddedToArchive = array();
          $this->BackupFile = new \SplFileInfo($this->BackupFileBaseName.".tar");
          $phar = new PharData($this->BackupFile->getPathname());
          LoggerUtils::getAppLogger()->debug("Archive opened at: ".$this->BackupFile->getPathname());
          $phar->startBuffering();

          $SqlFile =  new \SplFileInfo($this->TempFolder."/".'ChurchCRM-Database.sql');
          $this->CaptureSQLFile($SqlFile);
          $phar->addFile($SqlFile, 'ChurchCRM-Database.sql');
          LoggerUtils::getAppLogger()->debug("Database added to archive");
          $imageFiles = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(SystemURLs::getImagesRoot()));
          foreach ($imageFiles as $imageFile) {
              if ($this->ShouldBackupImageFile($imageFile)) {
                  $localName = substr(str_replace(SystemURLs::getDocumentRoot(), '', $imageFile->getRealPath()), 1);
                  $phar->addFile($imageFile->getRealPath(), $localName);
                  array_push($imagesAddedToArchive, $imageFile->getRealPath());
              }
          }
          LoggerUtils::getAppLogger()->debug("Images files added to archive: ". join(";", $imagesAddedToArchive));
          $phar->stopBuffering();
          LoggerUtils::getAppLogger()->debug("Finished creating archive.  Beginning to compress");
          $phar->compress(\Phar::GZ);
          LoggerUtils::getAppLogger()->debug("Archive compressed; should now be a .gz file");
          unset($phar);
          unlink($this->BackupFile->getPathname());
          LoggerUtils::getAppLogger()->debug("Initial .tar archive deleted: " . $this->BackupFile->getPathname());
          $this->BackupFile = new \SplFileInfo($this->BackupFileBaseName.".tar.gz");
          LoggerUtils::getAppLogger()->debug("New backup file: " .  $this->BackupFile);
          unlink($SqlFile);
          LoggerUtils::getAppLogger()->debug("Temp Database backup deleted: " . $SqlFile);
      }

      private function CreateGZSql()
      {
          $SqlFile =  new \SplFileInfo($this->TempFolder."/".'ChurchCRM-Database.sql');
          $this->CaptureSQLFile($SqlFile);
          $this->BackupFile = new \SplFileInfo($this->BackupFileBaseName.'.sql.gz');
          $gzf = gzopen($this->BackupFile->getPathname(), 'w6');
          gzwrite($gzf, file_get_contents($SqlFile->getPathname()));
          gzclose($gzf);
          unlink($SqlFile->getPathname());
      }

      private function EncryptBackupFile()
      {
          LoggerUtils::getAppLogger()->info("Encrypting backup file: ".$this->BackupFile);
          $tempfile = new \SplFileInfo($this->BackupFile->getPathname()."temp");
          rename($this->BackupFile, $tempfile);
          File::encryptFileWithPassword($tempfile, $this->BackupFile, $this->BackupPassword);
          LoggerUtils::getAppLogger()->info("Finished ecrypting backup file");
      }
      public function Execute()
      {
          $time = new \ChurchCRM\Utils\ExecutionTime();
          LoggerUtils::getAppLogger()->info("Beginning backup job. Type: " . $this->BackupType . ". BaseName: " . $this->BackupFileBaseName);
          if ($this->BackupType == BackupType::FullBackup) {
              $this->CreateFullArchive();
          } elseif ($this->BackupType == BackupType::SQL) {
              $this->BackupFile = new \SplFileInfo($this->BackupFileBaseName.".sql");
              $this->CaptureSQLFile($this->BackupFile);
          } elseif ($this->BackupType == BackupType::GZSQL) {
              $this->CreateGZSql();
          }
          if ($this->shouldEncrypt) {
              $this->EncryptBackupFile();
          }
          $time->End();
          $percentExecutionTime = (($time->getMiliseconds()/1000)/ini_get('max_execution_time'))*100;
          LoggerUtils::getAppLogger()->info("Completed backup job.  Took : " . $time->getMiliseconds()."ms. ".$percentExecutionTime."% of max_execution_time");
          if ($percentExecutionTime > 80) {
              // if the backup took more than 80% of the max_execution_time, then write a warning to the log
              LoggerUtils::getAppLogger()->warning("Backup task took more than 80% of max_execution_time (".ini_get('max_execution_time').").  Consider increasing this time to avoid a failure");
          }
          $this->BackupDownloadFileName  = $this->BackupFile->getFilename();
          return true;
      }
  }

  class RestoreJob extends JobBase
  {
      /**
       *
       * @var SplFileInfo
       */
      private $RestoreFile;

      /**
       *
       * @var Array
       */
      public $Messages;
      /**
       *
       * @var Boolean
       */
      private $IsBackupEncrypted;
      /**
       *
       * @var String
       */
      private $restorePassword;

      private function IsIncomingFileFailed()
      {
          // Not actually sure what this is supposed to do, but it was working before??
          return $_SERVER['REQUEST_METHOD'] == 'POST' && empty($_POST) && empty($_FILES) && $_SERVER['CONTENT_LENGTH'] > 0;
      }

      public function __construct()
      {
          LoggerUtils::getAppLogger()->info("Beginning to process incoming archvie for restoration");
          $this->Messages = array();
          if ($this->IsIncomingFileFailed()) {
              $message = "The selected file exceeds this servers maximum upload size of: " . SystemService::getMaxUploadFileSize();
              LoggerUtils::getAppLogger()->error($message);
              throw new \Exception($message, 500);
          }
          $rawUploadedFile = $_FILES['restoreFile'];
          $this->TempFolder = $this->CreateEmptyTempFolder();
          $this->RestoreFile = new \SplFileInfo($this->TempFolder."/" . $rawUploadedFile['name']);
          LoggerUtils::getAppLogger()->debug("Moving ".$rawUploadedFile['tmp_name']. " to ". $this->RestoreFile);
          move_uploaded_file($rawUploadedFile['tmp_name'], $this->RestoreFile);
          LoggerUtils::getAppLogger()->debug("File move complete");
          $this->DiscoverBackupType();
          LoggerUtils::getAppLogger()->debug("Detected backup type: " . $this->BackupType);
          LoggerUtils::getAppLogger()->info("Restore job created; ready to execute");
      }

      private function DecryptBackup()
      {
          LoggerUtils::getAppLogger()->info("Decrypting file: " . $this->RestoreFile);
          $this->restorePassword = InputUtils::FilterString($_POST['restorePassword']);
          $tempfile = new \SplFileInfo($this->RestoreFile->getPathname()."temp");

          try {
              File::decryptFileWithPassword($this->RestoreFile, $tempfile, $this->restorePassword);
              rename($tempfile, $this->RestoreFile);
              LoggerUtils::getAppLogger()->info("File decrypted");
          } catch (\Defuse\Crypto\Exception\WrongKeyOrModifiedCiphertextException $ex) {
              if ($ex->getMessage() == 'Bad version header.') {
                  LoggerUtils::getAppLogger()->info("Bad version header; this file probably wasn't encrypted");
              } else {
                  LoggerUtils::getAppLogger()->error($ex->getMessage());
                  throw $ex;
              }
          }
      }
      private function DiscoverBackupType()
      {
          switch ($this->RestoreFile->getExtension()) {
          case "gz":
            $basename = $this->RestoreFile->getBasename();
            if (substr($basename, strlen($basename)-6, 6) == "tar.gz") {
                $this->BackupType = BackupType::FullBackup;
            } elseif (substr($basename, strlen($basename)-6, 6) == "sql.gz") {
                $this->BackupType = BackupType::GZSQL;
            }
            break;
          case "sql":
            $this->BackupType = BackupType::SQL;
            break;
        }
      }

      private function RestoreSQLBackup($SQLFileInfo)
      {
          $connection = Propel::getConnection();
          LoggerUtils::getAppLogger()->debug("Restoring SQL file from: ".$SQLFileInfo);
          SQLUtils::sqlImport($SQLFileInfo, $connection);
          LoggerUtils::getAppLogger()->debug("Finished restoring SQL table");
      }

      private function RestoreFullBackup()
      {
          LoggerUtils::getAppLogger()->debug("Restoring full archive");
          $phar = new PharData($this->RestoreFile);
          LoggerUtils::getAppLogger()->debug("Extracting " . $this->RestoreFile . " to ". $this->TempFolder);
          $phar->extractTo($this->TempFolder);
          LoggerUtils::getAppLogger()->debug("Finished exctraction");
          $sqlFile =  $this->TempFolder."/ChurchCRM-Database.sql";
          if (file_exists($sqlFile)) {
              $this->RestoreSQLBackup($sqlFile);
              LoggerUtils::getAppLogger()->debug("Removing images from live instance");
              FileSystemUtils::recursiveRemoveDirectory(SystemURLs::getDocumentRoot() . '/Images');
              LoggerUtils::getAppLogger()->debug("Removal complete; Copying restored images to live instance");
              FileSystemUtils::recursiveCopyDirectory($this->TempFolder. '/Images/', SystemURLs::getImagesRoot());
              LoggerUtils::getAppLogger()->debug("Finished copying images");
          } else {
              FileSystemUtils::recursiveRemoveDirectory($restoreResult->backupDir, true);
              throw new Exception(gettext("Backup archive does not contain a database").": " .$this->RestoreFile);
          }
          LoggerUtils::getAppLogger()->debug("Finished restoring full archive");
      }

      private function RestoreGZSQL()
      {
          LoggerUtils::getAppLogger()->debug("Decompressing gzipped SQL file: ". $this->RestoreFile);
          $gzf = gzopen($this->RestoreFile, 'r');
          $buffer_size = 4096;
          $SqlFile = new \SplFileInfo($this->TempFolder."/".'ChurchCRM-Database.sql');
          $out_file = fopen($SqlFile, 'wb');
          while (!gzeof($gzf)) {
              fwrite($out_file, gzread($gzf, $buffer_size));
          }
          fclose($out_file);
          gzclose($gzf);
          $this->RestoreSQLBackup($SqlFile);
          unlink($this->RestoreFile);
          unlink($SqlFile->getPathname());
      }

      private function PostRestoreCleanup()
      {
          //When restoring a database, do NOT let the database continue to create remote backups.
          //This can be very troublesome for users in a testing environment.
          LoggerUtils::getAppLogger()->debug("Starting post-restore cleanup");
          SystemConfig::setValue('bEnableExternalBackupTarget', '0');
          array_push($this->Messages, gettext('As part of the restore, external backups have been disabled.  If you wish to continue automatic backups, you must manuall re-enable the bEnableExternalBackupTarget setting.'));
          SystemConfig::setValue('sLastIntegrityCheckTimeStamp', null);
          LoggerUtils::getAppLogger()->debug("Reset System Settings for: bEnableExternalBackupTarget and sLastIntegrityCheckTimeStamp");
      }

      public function Execute()
      {
          LoggerUtils::getAppLogger()->info("Executing restore job");
          try {
              $this->DecryptBackup();
              switch ($this->BackupType) {
              case BackupType::SQL:
                $this->RestoreSQLBackup($this->RestoreFile);
                break;
              case BackupType::GZSQL:
                $this->RestoreGZSQL();
                break;
              case BackupType::FullBackup:
                $this->RestoreFullBackup();
                break;
            }
              $this->PostRestoreCleanup();
              LoggerUtils::getAppLogger()->info("Finished executing restore job.  Cleaning out temp folder.");
          } catch (Exception $ex) {
              LoggerUtils::getAppLogger()->error("Error restoring backup: " . $ex);
          }
          $this->TempFolder = $this->CreateEmptyTempFolder();
      }
  }


  class BackupDownloader
  {
      public static function DownloadBackup($filename)
      {
          $path = SystemURLs::getDocumentRoot() . "/tmp_attach/ChurchCRMBackups/$filename";
          LoggerUtils::getAppLogger()->info("Download requested for :" . $path);
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
                  LoggerUtils::getAppLogger()->debug("Headers sent. sending backup file contents");
                  while (!feof($fd)) {
                      $buffer = fread($fd, 2048);
                      echo $buffer;
                  }
                  LoggerUtils::getAppLogger()->debug("Backup file contents sent");
              }
              fclose($fd);
              FileSystemUtils::recursiveRemoveDirectory(SystemURLs::getDocumentRoot() . '/tmp_attach/', true);
              LoggerUtils::getAppLogger()->debug("Removed backup file from server filesystem");
          } else {
              $message = "Requested download does not exist: " . $path;
              LoggerUtils::getAppLogger()->error($message);
              throw new \Exception($message, 500);
          }
      }
  }


}
