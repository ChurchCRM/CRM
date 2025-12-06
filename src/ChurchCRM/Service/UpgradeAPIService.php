<?php

namespace ChurchCRM\Service;

use ChurchCRM\Utils\ChurchCRMReleaseManager;

/**
 * UpgradeAPIService
 *
 * Provides API operations for system upgrade and update checking.
 * This service wraps ChurchCRMReleaseManager operations for admin API routes.
 * Admin authentication is enforced by AdminRoleAuthMiddleware at the application level.
 */
class UpgradeAPIService
{
    /**
     * Download the latest release from GitHub
     *
     * @return array Upgrade file information (fileName, fullPath, releaseNotes, sha1)
     * @throws \Exception
     */
    public static function downloadLatestRelease(): array
    {
        return ChurchCRMReleaseManager::downloadLatestRelease();
    }

    /**
     * Apply the upgrade with the given file and SHA1 hash
     *
     * @param string $fullPath Full path to upgrade file
     * @param string $sha1 SHA1 hash for verification
     * @return void
     * @throws \Exception
     */
    public static function doUpgrade(string $fullPath, string $sha1): void
    {
        ChurchCRMReleaseManager::doUpgrade($fullPath, $sha1);
    }

    /**
     * Refresh upgrade information from GitHub and update session state
     *
     * @return array Session update data (updateAvailable, updateVersion, latestVersion)
     * @throws \Exception
     */
    public static function refreshUpgradeInfo(): array
    {
        // Force fresh check from GitHub
        ChurchCRMReleaseManager::checkForUpdates();

        // Recompute whether an update is available
        $updateInfo = ChurchCRMReleaseManager::checkSystemUpdateAvailable();
        $_SESSION['systemUpdateAvailable'] = $updateInfo['available'];
        $_SESSION['systemUpdateVersion'] = $updateInfo['version'];
        $_SESSION['systemLatestVersion'] = $updateInfo['latestVersion'];

        // Return updated session data
        return [
            'updateAvailable' => $_SESSION['systemUpdateAvailable'] ?? false,
            'updateVersion' => isset($_SESSION['systemUpdateVersion']) ? $_SESSION['systemUpdateVersion']->__toString() : null,
            'latestVersion' => isset($_SESSION['systemLatestVersion']) ? $_SESSION['systemLatestVersion']->__toString() : null
        ];
    }
}
