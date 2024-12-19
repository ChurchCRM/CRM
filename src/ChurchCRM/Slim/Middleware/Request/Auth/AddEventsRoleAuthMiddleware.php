<?php

namespace ChurchCRM\Slim\Middleware\Request\Auth;

class AddEventsRoleAuthMiddleware extends BaseAuthRoleMiddleware
{
    public function hasRole()
    {
        return $this->user->isAddEvent();
    }

    public function noRoleMessage()
    {
        return gettext('User must have bAddEvent permissions');
    }
}
