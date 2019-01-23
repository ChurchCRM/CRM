<?php

namespace ChurchCRM\Tasks;

use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Service\AppIntegrityService;


class PrerequisiteCheckTask implements iTask
{
  public function isActive()
  {
    return ! AppIntegrityService::arePrerequisitesMet();
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
    return gettext('Unmet Application Prerequisites');
  }

  public function getDesc()
  {
    return gettext('Unmet Application Prerequisites');
  }

}
