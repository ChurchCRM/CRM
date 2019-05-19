<?php

namespace ChurchCRM\Slim\Middleware\Request\Auth;

class DeleteRecordRoleAuthMiddleware extends BaseAuthRoleMiddleware {

    function hasRole()
    {
        return $this->user->isDeleteRecordsEnabled();
    }

    function noRoleMessage()
    {
        return gettext('User must have Delete Records permission');
    }
}
