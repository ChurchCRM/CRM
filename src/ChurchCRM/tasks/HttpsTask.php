<?php

namespace ChurchCRM\Tasks;

use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Authentication\AuthenticationManager;

class HttpsTask implements iTask
{
    public function isActive(): bool
    {
        return AuthenticationManager::GetCurrentUser()->isAdmin() && !isset($_SERVER['HTTPS']);
    }

    public function isAdmin(): bool
    {
        return true;
    }

    public function getLink(): string
    {
        return SystemURLs::getSupportURL(array_pop(explode('\\', self::class)));
    }

    public function getTitle(): string
    {
        return gettext('Configure HTTPS');
    }

    public function getDesc(): string
    {
        return gettext('Your system could be more secure by installing an TLS/SSL Cert.');
    }
}
