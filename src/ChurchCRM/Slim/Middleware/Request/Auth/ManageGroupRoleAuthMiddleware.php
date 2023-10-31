<?php

namespace ChurchCRM\Slim\Middleware\Request\Auth;

class ManageGroupRoleAuthMiddleware extends BaseAuthRoleMiddleware
{
    protected function hasRole()
    {
        return $this->user->isManageGroupsEnabled();
    }

    protected function noRoleMessage()
    {
        return gettext('User must have Manage Groups permission');
    }
}
