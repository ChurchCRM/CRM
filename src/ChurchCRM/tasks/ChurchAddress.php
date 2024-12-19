<?php

namespace ChurchCRM\Tasks;

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;

class ChurchAddress implements iTask
{
    public function isActive()
    {
        return AuthenticationManager::GetCurrentUser()->isAdmin() && empty(SystemConfig::getValue('sChurchAddress'));
    }

    public function isAdmin()
    {
        return true;
    }

    public function getLink()
    {
        return SystemURLs::getRootPath().'/SystemSettings.php';
    }

    public function getTitle()
    {
        return gettext('Set Church Address');
    }

    public function getDesc()
    {
        return gettext('Church Address is not Set.');
    }
}
