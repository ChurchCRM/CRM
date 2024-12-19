<?php

namespace ChurchCRM\Slim\Middleware\Request\Auth;

class DeleteRecordRoleAuthMiddleware extends BaseAuthRoleMiddleware
{
    public function hasRole()
    {
        return $this->user->isDeleteRecordsEnabled();
    }

    public function noRoleMessage()
    {
        return gettext('User must have Delete Records permission');
    }
}
