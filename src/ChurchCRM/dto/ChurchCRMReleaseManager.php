<?php

namespace ChurchCRM\Utils;

use ChurchCRM\dto\ChurchCRMRelease;
use Github\Client;

use function GuzzleHttp\json_encode;

class ChurchCRMReleaseManager {

    private static $releases;

    private static function populateReleases() {
        $client = new Client();
        self::$releases = array();
        foreach($client->api('repo')->releases()->all('churchcrm', 'crm') as $release)
        {
            array_push(self::$releases,new ChurchCRMRelease($release));
        }
        LoggerUtils::getAppLogger()->addInfo("Found " . count(self::$releases) . " ChurchCRM releases on GitHub");
    }

    /**
     * @return ChurchCRMRelease[]
     */

    private static function getReleases()  : array {
        if (self::$releases == null )
        {
            self::populateReleases();
        }
        return self::$releases;
    }

    public static function isReleaseCurrent(string $ReleaseString) : bool {
        return self::getReleases()[0]->getVersionString == $ReleaseString;
    }

    public static function getNextReleaseStep(ChurchCRMRelease $currentRelease) : ChurchCRMRelease {

        // look for releases having the same MAJOR and MINOR versions.  
        // Of these releases, if there is one with a newer PATCH version,
        // We should use the newest patch.
        $sameMajorAndMinor = array_filter(self::getReleases(),function(ChurchCRMRelease $r) use ($currentRelease) {
            $isSameMajorAndMinor = ($r->MAJOR == $currentRelease->MAJOR) && ($r->MINOR == $currentRelease->MINOR);
            return $isSameMajorAndMinor; 
        });

        usort($sameMajorAndMinor, function(ChurchCRMRelease $a, ChurchCRMRelease $b){
            return $a->PATCH < $b->PATCH;
        });
        LoggerUtils::getAppLogger()->addInfo("Comparing: " . json_encode($currentRelease). " with: " . json_encode($sameMajorAndMinor[0]));

        if (count ($sameMajorAndMinor) == 1) {
            return $currentRelease;
        }
        elseif ($currentRelease->equals($sameMajorAndMinor[0])) {
            // the current release is the same as the most recent patch release from github, so let's return the most recent overall release from GitHub
            return self::getNextReleaseStep(ChurchCRMRelease::FromString($currentRelease->MAJOR . "." . ($currentRelease->MINOR+1) . ".0"));
        }
        LoggerUtils::getAppLogger()->addInfo(json_encode($sameMajorAndMinor));
        return $sameMajorAndMinor[0];
    }
    
}