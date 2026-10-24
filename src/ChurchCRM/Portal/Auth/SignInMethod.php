<?php

namespace ChurchCRM\Portal\Auth;

/**
 * How a portal session was started (member sign-in design §5). Stored in the
 * session, on the person's sign-in row and in the audit, so the values never
 * change once released. Google, Microsoft and a mobile app are later cases
 * (`google`, `microsoft`, `app`).
 */
enum SignInMethod: string
{
    case EMAIL_LINK = 'email_link';
    case EMAIL_CODE = 'email_code';
    case DEVICE = 'device';
    case PASSKEY = 'passkey';
    case STAFF = 'staff';
    case ASSISTED = 'assisted';

    public function isEmail(): bool
    {
        return $this === self::EMAIL_LINK || $this === self::EMAIL_CODE;
    }

    /**
     * STAFF gets the blocks for everyone but not the member rule (§4, §6.4).
     * ASSISTED gets neither: only its own three checks (`PortalSignInRules`, §6.5).
     */
    public function appliesMemberRule(): bool
    {
        return $this !== self::STAFF && $this !== self::ASSISTED;
    }

    /** Login as User leaves the member's sign-in times, method and activity alone (§6.5). */
    public function updatesSignInRecord(): bool
    {
        return $this !== self::ASSISTED;
    }

    /**
     * STAFF and ASSISTED start from a staff session that is already signed in,
     * whose id was regenerated at that sign-in. Regenerating again would add no
     * protection and would end every other tab sharing the session.
     */
    public function regeneratesSessionId(): bool
    {
        return $this !== self::STAFF && $this !== self::ASSISTED;
    }

    /**
     * One principal per session (§6.4): a member way in ends a staff session left
     * in the same browser, so a member never carries staff authority. STAFF and
     * ASSISTED start from that staff session.
     */
    public function replacesStaffSession(): bool
    {
        return $this !== self::STAFF && $this !== self::ASSISTED;
    }

    /** For staff screens: the admin lists and the audit view. */
    public function label(): string
    {
        return match ($this) {
            self::EMAIL_LINK => gettext('Email link'),
            self::EMAIL_CODE => gettext('Email code'),
            self::DEVICE => gettext('Remembered device'),
            self::PASSKEY => gettext('Passkey'),
            self::STAFF => gettext('From a staff sign-in'),
            self::ASSISTED => gettext('Login as User'),
        };
    }
}
