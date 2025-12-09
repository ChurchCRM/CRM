<?php

namespace ChurchCRM\Utils;

use ChurchCRM\dto\ChurchCRMRelease;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\FileSystemUtils;
use ChurchCRM\Service\AppIntegrityService;
use ChurchCRM\Service\UpgradeService;
use ChurchCRM\Utils\ExecutionTime;
use ChurchCRM\Utils\LoggerUtils;
use ChurchCRM\Utils\MiscUtils;
use ChurchCRM\Utils\VersionUtils;
use Github\Client;

class ChurchCRMReleaseManager
{
    public const GITHUB_USER_NAME = 'ChurchCRM';
    public const GITHUB_REPOSITORY_NAME = 'CRM';

    /** @var bool true when an upgrade is in progress */
    private static ?bool $isUpgradeInProgress = null;

    public static function getReleaseFromString(string $releaseString): ChurchCRMRelease
    {
        if (empty($_SESSION['ChurchCRMReleases'])) {
            return new ChurchCRMRelease(@['name' => $releaseString]);
        } else {
            $requestedRelease = array_values(array_filter($_SESSION['ChurchCRMReleases'], fn ($r): bool => $r->__toString() === $releaseString));
            if (count($requestedRelease) === 1 && $requestedRelease[0] instanceof ChurchCRMRelease) {
                return $requestedRelease[0];
            } elseif (count($requestedRelease) === 0) {
                return new ChurchCRMRelease(@['name' => $releaseString]);
            } else {
                // This should _never_ happen.
                throw new \Exception('Provided string matched more than one ChurchCRM Release: ' . \json_encode($requestedRelease, JSON_THROW_ON_ERROR));
            }
        }
    }

    /**
     * @return ChurchCRMRelease[]
     */
    private static function populateReleases(): array
    {
        $client = new Client();
        $eligibleReleases = [];
        $allowPrerelease = SystemConfig::getBooleanValue('bAllowPrereleaseUpgrade');

        try {
            if ($allowPrerelease) {
                $gitHubReleases = $client->api('repo')->releases()->all(
                    ChurchCRMReleaseManager::GITHUB_USER_NAME,
                    ChurchCRMReleaseManager::GITHUB_REPOSITORY_NAME,
                    ['per_page' => 5, 'page' => 1]
                );

                foreach ($gitHubReleases as $r) {
                    $release = new ChurchCRMRelease($r);
                    if ($release->isPreRelease()) {
                        if ($allowPrerelease) {
                            $eligibleReleases[] = $release;
                        }
                    } else {
                        $eligibleReleases[] = $release;
                    }
                }
            } else {
                $latestRelease = $client->api('repo')->releases()->latest(
                    ChurchCRMReleaseManager::GITHUB_USER_NAME,
                    ChurchCRMReleaseManager::GITHUB_REPOSITORY_NAME
                );

                if (is_array($latestRelease) && !empty($latestRelease)) {
                    $release = new ChurchCRMRelease($latestRelease);
                    // Only cache stable releases; skip if latest is a prerelease
                    if (!$release->isPreRelease()) {
                        $eligibleReleases[] = $release;
                    }
                }
            }

            // Sort releases if multiple were fetched (prerelease mode)
            if ($allowPrerelease && count($eligibleReleases) > 1) {
                usort($eligibleReleases, fn (ChurchCRMRelease $a, ChurchCRMRelease $b): int => version_compare($b->__toString(), $a->__toString()));
            }
        } catch (\Exception $ex) {
            $errorMessage = $ex->getMessage();
            LoggerUtils::getAppLogger()->error('Error updating release metadata: ' . $errorMessage, ['exception' => $ex]);
        }

        return $eligibleReleases;
    }

    public static function checkForUpdates(): void
    {
        $logger = LoggerUtils::getAppLogger();
        $logger->info('=== checkForUpdates() CALLED ===');
        $_SESSION['ChurchCRMReleases'] = self::populateReleases();
        $logger->info('=== checkForUpdates() COMPLETE - ' . count($_SESSION['ChurchCRMReleases']) . ' releases cached ===');
    }

