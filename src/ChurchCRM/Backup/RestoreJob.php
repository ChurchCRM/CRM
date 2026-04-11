<?php

namespace ChurchCRM\Backup;

use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Utils\FileSystemUtils;
use ChurchCRM\Service\SystemService;
use ChurchCRM\Utils\SQLUtils;
use ChurchCRM\Utils\LoggerUtils;
use Exception;
use PharData;
use Propel\Runtime\Propel;

class RestoreJob extends JobBase
{
    private \SplFileInfo $RestoreFile;
    public array $Messages = [];

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
        
        // Security: Validate file extension and MIME type before processing
        $sanitizedFilename = $this->sanitizeUploadedFilename($rawUploadedFile['name']);
        $this->validateUploadedFile($rawUploadedFile['tmp_name']);
        
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
        
        // Validate against allowed backup file patterns (strict whitelist)
        if (!preg_match('/^[\w\-\.]+\.(sql|sql\.gz|tar\.gz)$/i', $filename)) {
            LoggerUtils::getAppLogger()->warning('Blocked invalid backup filename: ' . $filename);
            throw new \Exception('Invalid backup file. Only .sql, .sql.gz, .tar.gz files are allowed.', 400);
        }
        
        return $filename;
    }

    /**
     * Validate uploaded file MIME type
     * Files are stored in temp directory and cannot be executed
     *
     * @param string $tmpPath Path to temporary uploaded file
     * @throws \Exception If file MIME type is invalid
     */
    private function validateUploadedFile(string $tmpPath): void
    {
        // Validate MIME type to prevent web shell uploads
        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = finfo_file($finfo, $tmpPath);
            
            // Allow common backup file MIME types
            $allowedMimeTypes = ['application/gzip', 'application/x-gzip', 'text/plain', 'text/x-sql', 'application/sql', 'application/x-tar'];
            if (!in_array($mimeType, $allowedMimeTypes)) {
                LoggerUtils::getAppLogger()->warning('Blocked file with invalid MIME type: ' . $mimeType);
                throw new \Exception('Invalid file type. The uploaded file does not appear to be a valid backup file.', 400);
            }
        }
    }

    private function discoverBackupType(): void
    {
        $basename = $this->RestoreFile->getBasename();
        
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

        try {
            $sqlFile = $extractDir . '/ChurchCRM-Database.sql';
            if (file_exists($sqlFile)) {
                $this->restoreSQLBackup($sqlFile);

                // Security: Validate extracted images before copying to live webroot
                $imagesDir = $extractDir . '/Images';
                if (is_dir($imagesDir)) {
                    $this->validateExtractedImages($imagesDir);
                }

                LoggerUtils::getAppLogger()->debug('Removing images from live instance');
                FileSystemUtils::recursiveRemoveDirectory(SystemURLs::getDocumentRoot() . '/Images');
                LoggerUtils::getAppLogger()->debug('Removal complete; Copying restored images to live instance');
                FileSystemUtils::recursiveCopyDirectory($extractDir . '/Images/', SystemURLs::getImagesRoot());
                LoggerUtils::getAppLogger()->debug('Finished copying images');
            } else {
                throw new Exception(gettext('Backup archive does not contain a database') . ': ' . $this->RestoreFile);
            }
        } finally {
            // Always clean up extraction directory, even on validation failure
            FileSystemUtils::recursiveRemoveDirectory($extractDir, false);
            LoggerUtils::getAppLogger()->debug('Cleaned up extraction directory');
        }
        LoggerUtils::getAppLogger()->debug('Finished restoring full archive');
    }

    /**
     * Validate that extracted Images directory contains only image files.
     * Prevents RCE by removing any PHP/script files that may have been
     * embedded in a malicious backup archive.
     *
     * @param string $dir Path to the extracted Images directory
     * @throws Exception If executable PHP files are found (aborts restore)
     */
    private function validateExtractedImages(string $dir): void
    {
        // Aligned with Photo.php allowed types — no SVG (XSS risk) or BMP
        $allowedMimeTypes = [
            'image/jpeg',
            'image/jpg',
            'image/png',
            'image/gif',
            'image/webp',
        ];

        // Executable extensions that must never be copied to the webroot
        $dangerousExtensions = ['php', 'phtml', 'php3', 'php4', 'php5', 'php7', 'phps', 'phar', 'shtml'];

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::LEAVES_ONLY
        );

        // Create finfo instance once outside loop
        $finfo = new \finfo(FILEINFO_MIME_TYPE);

        foreach ($iterator as $file) {
            if (!$file->isFile()) {
                continue;
            }

            $filePath = $file->getPathname();
            $extension = strtolower($file->getExtension());

            // Immediately abort if any executable file is found
            if (\in_array($extension, $dangerousExtensions, true)) {
                LoggerUtils::getAppLogger()->error('Restore aborted: dangerous file found in backup archive: ' . $filePath);
                throw new Exception('Restore aborted: backup archive contains a potentially dangerous file (' . $file->getFilename() . '). This may indicate a compromised backup.');
            }

            // Check MIME type for all other files — remove non-images
            $mimeType = $finfo->file($filePath);
            if (!\in_array($mimeType, $allowedMimeTypes, true)) {
                LoggerUtils::getAppLogger()->warning('Restore: removing non-image file from backup: ' . $filePath . ' (MIME: ' . $mimeType . ')');
                if (!unlink($filePath)) {
                    // Cannot remove non-image file — abort to prevent it reaching the webroot
                    throw new Exception('Restore aborted: could not remove non-image file from backup (' . $file->getFilename() . '). Check file permissions.');
                }
            }
        }

        LoggerUtils::getAppLogger()->debug('Image validation passed for: ' . $dir);
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
        
        // Disable the external backup plugin if it was enabled
        try {
            SystemConfig::setValue('plugin.external-backup.enabled', '0');
            $this->Messages[] = gettext('As part of the restore, external backups have been disabled. If you wish to continue automatic backups, re-enable the External Backup plugin in Admin > Plugins.');
            LoggerUtils::getAppLogger()->debug('Reset System Settings for: plugin.external-backup.enabled');
        } catch (\Throwable $e) {
            // Config key might not exist in restored database - that's OK
            LoggerUtils::getAppLogger()->debug('Could not disable external backup plugin (config may not exist): ' . $e->getMessage());
        }

        // Rebuild views to ensure they are current after restore
        $this->rebuildViews();
    }

    private function rebuildViews(): void
    {
        LoggerUtils::getAppLogger()->debug('Rebuilding database views after restore');
        $connection = Propel::getConnection();
        $viewsFile = SystemURLs::getDocumentRoot() . '/mysql/upgrade/rebuild_views.sql';
        if (file_exists($viewsFile)) {
            SQLUtils::sqlImport($viewsFile, $connection);
            LoggerUtils::getAppLogger()->debug('Database views rebuilt successfully');
        } else {
            LoggerUtils::getAppLogger()->warning('Could not find rebuild_views.sql at: ' . $viewsFile);
        }
    }

    public function execute(): void
    {
        LoggerUtils::getAppLogger()->info('Executing restore job');

        try {
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
