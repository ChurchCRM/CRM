<?php

namespace ChurchCRM\Utils;

use ChurchCRM\dto\ChurchCRMRelease;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Utils\FileSystemUtils;
use ChurchCRM\Utils\HtaccessGenerator;
use ChurchCRM\Service\AppIntegrityService;
use ChurchCRM\Service\UpgradeService;
use ChurchCRM\Utils\ExecutionTime;
use ChurchCRM\Utils\LoggerUtils;
use ChurchCRM\Utils\VersionUtils;
use Github\Client;
use Github\ResultPager;

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
                throw new \Exception('Provided string matched more than one ChurchCRM Release: ' . \json_encode($requestedRelease));
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
                // Use the GitHub API client with proper method chaining
                // knplabs/github-api v3 uses repos()->releases()
                try {
                    $gitHubReleases = $client->repo()
                        ->releases()
                        ->all(
                            ChurchCRMReleaseManager::GITHUB_USER_NAME,
                            ChurchCRMReleaseManager::GITHUB_REPOSITORY_NAME
                        );
                } catch (\Exception $e) {
                    LoggerUtils::getAppLogger()->warning('Failed to fetch all releases from GitHub API', ['error' => $e->getMessage()]);
                    $gitHubReleases = [];
                }

                foreach ($gitHubReleases as $r) {
                    $release = new ChurchCRMRelease($r);
                    if (!$release->hasVersion()) {
                        continue;
                    }
                    if ($release->isPreRelease()) {
                        if ($allowPrerelease) {
                            $eligibleReleases[] = $release;
                        }
                    } else {
                        $eligibleReleases[] = $release;
                    }
                }
            } else {
                try {
                    $latestRelease = $client->repo()
                        ->releases()
                        ->latest(
                            ChurchCRMReleaseManager::GITHUB_USER_NAME,
                            ChurchCRMReleaseManager::GITHUB_REPOSITORY_NAME
                        );
                } catch (\Exception $e) {
                    LoggerUtils::getAppLogger()->warning('Failed to fetch latest release from GitHub API', ['error' => $e->getMessage()]);
                    $latestRelease = null;
                }

                if (is_array($latestRelease) && !empty($latestRelease)) {
                    $release = new ChurchCRMRelease($latestRelease);
                    // Only cache stable releases; skip if latest is a prerelease
                    if ($release->hasVersion() && !$release->isPreRelease()) {
                        $eligibleReleases[] = $release;
                    } elseif (!$release->hasVersion()) {
                        try {
                            $earlier = $client->repo()->releases()->all(
                                ChurchCRMReleaseManager::GITHUB_USER_NAME,
                                ChurchCRMReleaseManager::GITHUB_REPOSITORY_NAME
                            );
                        } catch (\Exception $e) {
                            LoggerUtils::getAppLogger()->warning('Failed to fetch earlier releases from GitHub API', ['error' => $e->getMessage()]);
                            $earlier = [];
                        }
                        foreach ($earlier as $r) {
                            $candidate = new ChurchCRMRelease($r);
                            if ($candidate->hasVersion() && !$candidate->isPreRelease()) {
                                $eligibleReleases[] = $candidate;
                                break;
                            }
                        }
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
        $logger->debug('checkForUpdates() called');
        $_SESSION['ChurchCRMReleases'] = self::populateReleases();
        unset($_SESSION['ChurchCRMAllStableReleases']);
        $logger->debug('checkForUpdates() complete - ' . count($_SESSION['ChurchCRMReleases']) . ' releases cached');
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
            $logger->info('Update found (patch): next release is ' . $nextStepRelease);
            return $nextStepRelease;
        }
        $nextStepRelease = self::getReleaseNextMinor($rs, $currentRelease);
        if ($nextStepRelease !== null) {
            $logger->info('Update found (minor): next release is ' . $nextStepRelease);
            return $nextStepRelease;
        }
        $nextStepRelease = self::getReleaseNextMajor($rs, $currentRelease);
        if ($nextStepRelease !== null) {
            $logger->info('Update found (major): next release is ' . $nextStepRelease);
            return $nextStepRelease;
        }

        if (null === $nextStepRelease) {
            // Check if current version is at or ahead of all available releases (e.g., development version)
            if (!empty($rs) && $currentRelease->compareTo($rs[0]) >= 0) {
                $logger->debug('Current version ' . $currentRelease . ' is at or ahead of highest available release ' . $rs[0] . '. No upgrade available.');
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

        // Download the file with retry logic (3 attempts total)
        $maxAttempts = 3;
        $downloadContent = false;
        $lastErrorMsg = 'Unknown error';

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            $logger->info('Download attempt ' . $attempt . ' of ' . $maxAttempts);

            if (function_exists('curl_init')) {
                // Use cURL for download (works even when allow_url_fopen is disabled)
                $ch = curl_init($url);
                if ($ch === false) {
                    $lastErrorMsg = 'curl_init() failed to initialize (possibly malformed URL)';
                } else {
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
                    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
                    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
                    curl_setopt($ch, CURLOPT_USERAGENT, 'ChurchCRM/' . VersionUtils::getInstalledVersion());
                    curl_setopt($ch, CURLOPT_TIMEOUT, 300);
                    $result = curl_exec($ch);
                    $curlErrno = curl_errno($ch);
                    $curlError = curl_error($ch);
                    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                    curl_close($ch);

                    if ($result !== false && $httpCode >= 200 && $httpCode < 300 && strlen($result) > 0) {
                        $downloadContent = $result;
                        $logger->info('Download succeeded on attempt ' . $attempt . ' via cURL (HTTP ' . $httpCode . ')');
                        break;
                    }

                    $lastErrorMsg = !empty($curlError) ? 'cURL error (' . $curlErrno . '): ' . $curlError : 'HTTP ' . $httpCode;
                }
            } else {
                // Fallback: file_get_contents (requires allow_url_fopen = On)
                @error_clear_last();
                $result = @file_get_contents($url);
                if ($result !== false && strlen($result) > 0) {
                    $downloadContent = $result;
                    $logger->info('Download succeeded on attempt ' . $attempt . ' via file_get_contents');
                    break;
                }
                $phpError = error_get_last();
                $lastErrorMsg = $phpError['message'] ?? 'Unknown error or empty response';
            }

            if ($attempt < $maxAttempts) {
                $logger->warning('Download attempt ' . $attempt . ' failed, retrying...', [
                    'url' => $url,
                    'error' => $lastErrorMsg,
                ]);
                // Wait before retry (1 second, then 2 seconds)
                sleep($attempt);
            }
        }

        // Check if all attempts failed
        if ($downloadContent === false) {
            $logger->error('Failed to download release file after ' . $maxAttempts . ' attempts', [
                'url' => $url,
                'error' => $lastErrorMsg,
            ]);
            throw new \Exception(gettext('Failed to download the release file from GitHub after multiple attempts. Please check your server\'s internet connection and try again.') . ' Error: ' . $lastErrorMsg);
        }

        // Check if downloaded content is empty
        $downloadSize = strlen($downloadContent);
        if ($downloadSize === 0) {
            $logger->error('Downloaded release file is empty after ' . $maxAttempts . ' attempts', [
                'url' => $url,
                'size' => 0,
            ]);
            throw new \Exception(gettext('Downloaded release file is empty. This may be due to network issues or GitHub rate limiting. Please try again later.'));
        }

        // Minimum expected size for a ChurchCRM release ZIP (at least 1MB)
        $minExpectedSize = 1024 * 1024;
        if ($downloadSize < $minExpectedSize) {
            $logger->warning('Downloaded file is smaller than expected', [
                'url' => $url,
                'size' => $downloadSize,
                'minExpectedSize' => $minExpectedSize,
            ]);
        }

        $destPath = $UpgradeDir . '/' . basename($url);
        $writeResult = file_put_contents($destPath, $downloadContent);

        if ($writeResult === false) {
            $logger->error('Failed to write downloaded file to disk', [
                'destPath' => $destPath,
                'downloadSize' => $downloadSize,
            ]);
            throw new \Exception(gettext('Failed to save the downloaded release file. Please check disk space and permissions.'));
        }

        $logger->info('Finished downloading file.  Execution time: ' . $executionTime->getMilliseconds() . ' ms');
        $logger->info('Downloaded file size: ' . $downloadSize . ' bytes');

        $returnFile = [];
        $returnFile['fileName'] = basename($url);
        $returnFile['releaseNotes'] = $release->getReleaseNotes();
        $returnFile['fullPath'] = $destPath;
        $returnFile['sha1'] = sha1_file($destPath);
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

            // Discard any output already buffered (e.g. by the do-upgrade route's
            // output-buffering guard) so this is the only thing in the response body.
            while (ob_get_level() > 0) {
                ob_end_clean();
            }

            echo \json_encode([
                'code'    => 500,
                'message' => 'Maximum execution time threshold exceeded: ' . ini_get('max_execution_time') . '.  This ChurchCRM installation may now be in an unstable state.  Please review the Documentation at https://docs.churchcrm.io/administration/troubleshooting',
            ]);
        }
    }

    public static function doUpgrade(string $zipFilename, string $sha1): void
    {
        self::$isUpgradeInProgress = true;
        // Disable PHP's error display for the entire upgrade (code deploy +
        // database migration + cleanup), not just the code-deploy phase. This
        // is what lets our custom timeout handler (preShutdown()) emit clean,
        // parsable JSON if this job times out, and it also stops any stray
        // PHP warning/notice/deprecation from the database migration step
        // (UpgradeService runs legacy .sql/.php upgrade scripts) from being
        // echoed directly into the HTTP response body ahead of the JSON the
        // wizard's AJAX call expects — which corrupts the response and
        // surfaces client-side as a "parsererror" even though the upgrade
        // itself succeeded. Restored exactly once, in the finally block
        // below, regardless of outcome.
        $displayErrors = ini_get('display_errors');
        ini_set('display_errors', 0);
        ini_set('max_execution_time', 50_000);
        register_shutdown_function(fn () => ChurchCRMReleaseManager::preShutdown());

        $logger = LoggerUtils::getAppLogger();
        $logger->info('Beginning upgrade process');
        $logger->info('PHP max_execution_time is now: ' . ini_get('max_execution_time'));

        try {
            // Pre-flight validation: Check if we can create the Upgrade directory
            $docRoot = SystemURLs::getDocumentRoot();
            $upgradeDir = $docRoot . '/Upgrade';
            $logger->info('Document root: ' . $docRoot);
            $logger->info('Upgrade directory will be: ' . $upgradeDir);

            // Check if document root is writable
            if (!is_writable($docRoot)) {
                // Safely determine the directory owner name, handling environments
                // where POSIX functions or lookups may fail.
                $ownerName = 'N/A';
                if (function_exists('posix_getpwuid')) {
                    $fileOwner = @fileowner($docRoot);
                    if ($fileOwner !== false) {
                        $ownerInfo = @posix_getpwuid($fileOwner);
                        if (is_array($ownerInfo) && isset($ownerInfo['name'])) {
                            $ownerName = $ownerInfo['name'];
                        } else {
                            $ownerName = 'unknown';
                        }
                    } else {
                        $ownerName = 'unknown';
                    }
                }

                $logger->error('Cannot write to ChurchCRM installation directory', [
                    'docRoot' => $docRoot,
                    'isWritable' => false,
                    'permissions' => substr(sprintf('%o', fileperms($docRoot)), -4),
                    'owner' => $ownerName,
                ]);
                throw new \Exception(gettext('Cannot write to ChurchCRM installation directory. Please check that the web server has write permissions.'));
            }

            // Create Upgrade directory if it doesn't exist
            if (!is_dir($upgradeDir)) {
                $logger->info('Creating Upgrade directory: ' . $upgradeDir);
                if (!@mkdir($upgradeDir, 0755, true)) {
                    $error = error_get_last();
                    $logger->error('Failed to create Upgrade directory', [
                        'upgradeDir' => $upgradeDir,
                        'lastError' => $error,
                    ]);
                    throw new \Exception(gettext('Failed to create Upgrade directory. Please check file permissions.'));
                }
            }

            $logger->info('Beginning hash validation on ' . $zipFilename);

            // Log detailed file information before attempting hash
            $fileExists = file_exists($zipFilename);
            $isReadable = is_readable($zipFilename);
            $fileSize = $fileExists ? filesize($zipFilename) : -1;
            $perms = $fileExists ? @fileperms($zipFilename) : false;
            $filePerms = ($perms !== false) ? substr(sprintf('%o', $perms), -4) : 'N/A';
            $currentUser = get_current_user();
            $fileOwner = 'unknown';
            // Only call posix_getpwuid if the function exists (not available on Windows)
            if ($fileExists && function_exists('posix_getpwuid')) {
                $pwuid = @posix_getpwuid(fileowner($zipFilename));
                $fileOwner = ($pwuid !== false && isset($pwuid['name'])) ? $pwuid['name'] : 'unknown';
            }
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

            $openResult = $zip->open($zipFilename);
            if ($openResult === true) {
                $logger->info('Extracting ' . $zipFilename . ' to: ' . $upgradeDir);

                $executionTime = new ExecutionTime();
                $isSuccessful = $zip->extractTo($upgradeDir);
                if (!$isSuccessful) {
                    $logger->error('Failed to extract upgrade archive', [
                        'zipFilename' => $zipFilename,
                        'upgradeDir' => $upgradeDir,
                        'diskFreeSpace' => disk_free_space($docRoot),
                    ]);
                    $zip->close();
                    throw new \Exception(gettext('Failed to extract upgrade archive. Please check disk space and file permissions.'));
                }

                $zip->close();

                $logger->info('Extraction completed.  Took:' . $executionTime->getMilliseconds());

                // Verify the extracted directory exists
                $extractedChurchCRM = $upgradeDir . '/churchcrm';
                if (!is_dir($extractedChurchCRM)) {
                    $logger->error('Extraction completed but expected directory not found', [
                        'upgradeDir' => $upgradeDir,
                        'expectedDir' => $extractedChurchCRM,
                        'upgradeDirContents' => @scandir($upgradeDir),
                    ]);
                    throw new \Exception(gettext('Extraction completed but upgrade files not found. The upgrade archive may be corrupted.'));
                }

                $logger->info('Moving extracted zip into place');

                $executionTime = new ExecutionTime();

                FileSystemUtils::moveDir($extractedChurchCRM, $docRoot);
                $codeDeploySuccessful = true;
                $logger->info('Move completed.  Took:' . $executionTime->getMilliseconds());

                // Regenerate RewriteBase in all routing .htaccess files so
                // subdirectory installs continue to work after the file overwrite.
                // Must run before the DB-migration API call. See #10563.
                try {
                    HtaccessGenerator::generateAll();
                } catch (\Throwable $e) {
                    $logger->error('Failed to regenerate .htaccess RewriteBase', ['exception' => $e]);
                    // Non-fatal — continue with upgrade
                }
            } else {
                // ZipArchive::open() failed - log the error code and throw
                $logger->error('Failed to open upgrade archive', [
                    'zipFilename' => $zipFilename,
                    'errorCode' => $openResult,
                ]);
                throw new \Exception(gettext('Failed to open upgrade archive. The downloaded file may be corrupted.'));
            }
            $logger->info('Deleting zip archive: ' . $zipFilename);
            unlink($zipFilename);

            $logger->info('Upgrade process complete');

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
                            'failed' => $cleanupResult['failed'],
                        ]);
                    }
                } catch (\Throwable $e) {
                    $logger->error('Database upgrade or cleanup failed', ['exception' => $e]);
                    throw $e;
                }
            }
        } catch (\Throwable $e) {
            $logger->error('Upgrade failed', ['exception' => $e]);
            throw $e;
        } finally {
            self::$isUpgradeInProgress = false;
            ini_set('display_errors', $displayErrors);
        }
    }
}
