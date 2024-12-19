<?php

namespace ChurchCRM\Slim\Middleware\Request\Auth;

class EditRecordsRoleAuthMiddleware extends BaseAuthRoleMiddleware
{
    public function hasRole()
    {
        return $this->user->isEditRecordsEnabled();
    }

    public function noRoleMessage()
    {
        return gettext('User must have Edit Records permission');
    }
}
