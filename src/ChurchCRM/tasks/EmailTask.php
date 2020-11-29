<?php

namespace ChurchCRM\Tasks;

use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Authentication\AuthenticationManager;

class EmailTask implements iTask
{
  public function isActive()
  {
    return AuthenticationManager::GetCurrentUser()->isAdmin() && empty(SystemConfig::hasValidMailServerSettings());
  }

  public function isAdmin()
  {
    return true;
  }

  public function getLink()
  {
    return SystemURLs::getRootPath() . '/SystemSettings.php';
  }

  public function getTitle()
  {
    return gettext('Set Email Settings');
  }

  public function getDesc()
  {
    return gettext("SMTP Server info are blank");
  }

}
