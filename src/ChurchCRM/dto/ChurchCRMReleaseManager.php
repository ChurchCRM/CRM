<?php

namespace ChurchCRM\Utils;

use ChurchCRM\dto\ChurchCRMRelease;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\FileSystemUtils;
use ChurchCRM\Utils\ExecutionTime;
use ChurchCRM\Utils\LoggerUtils;
use ChurchCRM\Utils\MiscUtils;
use ChurchCRM\Utils\VersionUtils;
use Github\Client;

class ChurchCRMReleaseManager
{
    // todo: make these const variables private after deprecating PHP7.0 #4948
    public const GITHUB_USER_NAME = 'ChurchCRM';
    public const GITHUB_REPOSITORY_NAME = 'CRM';

    /** @var bool true when an upgrade is in progress */
    private static ?bool $isUpgradeInProgress = null;

    public static function getReleaseFromString(string $releaseString): ChurchCRMRelease
    {
        $logger = LoggerUtils::getAppLogger();
        $logger->debug('*** getReleaseFromString called with: ' . $releaseString);
        
        if (empty($_SESSION['ChurchCRMReleases'])) {
            // The ChurchCRM releases have not yet been populated.
            // Since populating the release list can be an expensive operation
            // don't do it here, but rather wait for SystemServer TimerJobs to take care of it
            // just give the requester a skeleton object
            $logger->debug('*** ChurchCRMReleases cache is EMPTY - Creating skeleton object for ' . $releaseString);

            return new ChurchCRMRelease(@['name' => $releaseString]);
        } else {
            $logger->debug('*** ChurchCRMReleases cache has ' . count($_SESSION['ChurchCRMReleases']) . ' releases');
            $logger->debug('Attempting to service query for release string ' . $releaseString . ' from GitHub release cache');
            $requestedRelease = array_values(array_filter($_SESSION['ChurchCRMReleases'], fn ($r): bool => $r->__toString() === $releaseString));
            if (count($requestedRelease) === 1 && $requestedRelease[0] instanceof ChurchCRMRelease) {
                // this should be the case 99% of the time - the current version of the software has exactly one release on the GitHub account
                $logger->debug('*** Found EXACT match in cache for ' . $releaseString);

                return $requestedRelease[0];
            } elseif (count($requestedRelease) === 0) {
                // this will generally happen on dev or demo site instances
                // where the currently running software has not yet been released / tagged on GitHun
                $logger->debug('*** NO match found in cache for ' . $releaseString . ' - Creating skeleton object');
                $logger->debug('*** Cache contains: ' . implode(', ', array_map(fn($r) => $r->__toString(), $_SESSION['ChurchCRMReleases'])));

                return new ChurchCRMRelease(@['name' => $releaseString]);
            } else {
                // This should _never_ happen.
                $logger->error('*** MULTIPLE matches found for ' . $releaseString);
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
        $logger = LoggerUtils::getAppLogger();
        $allowPrerelease = SystemConfig::getBooleanValue('bAllowPrereleaseUpgrade');

        try {
            $logger->debug("Querying GitHub '" . ChurchCRMReleaseManager::GITHUB_USER_NAME . '/' . ChurchCRMReleaseManager::GITHUB_REPOSITORY_NAME . "' for ChurchCRM Releases");
            
            // Optimize API call: fetch only what we need
            if ($allowPrerelease) {
                // If prerelease is enabled, fetch top 5 releases to provide options
                $logger->debug('Fetching top 5 releases (bAllowPrereleaseUpgrade: true)');
                $gitHubReleases = $client->api('repo')->releases()->all(
                    ChurchCRMReleaseManager::GITHUB_USER_NAME,
                    ChurchCRMReleaseManager::GITHUB_REPOSITORY_NAME,
                    ['per_page' => 5, 'page' => 1]
                );
            } else {
                // If prerelease is disabled, fetch top 3 releases (latest + 2 prior for comparison)
                // This ensures we can find the installed version for comparison
                $logger->debug('Fetching top 3 releases (bAllowPrereleaseUpgrade: false)');
                $gitHubReleases = $client->api('repo')->releases()->all(
                    ChurchCRMReleaseManager::GITHUB_USER_NAME,
                    ChurchCRMReleaseManager::GITHUB_REPOSITORY_NAME,
                    ['per_page' => 3, 'page' => 1]
                );
            }
            
            $logger->debug('Received ' . count($gitHubReleases) . ' ChurchCRM releases from GitHub');
            
            foreach ($gitHubReleases as $r) {
                $release = new ChurchCRMRelease($r);
                if ($release->isPreRelease()) {
                    if ($allowPrerelease) {
                        $logger->debug('bAllowPrereleaseUpgrade allows upgrade to a pre-release version.  Including ' . $release . ' for consideration');
                        $eligibleReleases[] = $release;
                    } else {
                        $logger->debug('bAllowPrereleaseUpgrade disallows upgrade to a pre-release version.  Not including ' . $release . ' for consideration');
                    }
                } else {
                    $logger->debug($release . ' is not a pre-release version. Including for consideration');
                    $eligibleReleases[] = $release;
                }
            }

            // Sort from newest to oldest (descending order) - newest first
            // Use version_compare directly and negate for descending order
            usort($eligibleReleases, fn (ChurchCRMRelease $a, ChurchCRMRelease $b): int => version_compare($b->__toString(), $a->__toString()));
            
            $logger->debug('Releases after sorting (newest first):');
            foreach ($eligibleReleases as $idx => $rel) {
                $logger->debug('  [' . $idx . '] ' . $rel);
            }

            $logger->debug('Found ' . count($eligibleReleases) . ' eligible ChurchCRM releases on GitHub');
        } catch (\Exception $ex) {
            $errorMessage = $ex->getMessage();
            $logger->error('Error updating database: ' . $errorMessage, ['exception' => $ex]);
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
        $logger = LoggerUtils::getAppLogger();
        $logger->debug('*** isReleaseCurrent called for: ' . $Release . ' (MAJOR=' . $Release->MAJOR . ' MINOR=' . $Release->MINOR . ' PATCH=' . $Release->PATCH . ')');
        
        if (empty($_SESSION['ChurchCRMReleases'])) {
            // The ChurchCRM releases have not yet been populated.
            // Since populating the release list can be an expensive operation
            // don't do it here, but rather wait for SystemServer TimerJobs to take care of it
            // just tell the requester that the provided release _is_ current
            $logger->debug('*** ChurchCRMReleases cache is EMPTY - Assuming version is current (safe default)');
            return true;
        } else {
            $CurrentRelease = $_SESSION['ChurchCRMReleases'][0];
            $logger->debug('*** Highest available release: ' . $CurrentRelease . ' (MAJOR=' . $CurrentRelease->MAJOR . ' MINOR=' . $CurrentRelease->MINOR . ' PATCH=' . $CurrentRelease->PATCH . ')');
            $isEqual = $CurrentRelease->equals($Release);
            $logger->debug('*** Comparing: ' . $Release . ' equals ' . $CurrentRelease . ' ? ' . ($isEqual ? 'YES (current)' : 'NO (not current)'));

            return $isEqual;
        }
    }

    private static function getHighestReleaseInArray(array $eligibleUpgradeTargetReleases)
    {
        $logger = LoggerUtils::getAppLogger();
        $logger->debug('*** getHighestReleaseInArray called with ' . count($eligibleUpgradeTargetReleases) . ' candidates');
        
        if (count($eligibleUpgradeTargetReleases) > 0) {
            // Log before sort
            $logger->debug('    Candidates before sort:');
            foreach ($eligibleUpgradeTargetReleases as $idx => $rel) {
                $logger->debug('      [' . $idx . '] ' . $rel);
            }
            
            // Sort descending by version, newest first
            usort($eligibleUpgradeTargetReleases, fn (ChurchCRMRelease $a, ChurchCRMRelease $b): int => version_compare($b->__toString(), $a->__toString()));
            
            // Log after sort
            $logger->debug('    Candidates after sort (descending/newest first):');
            foreach ($eligibleUpgradeTargetReleases as $idx => $rel) {
                $logger->debug('      [' . $idx . '] ' . $rel);
            }
            
            $highest = $eligibleUpgradeTargetReleases[0];
            $logger->debug('*** Returning highest: ' . $highest);
            return $highest;
        }

        $logger->debug('*** No candidates, returning null');
        return null;
    }

    private static function getReleaseNextPatch(array $rs, ChurchCRMRelease $currentRelease)
    {
        $logger = LoggerUtils::getAppLogger();
        $logger->debug('>>> getReleaseNextPatch: Checking for patch updates for ' . $currentRelease);
        
        $eligibleUpgradeTargetReleases = array_values(array_filter($rs, function (ChurchCRMRelease $r) use ($currentRelease, $logger): bool {
            $isSameMajorAndMinorWithGreaterPatch = ($r->MAJOR === $currentRelease->MAJOR) && ($r->MINOR === $currentRelease->MINOR) && ($r->PATCH > $currentRelease->PATCH);
            
            if ($r->MAJOR === $currentRelease->MAJOR && $r->MINOR === $currentRelease->MINOR) {
                $logger->debug('    PATCH CHECK: Release ' . $r . ' - MAJOR match (' . $r->MAJOR . '==' . $currentRelease->MAJOR . '), MINOR match (' . $r->MINOR . '==' . $currentRelease->MINOR . '), PATCH compare: ' . $r->PATCH . ' > ' . $currentRelease->PATCH . ' = ' . ($r->PATCH > $currentRelease->PATCH ? 'YES' : 'NO'));
            }
            
            $logger->debug('    Release ' . $r . ' is' . ($isSameMajorAndMinorWithGreaterPatch ? ' ' : ' not ') . 'a possible patch upgrade target');

            return $isSameMajorAndMinorWithGreaterPatch;
        }));
        
        $logger->debug('>>> getReleaseNextPatch: Found ' . count($eligibleUpgradeTargetReleases) . ' patch upgrade candidates');

        return self::getHighestReleaseInArray($eligibleUpgradeTargetReleases);
    }

    private static function getReleaseNextMinor(array $rs, ChurchCRMRelease $currentRelease)
    {
        $logger = LoggerUtils::getAppLogger();
        $logger->debug('>>> getReleaseNextMinor: Checking for minor updates for ' . $currentRelease);
        
        $eligibleUpgradeTargetReleases = array_values(array_filter($rs, function (ChurchCRMRelease $r) use ($currentRelease, $logger): bool {
            $isSameMajorAndMinorWithGreaterPatch = ($r->MAJOR === $currentRelease->MAJOR) && ($r->MINOR > $currentRelease->MINOR);
            
            if ($r->MAJOR === $currentRelease->MAJOR) {
                $logger->debug('    MINOR CHECK: Release ' . $r . ' - MAJOR match (' . $r->MAJOR . '==' . $currentRelease->MAJOR . '), MINOR compare: ' . $r->MINOR . ' > ' . $currentRelease->MINOR . ' = ' . ($r->MINOR > $currentRelease->MINOR ? 'YES' : 'NO'));
            }
            
            $logger->debug('    Release ' . $r . ' is' . ($isSameMajorAndMinorWithGreaterPatch ? ' ' : ' not ') . 'a possible minor upgrade target');

            return $isSameMajorAndMinorWithGreaterPatch;
        }));
        
        $logger->debug('>>> getReleaseNextMinor: Found ' . count($eligibleUpgradeTargetReleases) . ' minor upgrade candidates');

        return self::getHighestReleaseInArray($eligibleUpgradeTargetReleases);
    }

    private static function getReleaseNextMajor(array $rs, ChurchCRMRelease $currentRelease)
    {
        $logger = LoggerUtils::getAppLogger();
        $logger->debug('>>> getReleaseNextMajor: Checking for major updates for ' . $currentRelease);
        
        $eligibleUpgradeTargetReleases = array_values(array_filter($rs, function (ChurchCRMRelease $r) use ($currentRelease, $logger): bool {
            $isSameMajorAndMinorWithGreaterPatch = ($r->MAJOR > $currentRelease->MAJOR);
            
            $logger->debug('    MAJOR CHECK: Release ' . $r . ' - MAJOR compare: ' . $r->MAJOR . ' > ' . $currentRelease->MAJOR . ' = ' . ($r->MAJOR > $currentRelease->MAJOR ? 'YES' : 'NO'));
            $logger->debug('    Release ' . $r . ' is' . ($isSameMajorAndMinorWithGreaterPatch ? ' ' : ' not ') . 'a possible major upgrade target');

            return $isSameMajorAndMinorWithGreaterPatch;
        }));
        
        $logger->debug('>>> getReleaseNextMajor: Found ' . count($eligibleUpgradeTargetReleases) . ' major upgrade candidates');

        return self::getHighestReleaseInArray($eligibleUpgradeTargetReleases);
    }

    public static function getNextReleaseStep(ChurchCRMRelease $currentRelease): ?ChurchCRMRelease
    {
        $logger = LoggerUtils::getAppLogger();
        $logger->debug('=== getNextReleaseStep START ===');
        $logger->debug('Determining the next-step release step for ' . $currentRelease);
        $logger->debug('Current version details: MAJOR=' . $currentRelease->MAJOR . ' MINOR=' . $currentRelease->MINOR . ' PATCH=' . $currentRelease->PATCH);
        
        if (empty($_SESSION['ChurchCRMReleases'])) {
            $logger->debug('Session releases empty, populating...');
            $_SESSION['ChurchCRMReleases'] = self::populateReleases();
        }
        $rs = array_values($_SESSION['ChurchCRMReleases']);
        
        // Log all available releases
        $logger->debug('Total available releases: ' . count($rs));
        foreach ($rs as $idx => $release) {
            $logger->debug('  [' . $idx . '] Release: ' . $release . ' (MAJOR=' . $release->MAJOR . ' MINOR=' . $release->MINOR . ' PATCH=' . $release->PATCH . ')');
        }
        
        // look for releases having the same MAJOR and MINOR versions.
        // Of these releases, if there is one with a newer PATCH version,
        // We should use the newest patch.
        $logger->debug('Evaluating next-step release eligibility based on ' . count($_SESSION['ChurchCRMReleases']) . ' available releases ');

        $logger->debug('>>> STEP 1: Checking for patch updates (same MAJOR.MINOR, higher PATCH)...');
        $nextStepRelease = self::getReleaseNextPatch($rs, $currentRelease);
        if ($nextStepRelease !== null) {
            $logger->info('=== UPDATE FOUND (PATCH) === Next: ' . $nextStepRelease);
            $logger->debug('=== getNextReleaseStep END ===');
            return $nextStepRelease;
        }
        $logger->debug('No patch update found.');
        
        $logger->debug('>>> STEP 2: Checking for minor updates (same MAJOR, higher MINOR)...');
        $nextStepRelease = self::getReleaseNextMinor($rs, $currentRelease);
        if ($nextStepRelease !== null) {
            $logger->info('=== UPDATE FOUND (MINOR) === Next: ' . $nextStepRelease);
            $logger->debug('=== getNextReleaseStep END ===');
            return $nextStepRelease;
        }
        $logger->debug('No minor update found.');
        
        $logger->debug('>>> STEP 3: Checking for major updates (higher MAJOR)...');
        $nextStepRelease = self::getReleaseNextMajor($rs, $currentRelease);
        if ($nextStepRelease !== null) {
            $logger->info('=== UPDATE FOUND (MAJOR) === Next: ' . $nextStepRelease);
            $logger->debug('=== getNextReleaseStep END ===');
            return $nextStepRelease;
        }
        $logger->debug('No major update found.');

        if (null === $nextStepRelease) {
            // Check if current version is at or ahead of all available releases (e.g., development version)
            if (!empty($rs) && $currentRelease->compareTo($rs[0]) >= 0) {
                $logger->info('*** Current version ' . $currentRelease . ' is at or ahead of highest available release ' . $rs[0] . '. No upgrade available.');
                $logger->debug('=== getNextReleaseStep END (no upgrade) ===');
                return null;
            }
            $logger->warning('Could not identify a suitable upgrade target release.  Current software version: ' . $currentRelease . '.  Highest available release: ' . (!empty($rs) ? $rs[0] : 'None'));
            $logger->debug('=== getNextReleaseStep END (warning) ===');
            return null;
        }

        $logger->debug('=== getNextReleaseStep END ===');
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

        $actualSha1 = sha1_file($zipFilename);
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
            $logger->info('Move completed.  Took:' . $executionTime->getMilliseconds());
        }
        $logger->info('Deleting zip archive: ' . $zipFilename);
        unlink($zipFilename);

        SystemConfig::setValue('sLastIntegrityCheckTimeStamp', null);
        $logger->debug('Set sLastIntegrityCheckTimeStamp to null');
        $logger->info('Upgrade process complete');
        ini_set('display_errors', $displayErrors);
        self::$isUpgradeInProgress = false;
    }

    /**
     * Check if a system update is available for the current installation
     * Returns an array with 'available' (bool) and 'version' (ChurchCRMRelease|null) keys
     *
     * @return array{available: bool, version: ChurchCRMRelease|null}
     */
    public static function checkSystemUpdateAvailable(): array
    {
        try {
            $logger = LoggerUtils::getAppLogger();
            $installedVersionString = VersionUtils::getInstalledVersion();
            $logger->debug('*** checkSystemUpdateAvailable START - Installed version string: ' . $installedVersionString);
            
            $installedVersion = self::getReleaseFromString($installedVersionString);
            $logger->debug('*** Installed version object: ' . $installedVersion . ' (MAJOR=' . $installedVersion->MAJOR . ' MINOR=' . $installedVersion->MINOR . ' PATCH=' . $installedVersion->PATCH . ')');

            if (empty($_SESSION['ChurchCRMReleases'])) {
                $logger->debug('*** Release cache empty - populating before evaluation');
                $_SESSION['ChurchCRMReleases'] = self::populateReleases();
                $logger->debug('*** Release cache populated with ' . count($_SESSION['ChurchCRMReleases']) . ' releases');
            }
            
            $isCurrent = self::isReleaseCurrent($installedVersion);
            $logger->debug('*** Is current version? ' . ($isCurrent ? 'YES' : 'NO'));
            
            if (!$isCurrent) {
                $nextRelease = self::getNextReleaseStep($installedVersion);
                if (null !== $nextRelease) {
                    $logger->info('System update available', [
                        'currentVersion' => $installedVersionString,
                        'availableVersion' => $nextRelease->__toString()
                    ]);
                    return [
                        'available' => true,
                        'version' => $nextRelease
                    ];
                }
                $logger->debug('*** No next release step found');
            }

            $logger->debug('*** checkSystemUpdateAvailable END - No update available');
            return [
                'available' => false,
                'version' => null
            ];
        } catch (\Exception $e) {
            LoggerUtils::getAppLogger()->warning('Failed to check for system updates', ['exception' => $e]);
            return [
                'available' => false,
                'version' => null
            ];
        }
    }
}
