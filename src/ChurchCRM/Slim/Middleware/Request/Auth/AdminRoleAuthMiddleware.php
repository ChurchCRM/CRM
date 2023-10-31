<?php

namespace ChurchCRM\Slim\Middleware\Request\Auth;

class AdminRoleAuthMiddleware extends BaseAuthRoleMiddleware
{
    protected function hasRole()
    {
        return $this->user->isAdmin();
    }

    protected function noRoleMessage()
    {
        return gettext('User must be an Admin');
    }
}
