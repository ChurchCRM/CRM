<?php

namespace ChurchCRM\Slim\Middleware\Request\Auth;

class ViewRecordsRoleAuthMiddleware extends BaseAuthRoleMiddleware
{
    protected function hasRole(): bool
    {
        return $this->user->isEditRecordsEnabled();
    }

    protected function noRoleMessage(): string
    {
        return gettext('User must have Edit Records permission to view person records');
    }

    protected function getRoleName(): string
    {
        return 'EditRecords';
    }
}
