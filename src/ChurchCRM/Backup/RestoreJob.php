<?php

namespace ChurchCRM\Backup;

use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\FileSystemUtils;
use ChurchCRM\Service\SystemService;
use ChurchCRM\SQLUtils;
use ChurchCRM\Utils\InputUtils;
use ChurchCRM\Utils\LoggerUtils;
use Defuse\Crypto\File;
use Exception;
use PharData;
use Propel\Runtime\Propel;

class RestoreJob extends JobBase
{
    private \SplFileInfo $RestoreFile;

    /**
     * @var array
     */
    public $Messages = [];
    /**
     * @var bool
     */
    private $IsBackupEncrypted;
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
        $this->TempFolder = $this->createEmptyTempFolder();
        $this->RestoreFile = new \SplFileInfo($this->TempFolder . '/' . $rawUploadedFile['name']);
        LoggerUtils::getAppLogger()->debug('Moving ' . $rawUploadedFile['tmp_name'] . ' to ' . $this->RestoreFile);
        move_uploaded_file($rawUploadedFile['tmp_name'], $this->RestoreFile);
        LoggerUtils::getAppLogger()->debug('File move complete');
        $this->discoverBackupType();
        LoggerUtils::getAppLogger()->debug('Detected backup type: ' . $this->BackupType);
        LoggerUtils::getAppLogger()->info('Restore job created; ready to execute');
    }

    private function decryptBackup(): void
    {
        LoggerUtils::getAppLogger()->info('Decrypting file: ' . $this->RestoreFile);
        $this->restorePassword = InputUtils::filterString($_POST['restorePassword']);
        $tempfile = new \SplFileInfo($this->RestoreFile->getPathname() . 'temp');

        try {
            File::decryptFileWithPassword($this->RestoreFile, $tempfile, $this->restorePassword);
            rename($tempfile, $this->RestoreFile);
            LoggerUtils::getAppLogger()->info('File decrypted');
        } catch (\Defuse\Crypto\Exception\WrongKeyOrModifiedCiphertextException $ex) {
            if ($ex->getMessage() == 'Bad version header.') {
                LoggerUtils::getAppLogger()->info("Bad version header; this file probably wasn't encrypted");
            } else {
                LoggerUtils::getAppLogger()->error($ex->getMessage());

                throw $ex;
            }
        }
    }

    private function discoverBackupType(): void
    {
        switch ($this->RestoreFile->getExtension()) {
            case 'gz':
                $basename = $this->RestoreFile->getBasename();
                if (substr($basename, strlen($basename) - 6, 6) == 'tar.gz') {
                    $this->BackupType = BackupType::FULL_BACKUP;
                } elseif (substr($basename, strlen($basename) - 6, 6) == 'sql.gz') {
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
        LoggerUtils::getAppLogger()->debug('Extracting ' . $this->RestoreFile . ' to ' . $this->TempFolder);
        $phar->extractTo($this->TempFolder);
        LoggerUtils::getAppLogger()->debug('Finished extraction');
        $sqlFile = $this->TempFolder . '/ChurchCRM-Database.sql';
        if (file_exists($sqlFile)) {
            $this->restoreSQLBackup($sqlFile);
            LoggerUtils::getAppLogger()->debug('Removing images from live instance');
            FileSystemUtils::recursiveRemoveDirectory(SystemURLs::getDocumentRoot() . '/Images');
            LoggerUtils::getAppLogger()->debug('Removal complete; Copying restored images to live instance');
            FileSystemUtils::recursiveCopyDirectory($this->TempFolder . '/Images/', SystemURLs::getImagesRoot());
            LoggerUtils::getAppLogger()->debug('Finished copying images');
        } else {
            throw new Exception(gettext('Backup archive does not contain a database') . ': ' . $this->RestoreFile);
        }
        LoggerUtils::getAppLogger()->debug('Finished restoring full archive');
    }

    private function restoreGZSQL(): void
    {
        LoggerUtils::getAppLogger()->debug('Decompressing gzipped SQL file: ' . $this->RestoreFile);
        $gzf = gzopen($this->RestoreFile, 'r');
        $buffer_size = 4096;
        $SqlFile = new \SplFileInfo($this->TempFolder . '/' . 'ChurchCRM-Database.sql');
        $out_file = fopen($SqlFile, 'wb');
        while (!gzeof($gzf)) {
            fwrite($out_file, gzread($gzf, $buffer_size));
        }
        fclose($out_file);
        gzclose($gzf);
        $this->restoreSQLBackup($SqlFile);
        unlink($this->RestoreFile);
        unlink($SqlFile->getPathname());
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
            LoggerUtils::getAppLogger()->info('Finished executing restore job.  Cleaning out temp folder.');
        } catch (Exception $ex) {
            LoggerUtils::getAppLogger()->error('Error restoring backup: ' . $ex);
        }
        $this->TempFolder = $this->createEmptyTempFolder();
    }
}
