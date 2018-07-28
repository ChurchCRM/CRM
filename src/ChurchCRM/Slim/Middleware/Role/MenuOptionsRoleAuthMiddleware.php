<?php

namespace ChurchCRM\Slim\Middleware\Role;

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
