<?php

namespace ChurchCRM\Volunteer\Service;

use ChurchCRM\Authentication\AuthenticationManager;

/**
 * The Volunteer v2 exception to the `bManageGroups` model hooks (D19, epic #9701).
 *
 * ## The problem it solves
 *
 * `Group::preSave/preInsert/preUpdate/preDelete` and the four equivalents on
 * `Person2group2roleP2g2r` call `AuthService::requireUserGroupMembership('bManageGroups')`
 * (design F21). That is the ORM refusing the write, underneath whatever the API layer
 * decided — so before D19 a ministry coordinator without the global Manage Groups flag
 * could not add anybody to their own volunteer pool, and neither could a volunteer's own
 * "I'd like to help" click.
 *
 * D19 makes a ministry own exactly one Group (`group_grp.grp_ministry_id`), so the ORM now
 * has the one fact it was missing: *whose* group this is. This class turns that fact into
 * the two narrow exceptions the hooks apply, and nothing else:
 *
 *   1. **Coordinator.** The group carries a ministry id and the current user coordinates
 *      that ministry (or is a global volunteer manager, or an administrator). The answer
 *      comes from `VolunteerAuthorizationService`, which reads `volunteer_scope_vscp` —
 *      NOT `$_SESSION` — so an API-key caller is judged exactly like a browser session.
 *      That is the whole reason A12/F21 said "no authorization in lifecycle hooks": the
 *      old one silently degraded to admin-only for API keys. This one does not.
 *
 *   2. **Managed write.** A V2 service has already authorized a write of its own and
 *      opened a context with `run()`. This is what lets a volunteer add THEMSELVES to a
 *      ministry's pool from the Open Opportunities page: they are neither an administrator
 *      nor a coordinator, and the service — not the model — is the thing that decided they
 *      may. The context is a depth counter with `try`/`finally`, so a throw inside the
 *      callable can never leave it open, and a nested call cannot close its parent's.
 *
 * ## What is deliberately unchanged
 *
 * A group whose `grp_ministry_id` is NULL — which is every group in an installation that
 * has never created a ministry — never reaches this class's ministry branch at all. The
 * hooks ask `AuthService::hasUserGroupMembership('bManageGroups')` FIRST and return when
 * it is true, so the V1 path (the flag, the administrator bypass, the thrown 401 with its
 * original message) is byte-for-byte what it was, and costs exactly what it cost.
 *
 * The context is a process-local static, not a session value and not a request attribute:
 * it exists only for the duration of one `run()` call inside one PHP process, so nothing
 * about it can leak between requests or be set by a caller from outside.
 */
final class VolunteerPoolWriter
{
    /**
     * How many `run()` calls are currently on the stack.
     *
     * A counter rather than a boolean so a service that opens a context and calls
     * another that does the same still has the context after the inner one returns.
     */
    private static int $depth = 0;

    private function __construct()
    {
    }

    /**
     * Run `$work` with the managed-write context open.
     *
     * The caller MUST have authorized the write itself before calling this — the context
     * says "a V2 service has decided this is allowed", so opening one without deciding
     * would be the silent bypass `GroupService::addUserToGroupInternal()` already is
     * (design §4.6, the second rejected workaround).
     *
     * @template T
     *
     * @param callable(): T $work
     *
     * @return T
     */
    public static function run(callable $work): mixed
    {
        ++self::$depth;

        try {
            return $work();
        } finally {
            --self::$depth;
        }
    }

    /** Is a V2 service currently doing a write it has already authorized? */
    public static function isManagedWriteOpen(): bool
    {
        return self::$depth > 0;
    }

    /**
     * May the current caller write a group that belongs to `$ministryId`?
     *
     * Returns false for `null`, which is the "this is an ordinary group" answer: the
     * caller then falls through to the unchanged `bManageGroups` requirement.
     */
    public static function mayWriteMinistryGroup(?int $ministryId): bool
    {
        if ($ministryId === null) {
            return false;
        }

        if (self::isManagedWriteOpen()) {
            return true;
        }

        $currentUser = AuthenticationManager::getCurrentUser();
        if ($currentUser === null) {
            return false;
        }

        // Scope table, never $_SESSION — so an API-key caller gets the same answer
        // a browser session does (F21, the defect this whole class exists to fix).
        return (new VolunteerAuthorizationService())->canManageMinistry($currentUser, $ministryId);
    }
}
