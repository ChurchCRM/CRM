<?php

namespace ChurchCRM\Backup;

use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\FileSystemUtils;
use ChurchCRM\Service\SystemService;
use ChurchCRM\SQLUtils;
use ChurchCRM\Utils\InputUtils;
use ChurchCRM\Utils\LoggerUtils;
use Defuse\Crypto\Exception\WrongKeyOrModifiedCiphertextException;
use Defuse\Crypto\File;
use Exception;
use PharData;
use Propel\Runtime\Propel;

class RestoreJob extends JobBase
{
    private \SplFileInfo $RestoreFile;
    public array $Messages = [];
    private ?string $restorePassword = null;

    private function isIncomingFileFailed(): bool
    {
        // Not actually sure what this is supposed to do, but it was working before??
        return $_SERVER['REQUEST_METHOD'] == 'POST' && empty($_POST) && empty($_FILES) && $_SERVER['CONTENT_LENGTH'] > 0;
    }

    public function __construct()
    {
        LoggerUtils::getAppLogger()->info('Beginning to process incoming archive for restoration');
        if ($this->isIncomingFileFailed()) {
            $message = 'The selected file exceeds this servers maximum upload size of: ' . SystemService::getMaxUploadFileSize();
            LoggerUtils::getAppLogger()->error($message);

            throw new \Exception($message, 500);
        }
        $rawUploadedFile = $_FILES['restoreFile'];
        
        // Security: Sanitize the uploaded filename to prevent path traversal and malicious file uploads
        $sanitizedFilename = $this->sanitizeUploadedFilename($rawUploadedFile['name']);
        
        $this->RestoreFile = new \SplFileInfo(sys_get_temp_dir() . '/' . $sanitizedFilename);
        LoggerUtils::getAppLogger()->debug('Moving ' . $rawUploadedFile['tmp_name'] . ' to ' . $this->RestoreFile);
        move_uploaded_file($rawUploadedFile['tmp_name'], $this->RestoreFile);
        LoggerUtils::getAppLogger()->debug('File move complete');
        $this->discoverBackupType();
        LoggerUtils::getAppLogger()->debug('Detected backup type: ' . $this->BackupType);
        LoggerUtils::getAppLogger()->info('Restore job created; ready to execute');
    }

    /**
     * Sanitize uploaded filename to prevent path traversal and malicious file uploads.
     *
     * @param string $filename The original filename from the upload
     * @return string Sanitized filename
     * @throws \Exception If the filename is invalid or has a disallowed extension
     */
    private function sanitizeUploadedFilename(string $filename): string
    {
        // Use basename to strip any path components (prevents path traversal)
        $filename = basename($filename);
        
        // Validate against allowed backup file patterns (including encrypted .enc files)
        if (!preg_match('/^[\w\-\.]+\.(sql|sql\.gz|tar\.gz)(\.enc)?$/i', $filename)) {
            LoggerUtils::getAppLogger()->warning('Blocked invalid backup filename: ' . $filename);
            throw new \Exception('Invalid backup file. Only .sql, .sql.gz, .tar.gz files (optionally with .enc) are allowed.', 400);
        }
        
        return $filename;
    }

    private function decryptBackup(): void
    {
        // Check if file is encrypted (has .enc extension)
        $basename = $this->RestoreFile->getBasename();
        $isEncryptedFile = str_ends_with($basename, '.enc');
        
        $this->restorePassword = InputUtils::filterString($_POST['restorePassword']);
        
        // Only decrypt if file has .enc extension - the extension is the source of truth
        if (!$isEncryptedFile) {
            LoggerUtils::getAppLogger()->debug('File does not have .enc extension, skipping decryption');
            return;
        }
        
        // Encrypted file requires a password
        if (empty($this->restorePassword)) {
            throw new \Exception('Encrypted backup file requires a password to restore.', 400);
        }
        
        LoggerUtils::getAppLogger()->info('Decrypting file: ' . $this->RestoreFile);
        
        // Determine the decrypted filename (remove .enc if present)
        $decryptedPath = $isEncryptedFile 
            ? substr($this->RestoreFile->getPathname(), 0, -4)  // Remove .enc
            : $this->RestoreFile->getPathname();
        
        $tempfile = new \SplFileInfo($this->RestoreFile->getPathname() . '.tmp');

        try {
            File::decryptFileWithPassword($this->RestoreFile, $tempfile, $this->restorePassword);
            unlink($this->RestoreFile);  // Remove encrypted file
            rename($tempfile, $decryptedPath);  // Rename to proper extension
            $this->RestoreFile = new \SplFileInfo($decryptedPath);
            LoggerUtils::getAppLogger()->info('File decrypted to: ' . $this->RestoreFile);
        } catch (WrongKeyOrModifiedCiphertextException $ex) {
            if ($ex->getMessage() == 'Bad version header.') {
                LoggerUtils::getAppLogger()->info("Bad version header; this file probably wasn't encrypted");
                // Clean up temp file if it exists
                if (file_exists($tempfile->getPathname())) {
                    unlink($tempfile->getPathname());
                }
            } else {
                LoggerUtils::getAppLogger()->error('Decryption failed: ' . $ex->getMessage());
                throw $ex;
            }
        }
    }

