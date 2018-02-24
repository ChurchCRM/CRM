<?php

namespace ChurchCRM\Slim\Middleware\Role;

class AdminRoleAuthMiddleware extends BaseAuthRoleMiddleware
{

    function hasRole()
    {
        return $this->user->isAdmin();
    }

    function noRoleMessage()
    {
        return gettext('User must be an Admin');
    }
}
