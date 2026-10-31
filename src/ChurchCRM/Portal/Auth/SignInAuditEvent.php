<?php

namespace ChurchCRM\Portal\Auth;

/**
 * Every event the member sign-in audit records (design §11.2). Declared in full
 * here so the rows that write them share one list.
 */
enum SignInAuditEvent: string
{
    case EMAIL_REQUESTED = 'email_requested';
    case REQUEST_CANCELLED = 'request_cancelled';
    case SIGNED_IN = 'signed_in';
    case REFUSED = 'refused';
    case THROTTLED = 'throttled';
    case SESSION_ENDED = 'session_ended';
    case DEVICE_ADDED = 'device_added';
    case DEVICE_REMOVED = 'device_removed';
    case PASSKEY_ADDED = 'passkey_added';
    case PASSKEY_REMOVED = 'passkey_removed';
    case EMAIL_CHANGED = 'email_changed';
    case EMAIL_CONFIRMED = 'email_confirmed';
    case SIGN_IN_DISABLED = 'sign_in_disabled';
    case SIGN_IN_ENABLED = 'sign_in_enabled';
    case GRANT_ADDED = 'grant_added';
    case GRANT_REMOVED = 'grant_removed';
    case LOGIN_AS_STARTED = 'login_as_started';
    case LOGIN_AS_STOPPED = 'login_as_stopped';
    case FAMILY_CONFIRMED = 'family_confirmed';

    /** For staff screens: the audit view on Admin → Member Portal. */
    public function label(): string
    {
        return match ($this) {
            self::EMAIL_REQUESTED => gettext('Sign-in email requested'),
            self::REQUEST_CANCELLED => gettext('Emailed sign-in code and link cancelled'),
            self::SIGNED_IN => gettext('Signed in'),
            self::REFUSED => gettext('Sign-in refused'),
            self::THROTTLED => gettext('Too many attempts'),
            self::SESSION_ENDED => gettext('Session ended'),
            self::DEVICE_ADDED => gettext('Device remembered'),
            self::DEVICE_REMOVED => gettext('Remembered device removed'),
            self::PASSKEY_ADDED => gettext('Passkey added'),
            self::PASSKEY_REMOVED => gettext('Passkey removed'),
            self::EMAIL_CHANGED => gettext('Email changed'),
            self::EMAIL_CONFIRMED => gettext('Email confirmed'),
            self::SIGN_IN_DISABLED => gettext('Sign-in disabled'),
            self::SIGN_IN_ENABLED => gettext('Sign-in enabled'),
            self::GRANT_ADDED => gettext('Portal permission given'),
            self::GRANT_REMOVED => gettext('Portal permission removed'),
            self::LOGIN_AS_STARTED => gettext('Login as Person started'),
            self::LOGIN_AS_STOPPED => gettext('Login as Person stopped'),
            self::FAMILY_CONFIRMED => gettext('Family details confirmed'),
        };
    }
}
