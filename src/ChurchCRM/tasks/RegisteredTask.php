<?php

namespace ChurchCRM\Tasks;

use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;

class RegisteredTask implements iTask
{
    public function isActive(): bool
    {
        return SystemConfig::getValue('bRegistered') != 1;
    }

    public function isAdmin(): bool
    {
        return false;
    }

    public function getLink(): string
    {
        return SystemURLs::getRootPath() . '/Register.php';
    }

    public function getTitle(): string
    {
        return gettext('Register Software');
    }

    public function getDesc(): string
    {
        return gettext('Let us know that you are using the software');
    }
}
