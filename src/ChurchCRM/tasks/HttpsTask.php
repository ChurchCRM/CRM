<?php

namespace ChurchCRM\Tasks;

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\dto\SystemURLs;

class HttpsTask implements TaskInterface
{
    public function isActive(): bool
    {
        return AuthenticationManager::getCurrentUser()->isAdmin() && !isset($_SERVER['HTTPS']);
    }

    public function isAdmin(): bool
    {
        return true;
    }

    public function getLink(): string
    {
        return SystemURLs::getSupportURL('HttpsTask');
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
