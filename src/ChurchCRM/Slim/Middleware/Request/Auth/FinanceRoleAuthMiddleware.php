<?php

namespace ChurchCRM\Slim\Middleware\Request\Auth;

class FinanceRoleAuthMiddleware extends BaseAuthRoleMiddleware
{
    public function hasRole()
    {
        return $this->user->isFinanceEnabled();
    }

    public function noRoleMessage()
    {
        return gettext('User must have Finance permission');
    }
}
