<?php

namespace ChurchCRM\Tasks;

class DeprecatedPlatformTask implements iTask
{
  public function __construct()
  {
  
  }
  
  public function isActive()
  {
    return version_compare(PHP_VERSION, '7.0.0', '<');
  }

  public function isAdmin()
  {
    return true;
  }

  public function getLink()
  {
    return 'http://php.net/supported-versions.php';
  }

  public function getTitle()
  {
    return gettext('Application updates are disabled.  This installation is running on a deprecated version of PHP').": ". PHP_VERSION;
  }

  public function getDesc()
  {
    return gettext('Application updates are disabled.  This installation is running on a deprecated version of PHP').": ". PHP_VERSION;
  }

}
