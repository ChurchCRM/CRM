<?php

namespace ChurchCRM\Tasks;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Authentication\AuthenticationManager;

class ChurchAddress implements iTask
{
  public function isActive(): bool
  {
    return AuthenticationManager::GetCurrentUser()->isAdmin() && empty(SystemConfig::getValue('sChurchAddress'));
  }

  public function isAdmin(): bool
  {
    return true;
  }

  public function getLink(): string
  {
    return SystemURLs::getRootPath() . '/SystemSettings.php';
  }

  public function getTitle(): string
  {
    return gettext('Set Church Address');
  }

  public function getDesc(): string
  {
    return gettext("Church Address is not Set.");
  }
}
