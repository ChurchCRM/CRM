<?php

namespace ChurchCRM\Dashboard;

use ChurchCRM\Utils\ChurchCRMReleaseManager;
use ChurchCRM\Utils\LoggerUtils;

class SystemUpdateMenuItem implements DashboardItemInterface
{
    public static function getDashboardItemName(): string
    {
        return 'SystemUpgrade';
    }

    public static function shouldInclude(string $PageName): bool
    {
        return true;
    }

    public static function getDashboardItemValue(): array
    {
        $data['newVersion'] = '';
        $installedVersion = ChurchCRMReleaseManager::getReleaseFromString($_SESSION['sSoftwareInstalledVersion']);
        $isCurrent = ChurchCRMReleaseManager::isReleaseCurrent($installedVersion);
        if (!$isCurrent) {
            // Get the next release step; will return null if no upgrade is available
            $nextRelease = ChurchCRMReleaseManager::getNextReleaseStep($installedVersion);
            if (null !== $nextRelease) {
                $data['newVersion'] = $nextRelease;
            }
        }

        return $data;
    }
}
