<?php

namespace ChurchCRM\Portal\Auth;

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\model\ChurchCRM\Person;
use ChurchCRM\model\ChurchCRM\User;
use Throwable;

/**
 * The one way portal code asks who it is serving: the principal, its person,
 * and the staff account behind it when there is one.
 *
 * `PortalSessionMiddleware` fills this in on every portal request. The pages
 * outside `/portal` that wear the portal layout (the forced password change and
 * two-factor enrolment under `/v2/user/current/`) are reached only with a staff
 * session, so there the staff account's own person is used.
 */
final class PortalContext
{
    private static bool $resolved = false;
    private static ?PortalPrincipal $principal = null;
    private static ?Person $person = null;
    private static ?User $staffUser = null;

    public static function principal(): ?PortalPrincipal
    {
        self::resolveOutsidePortal();

        return self::$principal;
    }

    public static function person(): ?Person
    {
        self::resolveOutsidePortal();

        return self::$person;
    }

    /**
     * The signed-in staff account, only when the portal session came from it
     * (method STAFF). Never set for a member session or for Login as Person, so
     * nothing a member does in the portal can act with staff authority.
     */
    public static function staffUser(): ?User
    {
        self::resolveOutsidePortal();

        return self::$staffUser;
    }

    /** @internal PortalSessionMiddleware */
    public static function set(?PortalPrincipal $principal, ?Person $person, ?User $staffUser): void
    {
        self::$resolved = true;
        self::$principal = $principal;
        self::$person = $person;
        self::$staffUser = $principal?->method === SignInMethod::STAFF ? $staffUser : null;
    }

    /** @internal PortalSessionMiddleware, before each request */
    public static function reset(): void
    {
        self::$resolved = false;
        self::$principal = null;
        self::$person = null;
        self::$staffUser = null;
    }

    private static function resolveOutsidePortal(): void
    {
        if (self::$resolved) {
            return;
        }
        self::$resolved = true;

        $user = self::activeStaffUser();
        self::$staffUser = $user;
        self::$person = $user?->getPerson();
    }

    /** The user of a staff session that is signed in and active right now. */
    public static function activeStaffUser(): ?User
    {
        try {
            if (!AuthenticationManager::validateUserSessionIsActive(false)) {
                return null;
            }
            $user = AuthenticationManager::getCurrentUser();
        } catch (Throwable) {
            return null;
        }

        return $user instanceof User ? $user : null;
    }
}
