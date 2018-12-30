<?php

namespace ChurchCRM\Slim\Middleware\Request\Auth;

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
