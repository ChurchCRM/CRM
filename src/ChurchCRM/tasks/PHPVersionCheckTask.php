<?php

namespace ChurchCRM\Tasks;

use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;


class PHPVersionCheckTask implements iTask, iPreUpgradeTask
{
  public function isActive()
  {
    return version_compare(PHP_VERSION, '7.3.9', '<');
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
    return gettext('Support for this PHP version will soon be removed.  Current PHP Version: '. PHP_VERSION. ". Minimum Required PHP Version: 7.1.0");
  }

  public function getUpgradeBehavior() {
      return TaskUpgradeBehavior::WARN;
  }

}
