<?php

namespace ChurchCRM\Tasks;

use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Authentication\AuthenticationManager;

class HttpsTask implements iTask
{
  public function isActive(){
    return AuthenticationManager::GetCurrentUser()->isAdmin() && !isset($_SERVER['HTTPS']);
  }
  public function isAdmin(){
    return true;
  }
  public function getLink()
  {
    return SystemURLs::getSupportURL(array_pop(explode('\\', __CLASS__)));
  }
  public function getTitle(){
    return gettext('Configure HTTPS');
  }
  public function getDesc(){
    return gettext('Your system could be more secure by installing an TLS/SSL Cert.');
  }

}
