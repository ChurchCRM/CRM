<?php

namespace ChurchCRM\Backup;

use ChurchCRM\Bootstrapper;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Utils\ExecutionTime;
use ChurchCRM\Utils\LoggerUtils;
use ChurchCRM\Utils\VersionUtils;
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
    public string $BackupDownloadFileName;

    public function __construct(string $BaseName, string $BackupType)
    {
        $this->BackupType = $BackupType;
        $this->BackupFileBaseName = sys_get_temp_dir() . '/' . $BaseName;
        LoggerUtils::getAppLogger()->debug(
            "Backup job created; ready to execute: Type: '" .
                $this->BackupType .
                "' BaseName: '" . $this->BackupFileBaseName . "'"
        );
    }

    public function copyToWebDAV(string $Endpoint, string $Username, string $Password): bool
    {
        LoggerUtils::getAppLogger()->info('Beginning to copy backup to: ' . $Endpoint);

        try {
            // Ensure endpoint ends with / for proper URL construction
            $normalizedEndpoint = rtrim($Endpoint, '/') . '/';
            $remoteUrl = $normalizedEndpoint . urlencode($this->BackupFile->getFilename());
            LoggerUtils::getAppLogger()->debug('Full remote URL: ' . $remoteUrl);

            // Get file size for headers and streaming
            $fileSize = filesize($this->BackupFile->getPathname());
            if ($fileSize === false) {
                throw new \Exception('Failed to get backup file size');
            }

            // Open file for streaming upload (avoids loading entire file into memory)
            $fileHandle = fopen($this->BackupFile->getPathname(), 'rb');
            if ($fileHandle === false) {
                throw new \Exception('Failed to open backup file for reading');
            }

            $ch = curl_init($remoteUrl);
            // Use CURLAUTH_ANY to let cURL negotiate the best auth method (Basic or Digest)
            curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_ANY);
            curl_setopt($ch, CURLOPT_USERPWD, $Username . ':' . $Password);
            curl_setopt($ch, CURLOPT_PUT, true);
            curl_setopt($ch, CURLOPT_INFILE, $fileHandle);
            curl_setopt($ch, CURLOPT_INFILESIZE, $fileSize);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/octet-stream',
                'User-Agent: ChurchCRM/' . VersionUtils::getInstalledVersion(),
            ]);
            curl_setopt($ch, CURLOPT_TIMEOUT, 300); // 5 minute timeout for large files
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true); // Follow redirects

            LoggerUtils::getAppLogger()->debug('Beginning to stream file (' . $fileSize . ' bytes)');
            $time = new ExecutionTime();
            $result = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $effectiveUrl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
            $authUsed = curl_getinfo($ch, CURLINFO_HTTPAUTH_AVAIL);
            $error_msg = curl_error($ch);
            curl_close($ch);
            fclose($fileHandle);

            LoggerUtils::getAppLogger()->debug('cURL info - HTTP: ' . $httpCode . ', Auth available: ' . $authUsed . ', Effective URL: ' . $effectiveUrl);

            if (!empty($error_msg)) {
                throw new \Exception('Error backing up to remote: ' . $error_msg);
            }

            // HTTP 201 Created or 204 No Content are success for WebDAV PUT
            $success = in_array($httpCode, [200, 201, 204], true);
            if (!$success) {
                throw new \Exception('WebDAV upload failed with HTTP ' . $httpCode);
            }

            LoggerUtils::getAppLogger()->debug('File send complete. Took: ' . $time->getMilliseconds() . 'ms. HTTP: ' . $httpCode);

            return true;
        } catch (\Exception $e) {
            LoggerUtils::getAppLogger()->error('Error copying backup: ' . $e);

            throw $e;
        }
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
        // Always exclude extraneous files (can be regenerated): initials or remote images
        $isExtraneousFile = strpos($ImageFile->getFileName(), '-initials') !== false ||
            strpos($ImageFile->getFileName(), '-remote') !== false;

        return $ImageFile->isFile() && !$isExtraneousFile;
    }

    private function createFullArchive(): void
    {
        $imagesAddedToArchive = [];
        $directoriesAddedToArchive = [];
        $this->BackupFile = new \SplFileInfo($this->BackupFileBaseName . '.tar');
        $phar = new PharData($this->BackupFile->getPathname());
        LoggerUtils::getAppLogger()->debug('Archive opened at: ' . $this->BackupFile->getPathname());

        $SqlFile = new \SplFileInfo(sys_get_temp_dir() . '/ChurchCRM-Database.sql');
        $this->captureSQLFile($SqlFile);
        $phar->addFile($SqlFile, 'ChurchCRM-Database.sql');
        LoggerUtils::getAppLogger()->debug('Database added to archive');
        $imageFiles = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(SystemURLs::getImagesRoot()), RecursiveIteratorIterator::SELF_FIRST);
        foreach ($imageFiles as $imageFile) {
            if ($imageFile->isDir()) {
                // Add directories to preserve structure, even if empty
                $localName = substr(str_replace(SystemURLs::getDocumentRoot(), '', $imageFile->getRealPath()), 1);
                if ($localName != 'Images' && $imageFile->getFilename() != '.' && $imageFile->getFilename() != '..') {
                    $phar->addEmptyDir($localName);
                    $directoriesAddedToArchive[] = $localName;
                }
            } elseif ($this->shouldBackupImageFile($imageFile)) {
                $localName = substr(str_replace(SystemURLs::getDocumentRoot(), '', $imageFile->getRealPath()), 1);
                $phar->addFile($imageFile->getRealPath(), $localName);
                $imagesAddedToArchive[] = $imageFile->getRealPath();
            }
        }
        LoggerUtils::getAppLogger()->debug('Directories added to archive: ' . join(';', $directoriesAddedToArchive));
        LoggerUtils::getAppLogger()->debug('Images files added to archive: ' . join(';', $imagesAddedToArchive));
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
        $SqlFile = new \SplFileInfo(sys_get_temp_dir() . '/ChurchCRM-Database.sql');
        $this->captureSQLFile($SqlFile);
        $this->BackupFile = new \SplFileInfo($this->BackupFileBaseName . '.sql.gz');
        $gzf = gzopen($this->BackupFile->getPathname(), 'w6');
        gzwrite($gzf, file_get_contents($SqlFile->getPathname()));
        gzclose($gzf);
        unlink($SqlFile->getPathname());
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
