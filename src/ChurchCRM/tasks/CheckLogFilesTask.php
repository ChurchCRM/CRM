<?php

namespace ChurchCRM\Tasks;

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\dto\SystemURLs;

class CheckLogFilesTask implements TaskInterface
{
    private int $logFileCount;
    private const LOG_FILE_THRESHOLD = 100;

    public function __construct()
    {
        $logsPath = SystemURLs::getDocumentRoot() . '/logs/';
        $this->logFileCount = 0;

        if (is_dir($logsPath)) {
            $files = scandir($logsPath);
            foreach ($files as $file) {
                if ($file !== '.' && $file !== '..' && $file !== '.htaccess' && pathinfo($file, PATHINFO_EXTENSION) === 'log') {
                    $this->logFileCount++;
                }
            }
        }
    }

    public function isActive(): bool
    {
        return AuthenticationManager::getCurrentUser()->isAdmin() && $this->logFileCount > self::LOG_FILE_THRESHOLD;
    }

    public function isAdmin(): bool
    {
        return true;
    }

    public function getLink(): string
    {
        return SystemURLs::getRootPath() . '/v2/admin/logs';
    }

    public function getTitle(): string
    {
        return gettext('Too Many Log Files') . ' (' . $this->logFileCount . ')';
    }

    public function getDesc(): string
    {
        return gettext('The logs directory contains many log files. Clean up old log files to free up disk space.');
    }
}