    public static function isReleaseCurrent(ChurchCRMRelease $Release): bool
    {
        if (empty($_SESSION['ChurchCRMReleases'])) {
            // If we don't have cached releases, populate them first
            $_SESSION['ChurchCRMReleases'] = self::populateReleases();
        }
        
        if (empty($_SESSION['ChurchCRMReleases'])) {
            // If still empty (no releases found), assume current
            return true;
        }
        
        $CurrentRelease = $_SESSION['ChurchCRMReleases'][0];
        $isEqual = $CurrentRelease->equals($Release);
        
        LoggerUtils::getAppLogger()->debug('isReleaseCurrent comparison: Release=' . $Release->__toString() 
            . ' (MAJOR=' . $Release->MAJOR . ' MINOR=' . $Release->MINOR . ' PATCH=' . $Release->PATCH . ')'
            . ' vs CurrentRelease=' . $CurrentRelease->__toString()
            . ' (MAJOR=' . $CurrentRelease->MAJOR . ' MINOR=' . $CurrentRelease->MINOR . ' PATCH=' . $CurrentRelease->PATCH . ')'
            . ' equals=' . ($isEqual ? 'true' : 'false'));

        return $isEqual;
    }

    private static function getHighestReleaseInArray(array $eligibleUpgradeTargetReleases)
    {
        if (count($eligibleUpgradeTargetReleases) > 0) {
            usort($eligibleUpgradeTargetReleases, fn (ChurchCRMRelease $a, ChurchCRMRelease $b): int => version_compare($b->__toString(), $a->__toString()));
            return $eligibleUpgradeTargetReleases[0];
        }

        return null;
    }

    private static function getReleaseNextPatch(array $rs, ChurchCRMRelease $currentRelease)
    {
        $eligibleUpgradeTargetReleases = array_values(array_filter($rs, function (ChurchCRMRelease $r) use ($currentRelease): bool {
            return ($r->MAJOR === $currentRelease->MAJOR) && ($r->MINOR === $currentRelease->MINOR) && ($r->PATCH > $currentRelease->PATCH);
        }));

        return self::getHighestReleaseInArray($eligibleUpgradeTargetReleases);
    }

    private static function getReleaseNextMinor(array $rs, ChurchCRMRelease $currentRelease)
    {
        $eligibleUpgradeTargetReleases = array_values(array_filter($rs, function (ChurchCRMRelease $r) use ($currentRelease): bool {
            return ($r->MAJOR === $currentRelease->MAJOR) && ($r->MINOR > $currentRelease->MINOR);
        }));

        return self::getHighestReleaseInArray($eligibleUpgradeTargetReleases);
    }

    private static function getReleaseNextMajor(array $rs, ChurchCRMRelease $currentRelease)
    {
        $eligibleUpgradeTargetReleases = array_values(array_filter($rs, function (ChurchCRMRelease $r) use ($currentRelease): bool {
            return $r->MAJOR > $currentRelease->MAJOR;
        }));

        return self::getHighestReleaseInArray($eligibleUpgradeTargetReleases);
    }

    public static function getNextReleaseStep(ChurchCRMRelease $currentRelease): ?ChurchCRMRelease
    {
        $logger = LoggerUtils::getAppLogger();
        
        if (empty($_SESSION['ChurchCRMReleases'])) {
            $_SESSION['ChurchCRMReleases'] = self::populateReleases();
        }
        $rs = array_values($_SESSION['ChurchCRMReleases']);
        $nextStepRelease = self::getReleaseNextPatch($rs, $currentRelease);
        if ($nextStepRelease !== null) {
            $logger->info('=== UPDATE FOUND (PATCH) === Next: ' . $nextStepRelease);
            return $nextStepRelease;
        }
        $nextStepRelease = self::getReleaseNextMinor($rs, $currentRelease);
        if ($nextStepRelease !== null) {
            $logger->info('=== UPDATE FOUND (MINOR) === Next: ' . $nextStepRelease);
            return $nextStepRelease;
        }
        $nextStepRelease = self::getReleaseNextMajor($rs, $currentRelease);
        if ($nextStepRelease !== null) {
            $logger->info('=== UPDATE FOUND (MAJOR) === Next: ' . $nextStepRelease);
            return $nextStepRelease;
        }

        if (null === $nextStepRelease) {
            // Check if current version is at or ahead of all available releases (e.g., development version)
            if (!empty($rs) && $currentRelease->compareTo($rs[0]) >= 0) {
                $logger->info('*** Current version ' . $currentRelease . ' is at or ahead of highest available release ' . $rs[0] . '. No upgrade available.');
                return null;
            }
            $logger->warning('Could not identify a suitable upgrade target release.  Current software version: ' . $currentRelease . '.  Highest available release: ' . (!empty($rs) ? $rs[0] : 'None'));
            return null;
        }

        return $nextStepRelease;
    }

