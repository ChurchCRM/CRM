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
            try {
                // This can fail with an exception if the currently running software is "not current"
                // but there are no more available releases.
                // this exception will really only happen when running development versions of the software
                // or if the ChurchCRM Release on which the current instance is running has been deleted
                $data['newVersion'] = ChurchCRMReleaseManager::getNextReleaseStep($installedVersion);
            } catch (\Exception $e) {
                LoggerUtils::getAppLogger()->debug($e);
            }
        }

        return $data;
    }
}
