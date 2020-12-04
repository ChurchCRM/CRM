<?php

namespace ChurchCRM\Utils;

use ChurchCRM\dto\ChurchCRMRelease;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\FileSystemUtils;
use Github\Client;

class ChurchCRMReleaseManager {

    // todo: make these const variables private after deprecating PHP7.0 #4948
    const GITHUB_USER_NAME = 'churchcrm';
    const GITHUB_REPOSITORY_NAME = 'crm';

    /** @var bool true when an upgrade is in progress */
    private static $isUpgradeInProgress;

    public static function getReleaseFromString(string $releaseString): ChurchCRMRelease {
        if ( empty($_SESSION['ChurchCRMReleases']  )) {
            // The ChurchCRM releases have not yet been populated.
            // Since populating the release list can be an expensive operation
            // don't do it here, but rather wait for SystemServer TimerJobs to take care of it
            // just give the requestor a skeleton object
            LoggerUtils::getAppLogger()->debug("Query for release string " . $releaseString . " ocurred before GitHub releases were populated.  Providing skeleton release object");
            return new ChurchCRMRelease(@["name" => $releaseString]);
        }
        else {
            LoggerUtils::getAppLogger()->debug("Attempting to service query for release string " . $releaseString . " from GitHub release cache");
            $requestedRelease = array_values(array_filter($_SESSION['ChurchCRMReleases'],function($r) use ($releaseString) {
                return $r->__toString() == $releaseString;
            }));
            if (count($requestedRelease) == 1 && $requestedRelease[0] instanceof ChurchCRMRelease){
                // this should be the case 99% of the time - the current version of the software has exactly one release on the GitHub account
                LoggerUtils::getAppLogger()->debug("Query for release string " . $releaseString . " serviced from GitHub release cache");
                return $requestedRelease[0];
            }
            elseif (count($requestedRelease) == 0) {
                // this will generally happen on dev or demo site instances
                // where the currently running software has not yet been released / tagged on GitHun
                LoggerUtils::getAppLogger()->debug("Query for release string " . $releaseString . " did not match any GitHub releases.  Providing skeleton release object");
                return new ChurchCRMRelease(@["name" => $releaseString]);
            }
            else {
                // This should _never_ happen.
                throw new \Exception("Provided string matched more than one ChurchCRM Release: " . \json_encode($requestedRelease));
            }
        }
    }

    /**
     * @return ChurchCRMRelease[]
     */
    private static function populateReleases(): array{
        $client = new Client();
        $eligibleReleases = array();
        LoggerUtils::getAppLogger()->debug("Querying GitHub '".ChurchCRMReleaseManager::GITHUB_USER_NAME."/".ChurchCRMReleaseManager::GITHUB_REPOSITORY_NAME."' for ChurchCRM Releases");
        $gitHubReleases = $client->api('repo')->releases()->all(ChurchCRMReleaseManager::GITHUB_USER_NAME, ChurchCRMReleaseManager::GITHUB_REPOSITORY_NAME);
        LoggerUtils::getAppLogger()->debug("Received ". count($gitHubReleases) . " ChurchCRM releases from GitHub");
        foreach($gitHubReleases as $r)
        {
            $release = new ChurchCRMRelease($r);
            if ($release->isPreRelease()){
                if (SystemConfig::getBooleanValue("bAllowPrereleaseUpgrade")){
                    LoggerUtils::getAppLogger()->debug("bAllowPrereleaseUpgrade allows upgrade to a pre-release version.  Including ".$release." for consideration");
                    array_push($eligibleReleases,$release);
                }
                else {
                    LoggerUtils::getAppLogger()->debug("bAllowPrereleaseUpgrade disallows upgrade to a pre-release version.  Not including ".$release." for consideration");
                }
            }
            else {
                LoggerUtils::getAppLogger()->debug($release." is not a pre-release version. Including for consideration");
                array_push($eligibleReleases, $release);
            }


        }

        usort($eligibleReleases, function(ChurchCRMRelease $a, ChurchCRMRelease $b){
            return $a->compareTo($b) < 0;
        });

        LoggerUtils::getAppLogger()->debug("Found " . count($eligibleReleases) . " eligible ChurchCRM releases on GitHub");
        return $eligibleReleases;
    }



    public static function checkForUpdates() {
        $_SESSION['ChurchCRMReleases'] = self::populateReleases();
    }

    public static function isReleaseCurrent(ChurchCRMRelease $Release) : bool {
        if ( empty($_SESSION['ChurchCRMReleases']  )) {
            // The ChurchCRM releases have not yet been populated.
            // Since populating the release list can be an expensive operation
            // don't do it here, but rather wait for SystemServer TimerJobs to take care of it
            // just tell the requestor that the provided release _is_ current
            return true;

        }
        else {
            $CurrentRelease = $_SESSION['ChurchCRMReleases'][0];
            LoggerUtils::getAppLogger()->debug("Determining if ".$Release." is current by checking if equals: " . $CurrentRelease);
            return $CurrentRelease->equals($Release);
        }

    }