    private function discoverBackupType(): void
    {
        $basename = $this->RestoreFile->getBasename();
        
        // Check for encrypted files first (ends with .enc)
        if (str_ends_with($basename, '.enc')) {
            // Remove .enc to determine underlying type
            $innerBasename = substr($basename, 0, -4);
            if (str_ends_with($innerBasename, '.tar.gz')) {
                $this->BackupType = BackupType::FULL_BACKUP;
            } elseif (str_ends_with($innerBasename, '.sql.gz')) {
                $this->BackupType = BackupType::GZSQL;
            } elseif (str_ends_with($innerBasename, '.sql')) {
                $this->BackupType = BackupType::SQL;
            }
            return;
        }
        
        // Handle non-encrypted files
        switch ($this->RestoreFile->getExtension()) {
            case 'gz':
                if (str_ends_with($basename, 'tar.gz')) {
                    $this->BackupType = BackupType::FULL_BACKUP;
                } elseif (str_ends_with($basename, 'sql.gz')) {
                    $this->BackupType = BackupType::GZSQL;
                }
                break;
            case 'sql':
                $this->BackupType = BackupType::SQL;
                break;
        }
    }

    private function restoreSQLBackup(string $SQLFileInfo): void
    {
        $connection = Propel::getConnection();
        LoggerUtils::getAppLogger()->debug('Restoring SQL file from: ' . $SQLFileInfo);
        SQLUtils::sqlImport($SQLFileInfo, $connection);
        LoggerUtils::getAppLogger()->debug('Finished restoring SQL table');
    }

    private function restoreFullBackup(): void
    {
        LoggerUtils::getAppLogger()->debug('Restoring full archive');
        $phar = new PharData($this->RestoreFile);
        
        // Create a unique extraction directory for full backup restore
        $extractDir = sys_get_temp_dir() . '/churchcrm_restore_' . bin2hex(random_bytes(8));
        mkdir($extractDir, 0750, true);
        
        LoggerUtils::getAppLogger()->debug('Extracting ' . $this->RestoreFile . ' to ' . $extractDir);
        $phar->extractTo($extractDir);
        LoggerUtils::getAppLogger()->debug('Finished extraction');
        $sqlFile = $extractDir . '/ChurchCRM-Database.sql';
        if (file_exists($sqlFile)) {
            $this->restoreSQLBackup($sqlFile);
            LoggerUtils::getAppLogger()->debug('Removing images from live instance');
            FileSystemUtils::recursiveRemoveDirectory(SystemURLs::getDocumentRoot() . '/Images');
            LoggerUtils::getAppLogger()->debug('Removal complete; Copying restored images to live instance');
            FileSystemUtils::recursiveCopyDirectory($extractDir . '/Images/', SystemURLs::getImagesRoot());
            LoggerUtils::getAppLogger()->debug('Finished copying images');
        } else {
            throw new Exception(gettext('Backup archive does not contain a database') . ': ' . $this->RestoreFile);
        }
        
        // Clean up extraction directory
        FileSystemUtils::recursiveRemoveDirectory($extractDir, false);
        LoggerUtils::getAppLogger()->debug('Finished restoring full archive');
    }

    private function restoreGZSQL(): void
    {
        LoggerUtils::getAppLogger()->debug('Decompressing gzipped SQL file: ' . $this->RestoreFile);
        $sqlFile = sys_get_temp_dir() . '/ChurchCRM-Database.sql';
        file_put_contents($sqlFile, gzdecode(file_get_contents($this->RestoreFile)));
        $this->restoreSQLBackup($sqlFile);
        unlink($sqlFile);
    }

    private function postRestoreCleanup(): void
    {
        //When restoring a database, do NOT let the database continue to create remote backups.
        //This can be very troublesome for users in a testing environment.
        LoggerUtils::getAppLogger()->debug('Starting post-restore cleanup');
        SystemConfig::setValue('bEnableExternalBackupTarget', '0');
        $this->Messages[] = gettext('As part of the restore, external backups have been disabled.  If you wish to continue automatic backups, you must manually re-enable the bEnableExternalBackupTarget setting.');
        SystemConfig::setValue('sLastIntegrityCheckTimeStamp', null);
        LoggerUtils::getAppLogger()->debug('Reset System Settings for: bEnableExternalBackupTarget and sLastIntegrityCheckTimeStamp');
    }

    public function execute(): void
    {
        LoggerUtils::getAppLogger()->info('Executing restore job');

        try {
            $this->decryptBackup();
            switch ($this->BackupType) {
                case BackupType::SQL:
                    $this->restoreSQLBackup($this->RestoreFile);
                    break;
                case BackupType::GZSQL:
                    $this->restoreGZSQL();
                    break;
                case BackupType::FULL_BACKUP:
                    $this->restoreFullBackup();
                    break;
            }
            $this->postRestoreCleanup();
            LoggerUtils::getAppLogger()->info('Finished executing restore job.');
        } catch (Exception $ex) {
            LoggerUtils::getAppLogger()->error('Error restoring backup: ' . $ex);
        } finally {
            // Clean up the uploaded restore file
            if (file_exists($this->RestoreFile->getPathname())) {
                unlink($this->RestoreFile->getPathname());
                LoggerUtils::getAppLogger()->debug('Cleaned up restore file: ' . $this->RestoreFile->getPathname());
            }
        }
    }
}
