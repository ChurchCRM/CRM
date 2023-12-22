<?php

namespace ChurchCRM\Slim\Middleware\Request\Auth;

class DeleteRecordRoleAuthMiddleware extends BaseAuthRoleMiddleware
{
    protected function hasRole(): bool
    {
        return $this->user->isDeleteRecordsEnabled();
    }

    protected function noRoleMessage(): string
    {
        return gettext('User must have Delete Records permission');
    }
}
