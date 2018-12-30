<?php

namespace ChurchCRM\Tasks;

use ChurchCRM\dto\SystemURLs;

class HttpsTask implements iTask
{
  public function isActive(){
    return $_SESSION['user']->isAdmin() && !isset($_SERVER['HTTPS']);
  }
  public function isAdmin(){
    return true;
  }
  public function getLink(){
    return SystemURLs::getSupportURL("ssl");
  }
  public function getTitle(){
    return gettext('Configure HTTPS');
  }
  public function getDesc(){
    return gettext('Your system could be more secure by installing an TLS/SSL Cert.');
  }

}
