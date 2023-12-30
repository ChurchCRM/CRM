<?php

namespace ChurchCRM\Backup;

use ChurchCRM\Bootstrapper;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Utils\ExecutionTime;
use ChurchCRM\Utils\LoggerUtils;
use Defuse\Crypto\File;
use Exception;
use Ifsnop\Mysqldump\Mysqldump;
use PharData;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

class BackupJob extends JobBase
{
    private string $BackupFileBaseName;

    private ?\SplFileInfo $BackupFile = null;
    /**
     * @var bool
     */
    private $IncludeExtraneousFiles;
    /**
     * @var string
     */
    public $BackupDownloadFileName;
    /**
     * @var bool
     */
    public $shouldEncrypt;

    /**
     * @var string
     */
    public $BackupPassword;

    /**
     * @param string     $BaseName
     * @param BackupType $BackupType
     * @param bool       $IncludeExtraneousFiles
     */
    public function __construct(string $BaseName, $BackupType, $IncludeExtraneousFiles, $EncryptBackup, $BackupPassword)
    {
        $this->BackupType = $BackupType;
        $this->TempFolder = $this->createEmptyTempFolder();
        $this->BackupFileBaseName = $this->TempFolder . '/' . $BaseName;
        $this->IncludeExtraneousFiles = $IncludeExtraneousFiles;
        $this->shouldEncrypt = $EncryptBackup;
        $this->BackupPassword = $BackupPassword;
        LoggerUtils::getAppLogger()->debug(
            "Backup job created; ready to execute: Type: '" .
                $this->BackupType .
                "' Temp Folder: '" .
                $this->TempFolder .
                "' BaseName: '" . $this->BackupFileBaseName .
                "' Include extra files: '" . ($this->IncludeExtraneousFiles ? 'true' : 'false') . "'"
        );
    }

    public function copyToWebDAV(string $Endpoint, string $Username, string $Password)
    {
        LoggerUtils::getAppLogger()->info('Beginning to copy backup to: ' . $Endpoint);

        try {
            $fh = fopen($this->BackupFile->getPathname(), 'r');
            $remoteUrl = $Endpoint . urlencode($this->BackupFile->getFilename());
            LoggerUtils::getAppLogger()->debug('Full remote URL: ' . $remoteUrl);
            $credentials = $Username . ':' . $Password;
            $ch = curl_init($remoteUrl);
            curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_ANY);
            curl_setopt($ch, CURLOPT_USERPWD, $credentials);
            curl_setopt($ch, CURLOPT_PUT, true);
            curl_setopt($ch, CURLOPT_FAILONERROR, true);
            curl_setopt($ch, CURLOPT_INFILE, $fh);
            curl_setopt($ch, CURLOPT_INFILESIZE, $this->BackupFile->getSize());
            LoggerUtils::getAppLogger()->debug('Beginning to send file');
            $time = new ExecutionTime();
            $result = curl_exec($ch);
            if (curl_error($ch)) {
                $error_msg = curl_error($ch);
            }
            curl_close($ch);
            fclose($fh);

            if (isset($error_msg)) {
                throw new \Exception('Error backing up to remote: ' . $error_msg);
            }
            LoggerUtils::getAppLogger()->debug('File send complete.  Took: ' . $time->getMilliseconds() . 'ms');
        } catch (\Exception $e) {
            LoggerUtils::getAppLogger()->error('Error copying backup: ' . $e);
        }
        LoggerUtils::getAppLogger()->info('Backup copy completed.  Curl result: ' . $result);

