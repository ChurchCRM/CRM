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
        $hasIntegrityFailure = $this->integrityCheckData == null || $this->integrityCheckData->status == 'failure';
        
        return AuthenticationManager::getCurrentUser()->isAdmin() && $hasIntegrityFailure;
    }

    public function isAdmin(): bool
    {
        return true;
    }

    public function getLink(): string
    {
        return SystemURLs::getRootPath() . '/admin/system/debug';
    }

    public function getTitle(): string
    {
        return gettext('Application Integrity Check Failed');
    }

    public function getDesc(): string
    {
        return gettext('Some system files have been modified or are missing. Review the debug page for details.');
    }
}
