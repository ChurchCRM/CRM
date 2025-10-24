<?php

namespace ChurchCRM\Backup;

use ChurchCRM\dto\SystemURLs;
use ChurchCRM\FileSystemUtils;
use ChurchCRM\Utils\LoggerUtils;

class JobBase
{
    protected string $BackupType;
    protected string $TempFolder;

    protected function createEmptyTempFolder(): string
    {
        // both backup and restore operations require a clean temporary working folder.  Create it.
        $TempFolder = SystemURLs::getDocumentRoot() . '/tmp_attach/ChurchCRMBackups';

        LoggerUtils::getAppLogger()->debug('Removing temp folder tree at ' . $TempFolder);
        FileSystemUtils::recursiveRemoveDirectory($TempFolder, false);

        LoggerUtils::getAppLogger()->debug('Creating temp folder at ' . $TempFolder);
        if (!mkdir($TempFolder, 0750, true) && !is_dir($TempFolder)) {
            $error = error_get_last();
            $message = 'Failed to create backup directory at ' . $TempFolder;
            if ($error) {
                $message .= ': ' . $error['message'];
            }
            $message .= '. Please ensure the web server has write permissions to the parent directory.';
            LoggerUtils::getAppLogger()->error($message);

            throw new \Exception($message, 500);
        }
        LoggerUtils::getAppLogger()->debug('Temp folder created');

        return $TempFolder;
    }
}
