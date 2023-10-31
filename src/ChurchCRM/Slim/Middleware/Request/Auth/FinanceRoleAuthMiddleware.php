<?php

namespace ChurchCRM\Slim\Middleware\Request\Auth;

class FinanceRoleAuthMiddleware extends BaseAuthRoleMiddleware
{
    protected function hasRole()
    {
        return $this->user->isFinanceEnabled();
    }

    protected function noRoleMessage()
    {
        return gettext('User must have Finance permission');
    }
}
