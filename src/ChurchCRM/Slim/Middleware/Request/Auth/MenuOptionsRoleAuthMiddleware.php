<?php

namespace ChurchCRM\Slim\Middleware\Request\Auth;

class MenuOptionsRoleAuthMiddleware extends BaseAuthRoleMiddleware
{
    protected function hasRole()
    {
        return $this->user->isMenuOptionsEnabled();
    }

    protected function noRoleMessage()
    {
        return gettext('User must have MenuOptions permission');
    }
}
