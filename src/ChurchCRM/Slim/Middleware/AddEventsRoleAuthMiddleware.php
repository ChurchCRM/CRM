<?php

namespace ChurchCRM\Slim\Middleware;

use ChurchCRM\Slim\Middleware\Role\BaseAuthRoleMiddleware;

class AddEventsRoleAuthMiddleware extends BaseAuthRoleMiddleware {

    function hasRole()
    {
        return $this->user->isAddEvent();
    }

    function noRoleMessage()
    {
        return gettext('User must have bAddEvent permissions');
    }
}
