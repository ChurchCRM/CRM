<?php

namespace ChurchCRM\Slim\Middleware\Request\Auth;

class MenuOptionsRoleAuthMiddleware extends BaseAuthRoleMiddleware
{
    public function hasRole()
    {
        return $this->user->isMenuOptionsEnabled();
    }

    public function noRoleMessage()
    {
        return gettext('User must have MenuOptions permission');
    }
}
