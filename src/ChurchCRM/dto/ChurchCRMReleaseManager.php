<?php

namespace ChurchCRM\Utils;

use ChurchCRM\dto\ChurchCRMRelease;
use Github\Client;

use function GuzzleHttp\json_encode;

class ChurchCRMReleaseManager {


    private static function populateReleases() {
        $client = new Client();
        $releases = array();
        LoggerUtils::getAppLogger()->addDebug("Querying GitHub for ChurchCRM Releases");
        foreach($client->api('repo')->releases()->all('churchcrm', 'crm') as $release)
        {
            array_push($releases,new ChurchCRMRelease($release));
        }
        LoggerUtils::getAppLogger()->addDebug("Found " . count($releases) . " ChurchCRM releases on GitHub");
        return $releases;
    }

    /**
     * @return ChurchCRMRelease[]
     */


    public static function CheckForUpdates() {
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
            throw new \Exception("No eligibile releases to upgrade, but we're running a different version than the latest");
        }

        if ($currentRelease->equals($eligibleUpgradeTargetReleases[0])) {
            // the current release is the same as the most recent patch release from github, so let's return the most recent overall release from GitHub
            $nextStepRelease = ChurchCRMRelease::FromString($currentRelease->MAJOR . "." . ($currentRelease->MINOR+1) . ".0");
            LoggerUtils::getAppLogger()->addInfo("The current release (".$currentRelease.") is the highest release of it's Major/Minor combination.");
            LoggerUtils::getAppLogger()->addInfo("Looking for releases in series: " . $nextStepRelease);
            return self::getNextReleaseStep($nextStepRelease); 
        }
        LoggerUtils::getAppLogger()->addInfo("Next upgrade step for " . $currentRelease. " is : " . $eligibleUpgradeTargetReleases[0]);
        return $eligibleUpgradeTargetReleases[0];
    }
    
}