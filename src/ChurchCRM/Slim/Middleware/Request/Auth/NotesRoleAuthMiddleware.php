<?php

namespace ChurchCRM\Slim\Middleware\Request\Auth;

class NotesRoleAuthMiddleware extends BaseAuthRoleMiddleware
{
    protected function hasRole(): bool
    {
        return $this->user->isNotesEnabled();
    }

    protected function noRoleMessage(): string
    {
        return gettext('User must have Notes permission');
    }

    protected function getRoleName(): string
    {
        return 'Notes';
    }
}
