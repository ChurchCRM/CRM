<?php
namespace ChurchCRM\Tasks;

use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;

class ChurchNameTask implements iTask
{
  public function isActive(){
    return $_SESSION['user']->isAdmin() && SystemConfig::getValue('sChurchName') == 'Some Church';
  }
  public function isAdmin(){
    return true;
  }
  public function getLink(){
    return SystemURLs::getRootPath() . '/SystemSettings.php';
  }
  public function getTitle(){
    return gettext('Update Church Info');
  }
  public function getDesc(){
    return gettext("Church Name is set to default value");
  }

}
