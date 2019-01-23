<?php

namespace ChurchCRM\Slim\Middleware\Request\Auth;

class DeleteRecordRoleAuthMiddleware extends BaseAuthRoleMiddleware {

    function hasRole()
    {
        return $this->user->isDeleteRecords();
    }

    function noRoleMessage()
    {
        return gettext('User must have Delete Records permission');
    }
}
