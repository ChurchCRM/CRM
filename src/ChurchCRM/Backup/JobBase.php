<?php

namespace ChurchCRM\Backup;

use ChurchCRM\dto\SystemURLs;
use ChurchCRM\FileSystemUtils;
use ChurchCRM\Utils\LoggerUtils;

class JobBase
{
    /** @var BackupType */
    protected $BackupType;

    /**
     * @var string
     */
    protected $TempFolder;

    protected function createEmptyTempFolder()
    {
        // both backup and restore operations require a clean temporary working folder.  Create it.
        $TempFolder = SystemURLs::getDocumentRoot() . '/tmp_attach/ChurchCRMBackups';

        LoggerUtils::getAppLogger()->debug('Removing temp folder tree at ' . $TempFolder);
        FileSystemUtils::recursiveRemoveDirectory($TempFolder, false);

        LoggerUtils::getAppLogger()->debug('Creating temp folder at ' . $TempFolder);
        mkdir($TempFolder, 0750, true);
        LoggerUtils::getAppLogger()->debug('Temp folder created');

        return $TempFolder;
    }
}
