<?php

namespace ChurchCRM\Slim\Middleware\Request\Auth;

class DeleteRecordRoleAuthMiddleware extends BaseAuthRoleMiddleware
{
    protected function hasRole()
    {
        return $this->user->isDeleteRecordsEnabled();
    }

    protected function noRoleMessage()
    {
        return gettext('User must have Delete Records permission');
    }
}
