<?php

namespace ChurchCRM\Volunteer\Middleware;

use ChurchCRM\Slim\Middleware\Request\Auth\BaseAuthRoleMiddleware;

class ManageMinistriesRoleAuthMiddleware extends BaseAuthRoleMiddleware
{
    protected function hasRole(): bool
    {
        return $this->user->isManageMinistriesEnabled();
    }

    protected function noRoleMessage(): string
    {
        return gettext('Ministry management access is required');
    }

    protected function getRoleName(): string
    {
        // Must stay in the allow-list at src/v2/routes/root.php or the access-denied
        // page renders no reason at all (design A10).
        return 'ManageMinistries';
    }
}
