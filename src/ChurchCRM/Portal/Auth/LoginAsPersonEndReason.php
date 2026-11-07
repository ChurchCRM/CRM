<?php

namespace ChurchCRM\Portal\Auth;

/**
 * How a Login as Person session ended, as `lps_EndReason` stores it (member
 * sign-in design §6.5). The stored values never change once released.
 */
enum LoginAsPersonEndReason: string
{
    case STOP = 'stop';
    case SIGN_OUT = 'signout';
    case TIMEOUT = 'timeout';
    /** The person could no longer be signed in as: deceased, Disable sign-in, a staff account, or signed out everywhere. */
    case ENDED = 'ended';
    case MEMBER_SIGN_IN_OFF = 'signin_off';

    public function label(): string
    {
        return match ($this) {
            self::STOP => gettext('Stopped'),
            self::SIGN_OUT => gettext('Signed out'),
            self::TIMEOUT => gettext('Timed out'),
            self::ENDED => gettext('Ended by a change to their sign-in'),
            self::MEMBER_SIGN_IN_OFF => gettext('Ended when member sign-in was turned off'),
        };
    }
}
