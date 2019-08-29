<?php

namespace ChurchCRM\Tasks;
use ChurchCRM\dto\ChurchCRMRelease;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Utils\ChurchCRMReleaseManager;
use ChurchCRM\Utils\LoggerUtils;


class LatestReleaseTask implements iTask
{
  private $installedVersion;
  private $latestVersion;

  public function __construct()
  {
    $this->installedVersion = ChurchCRMRelease::FromString("3.2.4");#$_SESSION['sSoftwareInstalledVersion']);
  }

  public function isActive()
  {
    $isCurrent = ChurchCRMReleaseManager::isReleaseCurrent($this->installedVersion);
    if (! $isCurrent ) 
    {
      try {
        // This can fail with an exception if the currently running software is "not current"
        // but there are no more available releases.
        // this exception will really only happen when running development versions of the software
        // or if the ChurchCRM Release on which the current instance is running has been deleted 
        $this->latestVersion = ChurchCRMReleaseManager::getNextReleaseStep($this->installedVersion);
      }    
      catch (\Exception $e) {
        LoggerUtils::getAppLogger()->addWarning($e);
        return false;
      }
      return true;
    }
    return false;
  }

  public function isAdmin()
  {
    return false;
  }

  public function getLink()
  {
    if ($_SESSION['user']->isAdmin()) {
      return SystemURLs::getRootPath() . '/UpgradeCRM.php';
    } else {
      return 'https://github.com/ChurchCRM/CRM/releases/latest';
    }
  }

  public function getTitle()
  {
    return gettext('New Release') . ' ' . $this->latestVersion;
  }

  public function getDesc()
  {
    return $this->latestVersion;
  }

}
