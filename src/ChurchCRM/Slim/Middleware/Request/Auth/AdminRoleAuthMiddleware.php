<?php

namespace ChurchCRM\Slim\Middleware\Request\Auth;

class AdminRoleAuthMiddleware extends BaseAuthRoleMiddleware
{
    public function hasRole()
    {
        return $this->user->isAdmin();
    }

    public function noRoleMessage()
    {
        return gettext('User must be an Admin');
    }
}
