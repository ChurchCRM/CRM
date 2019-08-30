<?php

namespace ChurchCRM\Utils;

use ChurchCRM\dto\ChurchCRMRelease;
use Github\Client;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\dto\SystemConfig;

class ChurchCRMReleaseManager {

    private const GITHUB_USER_NAME = 'churchcrm';
    private const GITHUB_REPOSITORY_NAME = 'crm';

    public static function getReleaseFromString(string $releaseString): ChurchCRMRelease { 
        if ( empty($_SESSION['ChurchCRMReleases']  )) {
            // The ChurchCRM releases have not yet been populated.
            // Since populating the release list can be an expensive operation
            // don't do it here, but rather wait for SystemServer TimerJobs to take care of it
            // just give the requestor a skeleton object
            LoggerUtils::getAppLogger()->addDebug("Query for release string " . $releaseString . " ocurred before GitHub releases were populated.  Providing skeleton release object");
            return new ChurchCRMRelease(@["name" => $releaseString]);
        }
        else {
            LoggerUtils::getAppLogger()->addDebug("Attempting to service query for release string " . $releaseString . " from GitHub release cache");
            $requestedRelease = array_values(array_filter($_SESSION['ChurchCRMReleases'],function($r) use ($releaseString) {
                return $r->__toString() == $releaseString;
            }));
            if (count($requestedRelease) == 1 && $requestedRelease[0] instanceof ChurchCRMRelease){
                LoggerUtils::getAppLogger()->addDebug("Query for release string " . $releaseString . " serviced from GitHub release cache");
                return $requestedRelease[0];
            }
            else {
                throw new \Exception("Provided string matched more than one ChurchCRM Release: " . \json_encode($requestedRelease));
        }
        }
    }

    private static function populateReleases() {
        $client = new Client();
        $eligibleReleases = array();
        LoggerUtils::getAppLogger()->addDebug("Querying GitHub '".ChurchCRMReleaseManager::GITHUB_USER_NAME."/".ChurchCRMReleaseManager::GITHUB_REPOSITORY_NAME."' for ChurchCRM Releases");
        $gitHubReleases = $client->api('repo')->releases()->all(ChurchCRMReleaseManager::GITHUB_USER_NAME, ChurchCRMReleaseManager::GITHUB_REPOSITORY_NAME);
        LoggerUtils::getAppLogger()->addDebug("Received ". count($gitHubReleases) . " ChurchCRM releases on GitHub");
        foreach($gitHubReleases as $r)
        {
            $release = new ChurchCRMRelease($r);
            if ($release->isPreRelease()){
                if (SystemConfig::getBooleanValue("bAllowPrereleaseUpgrade")){
                    LoggerUtils::getAppLogger()->addDebug("bAllowPrereleaseUpgrade allows upgrade to a pre-release version.  Including ".$release." for consideration");
                    array_push($eligibleReleases,$release);
                }
                else {
                    LoggerUtils::getAppLogger()->addDebug("bAllowPrereleaseUpgrade disallows upgrade to a pre-release version.  Not including ".$release." for consideration");
                }
            }
            else {
                LoggerUtils::getAppLogger()->addDebug($release." is not a pre-release version. Including for consideration");
                array_push($eligibleReleases, $release);
            }
            
           
        }
        LoggerUtils::getAppLogger()->addDebug("Found " . count($eligibleReleases) . " eligible ChurchCRM releases on GitHub");
        return $eligibleReleases;
    }

    /**
     * @return ChurchCRMRelease[]
     */


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
            return $_SESSION['ChurchCRMReleases'][0]->equals($Release);
        }
        
    }

    public static function getNextReleaseStep(ChurchCRMRelease $currentRelease) : ChurchCRMRelease {

        LoggerUtils::getAppLogger()->addDebug("Determining the next-step release step for " . $currentRelease);
        if ( empty( $_SESSION['ChurchCRMReleases'] ) ) {
            $_SESSION['ChurchCRMReleases'] = self::populateReleases();
        }
        $rs = array_values($_SESSION['ChurchCRMReleases']);
        // look for releases having the same MAJOR and MINOR versions.  
        // Of these releases, if there is one with a newer PATCH version,
        // We should use the newest patch.
        LoggerUtils::getAppLogger()->addDebug("Evaluating next-step release eligibility based on " . count($_SESSION['ChurchCRMReleases']) . " available releases ");
        $eligibleUpgradeTargetReleases = array_values(array_filter($rs , function(ChurchCRMRelease $r) use ($currentRelease) {
            $isSameMajorAndMinor = ($r->MAJOR == $currentRelease->MAJOR) && ($r->MINOR == $currentRelease->MINOR);
            LoggerUtils::getAppLogger()->addDebug("Release " . $r . " is" . ($isSameMajorAndMinor ? " ":" not ")  . "a possible upgrade target");
            return $isSameMajorAndMinor; 
        }));

        usort($eligibleUpgradeTargetReleases, function(ChurchCRMRelease $a, ChurchCRMRelease $b){
            return $a->PATCH < $b->PATCH;
        });
        
        if (count($eligibleUpgradeTargetReleases) == 0 ) {
            throw new \Exception("Could not identify a suitable upgrade target release.  Current software version: " . $currentRelease . ".  Highest available release: " . $rs[0] ) ;
        }

        if ($currentRelease->equals($eligibleUpgradeTargetReleases[0])) {
            // the current release is the same as the most recent patch release from github, so let's return the most recent overall release from GitHub
            $nextStepRelease = ChurchCRMReleaseManager::getReleaseFromString($currentRelease->MAJOR . "." . ($currentRelease->MINOR+1) . ".0");
            LoggerUtils::getAppLogger()->addInfo("The current release (".$currentRelease.") is the highest release of it's Major/Minor combination.");
            LoggerUtils::getAppLogger()->addInfo("Looking for releases in series: " . $nextStepRelease);
            return self::getNextReleaseStep($nextStepRelease); 
        }
        LoggerUtils::getAppLogger()->addInfo("Next upgrade step for " . $currentRelease. " is : " . $eligibleUpgradeTargetReleases[0]);
        return $eligibleUpgradeTargetReleases[0];
    }


    public static function downloadLatestRelease()
    {
        // this is a proxy function.  For now, just download the nest step release
        $releaseToDownload =  ChurchCRMReleaseManager::getNextReleaseStep(ChurchCRMReleaseManager::getReleaseFromString($_SESSION['sSoftwareInstalledVersion']));
        return ChurchCRMReleaseManager::downloadRelease($releaseToDownload);
    }
    public static function downloadRelease(ChurchCRMRelease $release)
    {
        LoggerUtils::getAppLogger()->addInfo("Downloading release: " . $release);
        $logger = LoggerUtils::getAppLogger();
        $UpgradeDir = SystemURLs::getDocumentRoot() . '/Upgrade';
        $url = $release->getDownloadURL();
        $logger->debug("Creating upgrade directory: " . $UpgradeDir);
        mkdir($UpgradeDir);
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
    
}