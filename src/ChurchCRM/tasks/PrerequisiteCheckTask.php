<?php

namespace ChurchCRM\Tasks;

use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Service\AppIntegrityService;

class PrerequisiteCheckTask implements TaskInterface
{
    public function isActive(): bool
    {
        return !AppIntegrityService::arePrerequisitesMet();
    }

    public function isAdmin(): bool
    {
        return true;
    }

    public function getLink(): string
    {
        return SystemURLs::getRootPath() . '/v2/admin/debug';
    }

    public function getTitle(): string
    {
        return gettext('Unmet Application Prerequisites');
    }

    public function getDesc(): string
    {
        return gettext('Unmet Application Prerequisites');
    }
}
