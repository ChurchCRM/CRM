<?php

namespace ChurchCRM\Slim\Middleware\Request\Auth;

use ChurchCRM\Portal\Auth\PortalMemberUser;

class StaffRoleAuthMiddleware extends BaseAuthRoleMiddleware
{
    protected function hasRole(): bool
    {
        return !($this->user instanceof PortalMemberUser);
    }

    protected function noRoleMessage(): string
    {
        return gettext('User must be a staff user');
    }

    protected function getRoleName(): string
    {
        return 'Staff';
    }
}
