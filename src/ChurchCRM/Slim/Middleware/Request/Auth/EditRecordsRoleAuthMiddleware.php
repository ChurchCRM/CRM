<?php

namespace ChurchCRM\Slim\Middleware\Request\Auth;

class EditRecordsRoleAuthMiddleware extends BaseAuthRoleMiddleware
{
    protected function hasRole()
    {
        return $this->user->isEditRecordsEnabled();
    }

    protected function noRoleMessage()
    {
        return gettext('User must have Edit Records permission');
    }
}
