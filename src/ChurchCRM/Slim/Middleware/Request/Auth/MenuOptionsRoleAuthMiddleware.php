<?php

namespace ChurchCRM\Slim\Middleware\Request\Auth;

class MenuOptionsRoleAuthMiddleware extends BaseAuthRoleMiddleware {

    function hasRole()
    {
        return $this->user->isMenuOptionsEnabled();
    }

    function noRoleMessage()
    {
        return gettext('User must have MenuOptions permission');
    }
}
