<?php

namespace ChurchCRM\Portal\Auth;

/**
 * Every event the member sign-in audit records (design §11.2). Declared in full
 * here so the rows that write them share one list.
 */
enum SignInAuditEvent: string
{
    case EMAIL_REQUESTED = 'email_requested';
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
}
