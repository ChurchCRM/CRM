<?php

namespace ChurchCRM\Tasks;


class HttpsTask implements iTask
{
  public function isActive(){
    return $_SESSION['user']->isAdmin() && !isset($_SERVER['HTTPS']);
  }
  public function isAdmin(){
    return true;
  }
  public function getLink(){
    return 'http://docs.churchcrm.io/en/latest/';
  }
  public function getTitle(){
    return gettext('Configure HTTPS');
  }
  public function getDesc(){
    return gettext('Your system could be more secure by installing an TLS/SSL Cert.');
  }

}
