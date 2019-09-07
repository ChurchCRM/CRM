<?php

namespace ChurchCRM\Tasks;

use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;


class PHPVersionCheckTask implements iTask, iPreUpgradeTask
{
  // todo: make these const variables private after deprecating PHP7.0 #4948
  const REQUIRED_PHP_VERSION = '7.1.0';
  public function isActive()
  {
    return version_compare(PHP_VERSION, $this::REQUIRED_PHP_VERSION, '<');
  }

  public function isAdmin()
  {
    return true;
  }

  public function getLink()
  {
    return SystemURLs::getRootPath() . '/v2/admin/debug';
  }

  public function getTitle()
  {
    return gettext('Unsupported PHP Version');
  }

  public function getDesc()
  {
    return gettext('Support for this PHP version will soon be removed.  Current PHP Version: '. PHP_VERSION. ". Minimum Required PHP Version: " . $this::REQUIRED_PHP_VERSION);
  }

  public function getUpgradeBehavior() {
      return TaskUpgradeBehavior::WARN;
  }

}
