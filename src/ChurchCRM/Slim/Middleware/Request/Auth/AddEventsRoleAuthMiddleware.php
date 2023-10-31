<?php

namespace ChurchCRM\Slim\Middleware\Request\Auth;

class AddEventsRoleAuthMiddleware extends BaseAuthRoleMiddleware
{
    protected function hasRole()
    {
        return $this->user->isAddEvent();
    }

    protected function noRoleMessage()
    {
        return gettext('User must have bAddEvent permissions');
    }
}
