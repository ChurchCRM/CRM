<?php
namespace ChurchCRM\Tasks;

use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Authentication\AuthenticationManager;

class ChurchNameTask implements iTask
{
    public function isActive(): bool
    {
        return AuthenticationManager::GetCurrentUser()->isAdmin() && SystemConfig::getValue('sChurchName') == 'Some Church';
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
        return gettext('Update Church Info');
    }

    public function getDesc(): string
    {
        return gettext("Church Name is set to default value");
    }
}
