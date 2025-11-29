<?php

namespace ChurchCRM\Tasks;

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Service\AppIntegrityService;

class OrphanedFilesCheckTask implements TaskInterface
{
    private int $orphanedFilesCount;

    public function __construct()
    {
        $this->orphanedFilesCount = count(AppIntegrityService::getOrphanedFiles());
    }

    public function isActive(): bool
    {
        return AuthenticationManager::getCurrentUser()->isAdmin() && $this->orphanedFilesCount > 0;
    }

    public function isAdmin(): bool
    {
        return true;
    }

    public function getLink(): string
    {
        return SystemURLs::getRootPath() . '/admin/system/orphaned-files';
    }

    public function getTitle(): string
    {
        return sprintf(gettext('Security Warning: %d orphaned files detected'), $this->orphanedFilesCount);
    }

    public function getDesc(): string
    {
        return gettext('Orphaned files from previous versions were found. These should be reviewed and deleted for security.');
    }
}
