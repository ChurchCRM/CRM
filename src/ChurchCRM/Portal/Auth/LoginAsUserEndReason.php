<?php

namespace ChurchCRM\Portal\Auth;

/**
 * How a Login as User session ended, as `las_EndReason` stores it (member
 * sign-in design §6.5). The stored values never change once released.
 */
enum LoginAsUserEndReason: string
{
    case STOP = 'stop';
    case SIGN_OUT = 'signout';
    case TIMEOUT = 'timeout';
    /** The person could no longer be signed in as: deceased, Disable sign-in, a staff account, or signed out everywhere. */
    case ENDED = 'ended';

    public function label(): string
    {
        return match ($this) {
            self::STOP => gettext('Stopped'),
            self::SIGN_OUT => gettext('Signed out'),
            self::TIMEOUT => gettext('Timed out'),
            self::ENDED => gettext('Ended by a change to their sign-in'),
        };
    }
}
