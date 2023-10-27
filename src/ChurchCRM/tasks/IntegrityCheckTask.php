<?php

namespace ChurchCRM\Tasks;

use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\Utils\MiscUtils;

class IntegrityCheckTask implements iTask
{
  private $integrityCheckData;

  public function __construct()
  {
    $integrityCheckPath = SystemURLs::getDocumentRoot() . '/integrityCheck.json';
    if (is_file($integrityCheckPath)) {
      $integrityCheckContents = file_get_contents($integrityCheckPath);
      MiscUtils::throwIfFailed($integrityCheckContents);

      $this->integrityCheckData = json_decode($integrityCheckContents, null, 512, JSON_THROW_ON_ERROR);
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
