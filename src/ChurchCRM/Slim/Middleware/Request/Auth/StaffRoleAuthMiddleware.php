<?php

namespace ChurchCRM\Slim\Middleware\Request\Auth;

class StaffRoleAuthMiddleware extends BaseAuthRoleMiddleware
{
    protected function hasRole(): bool
    {
        return !$this->user->isEditSelfExclusive();
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
