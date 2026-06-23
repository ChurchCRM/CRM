<?php

namespace ChurchCRM\Slim\Middleware\Request\Auth;

/**
 * Enforces Notes read permission: Notes=1 OR Admin.
 *
 * Use this middleware on read-only notes and timeline routes.
 * Use NotesRoleAuthMiddleware on write routes (POST/PUT/DELETE) — they share
 * the same underlying isNotesEnabled() gate but having distinct middleware
 * classes makes the intent explicit at the route-mount site.
 *
 * Policy: any user who cannot read notes at all (plain-auth, EditRecords-only,
 * etc.) receives 403. Private-note visibility is further filtered at the
 * object level via Note::isVisibleTo(User).
 */
class NotesReadAuthMiddleware extends BaseAuthRoleMiddleware
{
    protected function hasRole(): bool
    {
        return $this->user->canReadNotes();
    }

    protected function noRoleMessage(): string
    {
        return gettext('Notes read permission required');
    }

    protected function getRoleName(): string
    {
        return 'NotesRead';
    }
}