        return $result;
    }

    private function captureSQLFile(\SplFileInfo $SqlFilePath): void
    {
        global $sSERVERNAME, $sDATABASE, $sUSER, $sPASSWORD;
        LoggerUtils::getAppLogger()->debug('Beginning to backup database to: ' . $SqlFilePath->getPathname());

        try {
            $dump = new Mysqldump(Bootstrapper::getDSN(), $sUSER, $sPASSWORD, ['add-drop-table' => true]);
            $dump->start($SqlFilePath->getPathname());
            LoggerUtils::getAppLogger()->debug('Finished backing up database to ' . $SqlFilePath->getPathname());
        } catch (\Exception $e) {
            $message = 'Failed to backup database to: ' . $SqlFilePath->getPathname() . ' Exception: ' . $e;
            LoggerUtils::getAppLogger()->error($message);

            throw new Exception($message, 500);
        }
    }

    private function shouldBackupImageFile(SplFileInfo $ImageFile): bool
    {
        $isExtraneousFile = strpos($ImageFile->getFileName(), '-initials') != false ||
        strpos($ImageFile->getFileName(), '-remote') != false ||
        strpos($ImageFile->getPathName(), 'thumbnails') != false;

        return $ImageFile->isFile() && !(!$this->IncludeExtraneousFiles && $isExtraneousFile); //todo: figure out this logic
    }

    private function createFullArchive(): void
    {
        $imagesAddedToArchive = [];
        $this->BackupFile = new \SplFileInfo($this->BackupFileBaseName . '.tar');
        $phar = new PharData($this->BackupFile->getPathname());
        LoggerUtils::getAppLogger()->debug('Archive opened at: ' . $this->BackupFile->getPathname());
        $phar->startBuffering();

        $SqlFile = new \SplFileInfo($this->TempFolder . '/' . 'ChurchCRM-Database.sql');
        $this->captureSQLFile($SqlFile);
        $phar->addFile($SqlFile, 'ChurchCRM-Database.sql');
        LoggerUtils::getAppLogger()->debug('Database added to archive');
        $imageFiles = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(SystemURLs::getImagesRoot()));
        foreach ($imageFiles as $imageFile) {
            if ($this->shouldBackupImageFile($imageFile)) {
                $localName = substr(str_replace(SystemURLs::getDocumentRoot(), '', $imageFile->getRealPath()), 1);
                $phar->addFile($imageFile->getRealPath(), $localName);
                $imagesAddedToArchive[] = $imageFile->getRealPath();
            }
        }
        LoggerUtils::getAppLogger()->debug('Images files added to archive: ' . join(';', $imagesAddedToArchive));
        $phar->stopBuffering();
        LoggerUtils::getAppLogger()->debug('Finished creating archive.  Beginning to compress');
        $phar->compress(\Phar::GZ);
        LoggerUtils::getAppLogger()->debug('Archive compressed; should now be a .gz file');
        unset($phar);
        unlink($this->BackupFile->getPathname());
        LoggerUtils::getAppLogger()->debug('Initial .tar archive deleted: ' . $this->BackupFile->getPathname());
        $this->BackupFile = new \SplFileInfo($this->BackupFileBaseName . '.tar.gz');
        LoggerUtils::getAppLogger()->debug('New backup file: ' . $this->BackupFile);
        unlink($SqlFile);
        LoggerUtils::getAppLogger()->debug('Temp Database backup deleted: ' . $SqlFile);
    }

    private function createGZSql(): void
    {
        $SqlFile = new \SplFileInfo($this->TempFolder . '/' . 'ChurchCRM-Database.sql');
        $this->captureSQLFile($SqlFile);
        $this->BackupFile = new \SplFileInfo($this->BackupFileBaseName . '.sql.gz');
        $gzf = gzopen($this->BackupFile->getPathname(), 'w6');
        gzwrite($gzf, file_get_contents($SqlFile->getPathname()));
        gzclose($gzf);
        unlink($SqlFile->getPathname());
    }

    private function encryptBackupFile(): void
    {
        LoggerUtils::getAppLogger()->info('Encrypting backup file: ' . $this->BackupFile);
        $tempFile = new \SplFileInfo($this->BackupFile->getPathname() . 'temp');
        rename($this->BackupFile, $tempFile);
        File::encryptFileWithPassword($tempFile, $this->BackupFile, $this->BackupPassword);
        LoggerUtils::getAppLogger()->info('Finished encrypting backup file');
    }

    public function execute(): bool
    {
        $time = new ExecutionTime();
        LoggerUtils::getAppLogger()->info('Beginning backup job. Type: ' . $this->BackupType . '. BaseName: ' . $this->BackupFileBaseName);
        if ($this->BackupType == BackupType::FULL_BACKUP) {
            $this->createFullArchive();
        } elseif ($this->BackupType == BackupType::SQL) {
            $this->BackupFile = new \SplFileInfo($this->BackupFileBaseName . '.sql');
            $this->captureSQLFile($this->BackupFile);
        } elseif ($this->BackupType == BackupType::GZSQL) {
            $this->createGZSql();
        }
        if ($this->shouldEncrypt) {
            $this->encryptBackupFile();
        }
        $time->end();
        $percentExecutionTime = (($time->getMilliseconds() / 1000) / ini_get('max_execution_time')) * 100;
        LoggerUtils::getAppLogger()->info('Completed backup job.  Took : ' . $time->getMilliseconds() . 'ms. ' . $percentExecutionTime . '% of max_execution_time');
        if ($percentExecutionTime > 80) {
            // if the backup took more than 80% of the max_execution_time, then write a warning to the log
            LoggerUtils::getAppLogger()->warning('Backup task took more than 80% of max_execution_time (' . ini_get('max_execution_time') . ').  Consider increasing this time to avoid a failure');
        }
        $this->BackupDownloadFileName = $this->BackupFile->getFilename();

        return true;
    }
}
