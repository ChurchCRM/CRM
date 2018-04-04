<?php

namespace ChurchCRM\Slim\Middleware\Role;

class FinanceRoleAuthMiddleware extends BaseAuthRoleMiddleware {

    function hasRole()
    {
        return $this->user->isFinanceEnabled();
    }

    function noRoleMessage()
    {
        return gettext('User must have Finance permission');
    }
}