    public static function downloadLatestRelease(): array
    {
        // Ensure releases are loaded
        if (empty($_SESSION['ChurchCRMReleases'])) {
            $_SESSION['ChurchCRMReleases'] = self::populateReleases();
        }

        // Get the latest release (first in the array since it's sorted)
        if (empty($_SESSION['ChurchCRMReleases'])) {
            throw new \Exception('No releases available from GitHub.');
        }

        $latestRelease = $_SESSION['ChurchCRMReleases'][0];
        LoggerUtils::getAppLogger()->info('Downloading latest release: ' . $latestRelease);

        return ChurchCRMReleaseManager::downloadRelease($latestRelease);
    }

    public static function downloadRelease(ChurchCRMRelease $release): array
    {
        LoggerUtils::getAppLogger()->info('Downloading release: ' . $release);
        $logger = LoggerUtils::getAppLogger();
        $UpgradeDir = sys_get_temp_dir();
        $url = $release->getDownloadURL();
        $logger->debug('Using temp directory: ' . $UpgradeDir);
        $logger->info('Downloading release from: ' . $url . ' to: ' . $UpgradeDir . '/' . basename($url));
        $executionTime = new ExecutionTime();
        file_put_contents($UpgradeDir . '/' . basename($url), file_get_contents($url));
        $logger->info('Finished downloading file.  Execution time: ' . $executionTime->getMilliseconds() . ' ms');
        $returnFile = [];
        $returnFile['fileName'] = basename($url);
        $returnFile['releaseNotes'] = $release->getReleaseNotes();
        $returnFile['fullPath'] = $UpgradeDir . '/' . basename($url);
        $returnFile['sha1'] = sha1_file($UpgradeDir . '/' . basename($url));
        $logger->info('SHA1 hash for ' . $returnFile['fullPath'] . ': ' . $returnFile['sha1']);
        $logger->info('Release notes: ' . $returnFile['releaseNotes']);

        return $returnFile;
    }

    public static function preShutdown(): void
    {
        // this is kind of code-smell
        // since this callback will be invoked upon PHP timeout
        // we aren't guaranteed any of Slim's error handling
        // so we need to echo a JSON document that "looks like"
        // an exception the client-side JS can display to the user
        // so they know it actually timed out.
        if (self::$isUpgradeInProgress) {
            // the PHP script was stopped while an upgrade was still in progress.
            $logger = LoggerUtils::getAppLogger();
            $logger->warning('Maximum execution time threshold exceeded: ' . ini_get('max_execution_time'));

            echo \json_encode([
                'code'    => 500,
                'message' => 'Maximum execution time threshold exceeded: ' . ini_get('max_execution_time') . '.  This ChurchCRM installation may now be in an unstable state.  Please review the documentation at https://github.com/ChurchCRM/CRM/wiki/Recovering-from-a-failed-update',
            ], JSON_THROW_ON_ERROR);
        }
    }

