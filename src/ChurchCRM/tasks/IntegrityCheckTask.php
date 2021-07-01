<?php

namespace ChurchCRM\Tasks;

use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Authentication\AuthenticationManager;


class IntegrityCheckTask implements iTask
{
  private $integrityCheckData;

  public function __construct()
  {
    if (file_exists(SystemURLs::getDocumentRoot() . '/integrityCheck.json')) {
      $this->integrityCheckData = json_decode(file_get_contents(SystemURLs::getDocumentRoot() . '/integrityCheck.json'));
    }
  }

  public function isActive()
  {
    return AuthenticationManager::GetCurrentUser()->isAdmin() && ($this->integrityCheckData == null || $this->integrityCheckData->status == 'failure');
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
    return gettext('Application Integrity Check Failed');
  }

  public function getDesc()
  {
    return gettext('Application Integrity Check Failed');
  }

}
