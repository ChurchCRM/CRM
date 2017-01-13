<?php

namespace ChurchCRM\Tasks;

use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;


class RegisteredTask implements iTask
{
  public function isActive()
  {
    return SystemConfig::getValue('bRegistered') != 1;
  }

  public function isAdmin()
  {
    return false;
  }

  public function getLink()
  {
    return SystemURLs::getRootPath() . '/Register.php';
  }

  public function getTitle()
  {
    return gettext('Register Software');
  }

  public function getDesc()
  {
    return gettext('Let us know that you are using the software');
  }

}
