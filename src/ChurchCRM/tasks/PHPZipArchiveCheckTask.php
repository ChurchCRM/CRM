<?php

namespace ChurchCRM\Tasks;

use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;


class PHPZipArchiveCheckTask implements iTask, iPreUpgradeTask
{
  // todo: make these const variables private after deprecating PHP7.0 #4948
  public function isActive()
  {
    return ! class_exists("ZipArchive");
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
    return gettext('Missing PHP ZipArchive');
  }

  public function getDesc()
  {
    return gettext("PHP ZipArchive required to support upgrade");
  }

  public function getUpgradeBehavior() {
      return TaskUpgradeBehavior::WARN;
  }

}
