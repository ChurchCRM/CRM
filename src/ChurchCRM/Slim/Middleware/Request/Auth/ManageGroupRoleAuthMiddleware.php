<?php

namespace ChurchCRM\Slim\Middleware\Request\Auth;

class ManageGroupRoleAuthMiddleware extends BaseAuthRoleMiddleware {

    function hasRole()
    {
        return $this->user->isManageGroupsEnabled();
    }

    function noRoleMessage()
    {
        return gettext('User must have Manage Groups permission');
    }
}
