<?php

namespace ChurchCRM\Slim\Middleware;

use ChurchCRM\Slim\Middleware\Role\BaseAuthRoleMiddleware;

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
