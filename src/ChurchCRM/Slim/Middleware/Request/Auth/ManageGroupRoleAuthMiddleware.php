<?php

namespace ChurchCRM\Slim\Middleware\Request\Auth;

class ManageGroupRoleAuthMiddleware extends BaseAuthRoleMiddleware
{
    public function hasRole()
    {
        return $this->user->isManageGroupsEnabled();
    }

    public function noRoleMessage()
    {
        return gettext('User must have Manage Groups permission');
    }
}
