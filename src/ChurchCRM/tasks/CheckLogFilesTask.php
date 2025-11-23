<?php

namespace ChurchCRM\Tasks;

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\dto\SystemConfig;

class CheckLogFilesTask implements TaskInterface
{
    private int $logFileCount;

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
        $threshold = (int) SystemConfig::getValue('iLogFileThreshold');
        return AuthenticationManager::getCurrentUser()->isAdmin() && $this->logFileCount > $threshold;
    }

    public function isAdmin(): bool
    {
        return true;
    }

    public function getLink(): string
    {
        return SystemURLs::getRootPath() . '/admin/system/logs';
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
