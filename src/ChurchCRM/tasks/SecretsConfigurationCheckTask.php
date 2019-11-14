<?php

namespace ChurchCRM\Tasks;

use ChurchCRM\dto\SystemURLs;
use ChurchCRM\KeyManager;


class SecretsConfigurationCheckTask implements iTask
{
  public function isActive()
  {
    return ! KeyManager::GetAreAllSecretsDefined();
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
    return gettext('Secret Keys missing from Config.php');
  }

  public function getDesc()
  {
    return gettext('Secret Keys missing from Config.php');
  }

}
