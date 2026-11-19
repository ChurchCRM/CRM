<?php

namespace ChurchCRM\Volunteer\Middleware;

use ChurchCRM\Slim\Middleware\Request\Auth\BaseAuthRoleMiddleware;
use ChurchCRM\Volunteer\Service\VolunteerAuthorizationService;

class VolunteerCoordinatorRoleAuthMiddleware extends BaseAuthRoleMiddleware
{
    protected function hasRole(): bool
    {
        return $this->user->isVolunteerCoordinatorEnabled()
            || (new VolunteerAuthorizationService())->hasTeamAuthority($this->user);
    }

    protected function noRoleMessage(): string
    {
        return gettext('Manage My Ministries access, with a ministry or team to manage, is required');
    }

    protected function getRoleName(): string
    {
        // Must stay in the allow-list at src/v2/routes/root.php or the access-denied
        // page renders no reason at all (design A10).
        return 'VolunteerCoordinator';
    }
}
