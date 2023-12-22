<?php

namespace ChurchCRM\Slim\Middleware\Request\Auth;

class EditRecordsRoleAuthMiddleware extends BaseAuthRoleMiddleware
{
    protected function hasRole(): bool
    {
        return $this->user->isEditRecordsEnabled();
    }

    protected function noRoleMessage(): string
    {
        return gettext('User must have Edit Records permission');
    }
}