    private static function getHighestReleaseInArray (array $eligibleUpgradeTargetReleases)  {
        if (count($eligibleUpgradeTargetReleases) >0 ) {
            usort($eligibleUpgradeTargetReleases, function(ChurchCRMRelease $a, ChurchCRMRelease $b){
                return $a->compareTo($b) < 0;
            });
            return $eligibleUpgradeTargetReleases[0];
        }
        return null;
    }

    private static function getReleaseNextPatch(array $rs, ChurchCRMRelease $currentRelease) {
        $eligibleUpgradeTargetReleases = array_values(array_filter($rs , function(ChurchCRMRelease $r) use ($currentRelease) {
            $isSameMajorAndMinorWithGreaterPatch = ($r->MAJOR == $currentRelease->MAJOR) && ($r->MINOR == $currentRelease->MINOR) && ($r->PATCH > $currentRelease->PATCH);
            LoggerUtils::getAppLogger()->debug("Release " . $r . " is" . ($isSameMajorAndMinorWithGreaterPatch ? " ":" not ")  . "a possible patch upgrade target");
            return $isSameMajorAndMinorWithGreaterPatch;
        }));
        return self::getHighestReleaseInArray($eligibleUpgradeTargetReleases);
    }

    private static function getReleaseNextMinor(array $rs, ChurchCRMRelease $currentRelease) {
        $eligibleUpgradeTargetReleases = array_values(array_filter($rs , function(ChurchCRMRelease $r) use ($currentRelease) {
            $isSameMajorAndMinorWithGreaterPatch = ($r->MAJOR == $currentRelease->MAJOR) && ($r->MINOR > $currentRelease->MINOR);
            LoggerUtils::getAppLogger()->debug("Release " . $r . " is" . ($isSameMajorAndMinorWithGreaterPatch ? " ":" not ")  . "a possible minor upgrade target");
            return $isSameMajorAndMinorWithGreaterPatch;
        }));
        return self::getHighestReleaseInArray($eligibleUpgradeTargetReleases);
    }

    private static function getReleaseNextMajor(array $rs, ChurchCRMRelease $currentRelease) {
        $eligibleUpgradeTargetReleases = array_values(array_filter($rs , function(ChurchCRMRelease $r) use ($currentRelease) {
            $isSameMajorAndMinorWithGreaterPatch = ($r->MAJOR > $currentRelease->MAJOR);
            LoggerUtils::getAppLogger()->debug("Release " . $r . " is" . ($isSameMajorAndMinorWithGreaterPatch ? " ":" not ")  . "a possible major upgrade target");
            return $isSameMajorAndMinorWithGreaterPatch;
        }));
        return self::getHighestReleaseInArray($eligibleUpgradeTargetReleases);
    }

    public static function getNextReleaseStep(ChurchCRMRelease $currentRelease) : ChurchCRMRelease {

        LoggerUtils::getAppLogger()->debug("Determining the next-step release step for " . $currentRelease);
        if ( empty( $_SESSION['ChurchCRMReleases'] ) ) {
            $_SESSION['ChurchCRMReleases'] = self::populateReleases();
        }
        $rs = array_values($_SESSION['ChurchCRMReleases']);
        // look for releases having the same MAJOR and MINOR versions.
        // Of these releases, if there is one with a newer PATCH version,
        // We should use the newest patch.
        LoggerUtils::getAppLogger()->debug("Evaluating next-step release eligibility based on " . count($_SESSION['ChurchCRMReleases']) . " available releases ");

        $nextStepRelease = self::getReleaseNextPatch($rs,$currentRelease);

        if (null == $nextStepRelease)  {
            $nextStepRelease = self::getReleaseNextMinor($rs,$currentRelease);
        }

        if (null == $nextStepRelease)  {
            $nextStepRelease = self::getReleaseNextMajor($rs,$currentRelease);
        }

        if (null == $nextStepRelease)  {
            throw new \Exception("Could not identify a suitable upgrade target release.  Current software version: " . $currentRelease . ".  Highest available release: " . $rs[0] ) ;
        }

        LoggerUtils::getAppLogger()->info("Next upgrade step for " . $currentRelease. " is : " . $nextStepRelease);
        return $nextStepRelease;
    }