    public static function doUpgrade(string $zipFilename, string $sha1): void
    {
        self::$isUpgradeInProgress = true;
        // temporarily disable PHP's error display so that
        // our custom timeout handler can display parsable JSON
        // in the event this upgrade job times-out the
        // PHP instance's max_execution_time
        $displayErrors = ini_get('display_errors');
        ini_set('display_errors', 0);
        ini_set('max_execution_time', 50_000);
        register_shutdown_function(fn () => ChurchCRMReleaseManager::preShutdown());

        $logger = LoggerUtils::getAppLogger();
        $logger->info('Beginning upgrade process');
        $logger->info('PHP max_execution_time is now: ' . ini_get('max_execution_time'));
        $logger->info('Beginning hash validation on ' . $zipFilename);

        // Log detailed file information before attempting hash
        $fileExists = file_exists($zipFilename);
        $isReadable = is_readable($zipFilename);
        $fileSize = $fileExists ? filesize($zipFilename) : -1;
        $perms = $fileExists ? @fileperms($zipFilename) : false;
        $filePerms = ($perms !== false) ? substr(sprintf('%o', $perms), -4) : 'N/A';
        $currentUser = get_current_user();
        $pwuid = $fileExists ? posix_getpwuid(fileowner($zipFilename)) : false;
        $fileOwner = ($pwuid !== false && isset($pwuid['name'])) ? $pwuid['name'] : 'unknown';
        $logger->debug('File pre-flight check', [
            'zipFilename' => $zipFilename,
            'fileExists' => $fileExists,
            'isReadable' => $isReadable,
            'fileSize' => $fileSize,
            'filePerms' => $filePerms,
            'fileOwner' => $fileOwner,
            'currentUser' => $currentUser,
            'currentUserId' => function_exists('posix_getuid') ? posix_getuid() : 'N/A',
            'fileOwnerId' => $fileExists ? fileowner($zipFilename) : -1,
        ]);

        $actualSha1 = sha1_file($zipFilename);
        
        // If sha1_file() returned false, log detailed diagnostic info
        if ($actualSha1 === false) {
            $lastError = error_get_last();
            $logger->error(
                'sha1_file() returned false - hash calculation failed',
                [
                    'zipFilename' => $zipFilename,
                    'expectedHash' => $sha1,
                    'actualHash' => false,
                    'fileExists' => $fileExists,
                    'isReadable' => $isReadable,
                    'fileSize' => $fileSize,
                    'filePerms' => $filePerms,
                    'fileOwner' => $fileOwner,
                    'currentUser' => $currentUser,
                    'lastError' => $lastError['message'] ?? 'none',
                    'lastErrorType' => $lastError['type'] ?? 'none',
                    'openBasedir' => ini_get('open_basedir') ?: 'not set',
                    'disabledFunctions' => ini_get('disable_functions') ?: 'none',
                    'memoryLimit' => ini_get('memory_limit'),
                    'uploadTmpDir' => ini_get('upload_tmp_dir') ?: sys_get_temp_dir(),
                ]
            );
        }
        
        if ($sha1 !== $actualSha1) {
            self::$isUpgradeInProgress = false;
            ini_set('display_errors', $displayErrors);
            $message = 'hash validation failure';
            $logger->error(
                $message,
                [
                    'zipFilename' => $zipFilename,
                    'expectedHash' => $sha1,
                    'actualHash' => $actualSha1,
                ]
            );

            throw new \Exception($message);
        }

        $logger->info('Hash validation succeeded on ' . $zipFilename . ' Got: ' . $actualSha1);

        $zip = new \ZipArchive();
        $codeDeploySuccessful = false;

        if ($zip->open($zipFilename) === true) {
            $logger->info('Extracting ' . $zipFilename . ' to: ' . SystemURLs::getDocumentRoot() . '/Upgrade');

            $executionTime = new ExecutionTime();
            $isSuccessful = $zip->extractTo(SystemURLs::getDocumentRoot() . '/Upgrade');
            MiscUtils::throwIfFailed($isSuccessful);

            $zip->close();

            $logger->info('Extraction completed.  Took:' . $executionTime->getMilliseconds());
            $logger->info('Moving extracted zip into place');

            $executionTime = new ExecutionTime();

            FileSystemUtils::moveDir(SystemURLs::getDocumentRoot() . '/Upgrade/churchcrm', SystemURLs::getDocumentRoot());
            $codeDeploySuccessful = true;
            $logger->info('Move completed.  Took:' . $executionTime->getMilliseconds());
        }
        $logger->info('Deleting zip archive: ' . $zipFilename);
        unlink($zipFilename);

        $logger->info('Upgrade process complete');
        ini_set('display_errors', $displayErrors);
        // Only attempt to upgrade the database if the code deploy/move completed successfully
        if ($codeDeploySuccessful) {
            try {
                $logger->info('Attempting automatic database upgrade post code-deploy');
                UpgradeService::upgradeDatabaseVersion();
                $logger->info('Automatic database upgrade completed successfully');
                
                // After successful database upgrade, clean up orphaned files
                $logger->info('Beginning automatic orphaned file cleanup');
                $cleanupResult = AppIntegrityService::deleteOrphanedFiles();
                $logger->info('Orphaned file cleanup completed', [
                    'deleted' => count($cleanupResult['deleted']),
                    'failed' => count($cleanupResult['failed']),
                ]);
                
                if (!empty($cleanupResult['failed'])) {
                    $logger->warning('Some orphaned files could not be deleted', [
                        'failedFiles' => $cleanupResult['failed'],
                        'errors' => $cleanupResult['errors'],
                    ]);
                }
            } catch (\Exception $e) {
                $logger->error('Automatic database upgrade failed: ' . $e->getMessage(), ['exception' => $e]);
                // rethrow so the API caller is made aware of the failure
                throw $e;
            }
        } else {
            $logger->warning('Skipping automatic database upgrade because code deployment did not complete successfully');
        }
        self::$isUpgradeInProgress = false;
    }

