<?php

namespace ChurchCRM\Tasks;

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Utils\MiscUtils;

class IntegrityCheckTask implements TaskInterface
{
    private $integrityCheckData;

    public function __construct()
    {
        $integrityCheckPath = SystemURLs::getDocumentRoot() . '/integrityCheck.json';
        if (is_file($integrityCheckPath)) {
            $integrityCheckContents = file_get_contents($integrityCheckPath);
            MiscUtils::throwIfFailed($integrityCheckContents);

            $this->integrityCheckData = json_decode($integrityCheckContents, null, 512, JSON_THROW_ON_ERROR);
        }
    }

    public function isActive(): bool
    {
        return AuthenticationManager::getCurrentUser()->isAdmin() && ($this->integrityCheckData == null || $this->integrityCheckData->status == 'failure');
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
        return gettext('Application Integrity Check Failed');
    }

    public function getDesc(): string
    {
        return gettext('Application Integrity Check Failed');
    }
}