    public static function downloadLatestRelease()
    {
        // this is a proxy function.  For now, just download the nest step release
        $releaseToDownload =  ChurchCRMReleaseManager::getNextReleaseStep(ChurchCRMReleaseManager::getReleaseFromString($_SESSION['sSoftwareInstalledVersion']));
        return ChurchCRMReleaseManager::downloadRelease($releaseToDownload);
    }
    public static function downloadRelease(ChurchCRMRelease $release)
    {
        LoggerUtils::getAppLogger()->info("Downloading release: " . $release);
        $logger = LoggerUtils::getAppLogger();
        $UpgradeDir = SystemURLs::getDocumentRoot() . '/Upgrade';
        $url = $release->getDownloadURL();
        $logger->debug("Creating upgrade directory: " . $UpgradeDir);
        if (!is_dir($UpgradeDir)){
            mkdir($UpgradeDir);
        }
        $logger->info("Downloading release from: " . $url . " to: ". $UpgradeDir . '/' . basename($url));
        $executionTime = new ExecutionTime();
        file_put_contents($UpgradeDir . '/' . basename($url), file_get_contents($url));
        $logger->info("Finished downloading file.  Execution time: " .$executionTime->getMiliseconds()." ms");
        $returnFile = [];
        $returnFile['fileName'] = basename($url);
        $returnFile['releaseNotes'] = $release->getReleaseNotes();
        $returnFile['fullPath'] = $UpgradeDir . '/' . basename($url);
        $returnFile['sha1'] = sha1_file($UpgradeDir . '/' . basename($url));
        $logger->info("SHA1 hash for ". $returnFile['fullPath'] .": " . $returnFile['sha1']);
        $logger->info("Release notes: " . $returnFile['releaseNotes'] );
        return $returnFile;
    }

    public static function preShutdown() {
        // this is kind of code-smell
        // since this callback will be invoked upon PHP timeout
        // we aren't guaranteed any of Slim's error handling
        // so we need to echo a JSON document that "looks like"
        // an exception the client-side JS can display to the user
        // so they know it actually timed out.
        if (self::$isUpgradeInProgress){
            // the PHP script was stopped while an upgrade was still in progress.
            $logger = LoggerUtils::getAppLogger();
            $logger->addWarning("Maximum execution time threshold exceeded: " . ini_get("max_execution_time"));

            echo \json_encode([
                'code' => 500,
                'message' => "Maximum execution time threshold exceeded: " . ini_get("max_execution_time") . ".  This ChurchCRM installation may now be in an unstable state.  Please review the documentation at https://github.com/ChurchCRM/CRM/wiki/Recovering-from-a-failed-update"
            ]);
        }
    }
    public static function doUpgrade($zipFilename, $sha1)
    {
        self::$isUpgradeInProgress = true;
        // temporarily disable PHP's error display so that
        // our custom timeout handler can display parsable JSON
        // in the event this upgrade job times-out the
        // PHP instance's max_execution_time
        $displayErrors = ini_get('display_errors');
        ini_set('display_errors',0);
        register_shutdown_function(function() {
            return ChurchCRMReleaseManager::preShutdown();
        });

        $logger = LoggerUtils::getAppLogger();
        $logger->info("Beginnging upgrade process");
        $logger->info("PHP max_execution_time is now: " . ini_get("max_execution_time"));
        $logger->info("Beginning hash validation on " . $zipFilename);
        if ($sha1 == sha1_file($zipFilename)) {
            $logger->info("Hash validation succeeded on " . $zipFilename . " Got: " . sha1_file($zipFilename));
            $zip = new \ZipArchive();
            if ($zip->open($zipFilename) == true) {
            $logger->info("Extracting " . $zipFilename." to: " . SystemURLs::getDocumentRoot() . '/Upgrade');
            $executionTime = new ExecutionTime();
            $zip->extractTo(SystemURLs::getDocumentRoot() . '/Upgrade');
            $zip->close();
            $logger->info("Extraction completed.  Took:" . $executionTime->getMiliseconds());
            $logger->info("Moving extracted zip into place");
            $executionTime = new ExecutionTime();
            FileSystemUtils::moveDir(SystemURLs::getDocumentRoot() . '/Upgrade/churchcrm', SystemURLs::getDocumentRoot());
            $logger->info("Move completed.  Took:" . $executionTime->getMiliseconds());
            }
            $logger->info("Deleting zip archive: ".$zipFilename);
            unlink($zipFilename);
            SystemConfig::setValue('sLastIntegrityCheckTimeStamp', null);
            $logger->debug("Set sLastIntegrityCheckTimeStamp to null");
            $logger->info("Upgrade process complete");
            ini_set('display_errors',$displayErrors);
            self::$isUpgradeInProgress = false;
            return 'success';
        } else {
            self::$isUpgradeInProgress = false;
            ini_set('display_errors',$displayErrors);
            $logger->err("Hash validation failed on " . $zipFilename.". Expected: ".$sha1. ". Got: ".sha1_file($zipFilename));
            return 'hash validation failure';
        }
    }

}