    /**
     * Check if a system update is available for the current installation
     * Returns an array with 'available' (bool) and 'version' (ChurchCRMRelease|null) keys
     *
     * @return array{available: bool, version: ChurchCRMRelease|null, latestVersion: ChurchCRMRelease|null}
     */
    public static function checkSystemUpdateAvailable(): array
    {
        try {
            $logger = LoggerUtils::getAppLogger();
            $installedVersionString = VersionUtils::getInstalledVersion();
            
            $installedVersion = self::getReleaseFromString($installedVersionString);

            if (empty($_SESSION['ChurchCRMReleases'])) {
                $releases = self::populateReleases();
                $_SESSION['ChurchCRMReleases'] = $releases;
                $logger->debug('Populated releases cache with ' . count($releases) . ' releases');
            }
            
            // Get the latest release from GitHub
            $latestRelease = null;
            if (!empty($_SESSION['ChurchCRMReleases'])) {
                $latestRelease = $_SESSION['ChurchCRMReleases'][0] ?? null;
            }
            
            if ($latestRelease === null) {
                $logger->warning('No releases available from GitHub cache');
            }
            
            $logger->debug('Update check: installed=' . $installedVersion->__toString() 
                . ' (MAJOR=' . $installedVersion->MAJOR . ' MINOR=' . $installedVersion->MINOR . ' PATCH=' . $installedVersion->PATCH . ')'
                . ', latest=' . ($latestRelease ? $latestRelease->__toString() : 'null')
                . ($latestRelease ? ' (MAJOR=' . $latestRelease->MAJOR . ' MINOR=' . $latestRelease->MINOR . ' PATCH=' . $latestRelease->PATCH . ')' : ''));
            
            $isCurrent = self::isReleaseCurrent($installedVersion);
            $logger->debug('isCurrent=' . ($isCurrent ? 'true' : 'false'));
            
            if (!$isCurrent) {
                $nextRelease = self::getNextReleaseStep($installedVersion);
                if (null !== $nextRelease) {
                    $logger->info('System update available', [
                        'currentVersion' => $installedVersionString,
                        'availableVersion' => $nextRelease->__toString(),
                        'latestVersion' => $latestRelease ? $latestRelease->__toString() : null
                    ]);
                    return [
                        'available' => true,
                        'version' => $nextRelease,
                        'latestVersion' => $latestRelease
                    ];
                }
            } else {
                $logger->debug('System is current - no update needed. latestVersion=' . ($latestRelease ? $latestRelease->__toString() : 'null'));
            }

            return [
                'available' => false,
                'version' => null,
                'latestVersion' => $latestRelease
            ];
        } catch (\Exception $e) {
            LoggerUtils::getAppLogger()->warning('Failed to check for system updates', ['exception' => $e]);
            return [
                'available' => false,
                'version' => null,
                'latestVersion' => null
            ];
        }
    }
}
