<?php

namespace ChurchCRM\Tasks;

use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;


class PHPPendingDeprecationVersionCheckTask implements iTask, iPreUpgradeTask
{
  private const REQUIRED_PHP_VERSION = '7.3.0';
  public function isActive()
  {
    return version_compare(PHP_VERSION, $this::REQUIRED_PHP_VERSION, '<')
        || version_compare(PHP_VERSION, '8.0.0', '>=');;
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
