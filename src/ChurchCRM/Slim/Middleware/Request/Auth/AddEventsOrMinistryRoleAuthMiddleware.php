<?php

namespace ChurchCRM\Slim\Middleware\Request\Auth;

/**
 * The event-write gate, widened by exactly one tier for Volunteer v2 (#9713, design §4.6, D9).
 *
 * `AddEventsRoleAuthMiddleware` answers a single global boolean, and there is no per-row event
 * authorization anywhere in ChurchCRM. D9 needs one: a ministry coordinator must be able to
 * create and edit the events **their** ministry owns without being handed the global AddEvent
 * right over every event in the church.
 *
 * Design §4.5 puts that decision in three layers, and this class is only the first of them:
 *
 *   1. THIS middleware — "does this caller have the coarse capability at all?" It lets a
 *      volunteer coordinator through to the handler. It deliberately cannot answer anything
 *      about a specific event: it runs before route arguments are resolved into entities, and
 *      `BaseAuthRoleMiddleware` has no entity hook.
 *   2. `EventsMiddleware` — loads the row, or 404s.
 *   3. The handler — asks `VolunteerAuthorizationService::canManageMinistry()` about that row's
 *      `event_ministry_id` and refuses with 403 when the answer is no. `eventWriteGuard()` in
 *      `src/api/routes/calendar/events.php` is the single implementation.
 *
 * Chained on **only** the five write routes §4.6 names — `newEvent`, `updateEvent`,
 * `setEventTime`, `setEventStatus`, `deleteEvent` — plus the `/event/editor` page they are
 * reached from. Every other AddEvent-gated route (check-in/out, quick-create, the repeat
 * generator, calendar administration, the audit sweep) keeps the untouched
 * `AddEventsRoleAuthMiddleware`, because nothing in D9 asks for those.
 *
 * With the rollout flag off, `User::canWriteEvents()` collapses back to `canManageEvents()`
 * and this class behaves exactly like its sibling.
 */
class AddEventsOrMinistryRoleAuthMiddleware extends BaseAuthRoleMiddleware
{
    protected function hasRole(): bool
    {
        return $this->user->canWriteEvents();
    }

    protected function noRoleMessage(): string
    {
        return gettext('User must have Add Event permission, or coordinate a volunteer ministry, and the Events module must be enabled');
    }

    protected function getRoleName(): string
    {
        // Same access-denied role name as AddEventsRoleAuthMiddleware: to a user who is
        // turned away the missing capability is still "Add Event", and src/v2/routes/root.php
        // already knows how to explain that one.
        return 'AddEvent';
    }
}
