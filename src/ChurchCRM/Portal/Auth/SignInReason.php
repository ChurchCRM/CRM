<?php

namespace ChurchCRM\Portal\Auth;

use ChurchCRM\model\ChurchCRM\User;

/**
 * Why a sign-in was refused or a portal session ended, as the audit stores it
 * (member sign-in design §11.2). The stored values never change once released.
 */
enum SignInReason: string
{
    case PERSON_NOT_FOUND = 'person_not_found';
    case DECEASED = User::SIGN_IN_BLOCKED_DECEASED;
    case INACTIVE = User::SIGN_IN_BLOCKED_INACTIVE;
    case FAMILY_INACTIVE = 'family_inactive';
    case NEEDS_REVIEW = 'needs_review';
    case SIGN_IN_DISABLED = User::SIGN_IN_BLOCKED_DISABLED;
    case FAMILY_ROLE = 'family_role';
    case CLASSIFICATION = 'classification';
    case UNDER_MINIMUM_AGE = 'under_minimum_age';
    case AGE_UNKNOWN = 'age_unknown';
    case NO_MATCH = 'no_match';
    case NO_EMAIL = 'no_email';
    case SHARED_EMAIL = 'shared_email';
    case EMAIL_UNCONFIRMED = 'email_unconfirmed';
    case REQUEST_ADDRESS_CHANGED = 'request_address_changed';
    case EMAIL_CHANGED = 'email_changed';
    case SESSION_VERSION = 'session_version';
    case IDLE = 'idle';
    case REPLACED = 'replaced';
    case SIGNED_OUT_EVERYWHERE = 'signed_out_everywhere';
    case SIGNED_OUT = 'signed_out';
    case THROTTLED_ADDRESS = 'throttled_address';
    case THROTTLED_IP = 'throttled_ip';
    case THROTTLED_PERSON = 'throttled_person';
    case TOO_MANY_CODES = 'too_many_codes';
    case DEVICE_REMOVED = 'device_removed';
    case DEVICE_EXPIRED = 'device_expired';
    case PASSKEY_UNKNOWN = 'passkey_unknown';
    case PASSKEY_INVALID = 'passkey_invalid';
    case STAFF_ACCOUNT = 'staff_account';
    case MEMBER_SIGN_IN_OFF = 'member_sign_in_off';

    /** For staff screens only; a person signing in never sees a reason (D3, A6). */
    public function label(): string
    {
        return match ($this) {
            self::PERSON_NOT_FOUND => gettext('No such person'),
            self::DECEASED => gettext('Deceased'),
            self::INACTIVE => gettext('Inactive'),
            self::FAMILY_INACTIVE => gettext('The family is inactive'),
            self::NEEDS_REVIEW => gettext('Awaiting review'),
            self::SIGN_IN_DISABLED => gettext('Sign-in is disabled'),
            self::FAMILY_ROLE => gettext('Their family role may not sign in'),
            self::CLASSIFICATION => gettext('Their classification may not sign in'),
            self::UNDER_MINIMUM_AGE => gettext('Younger than the minimum age'),
            self::AGE_UNKNOWN => gettext('No birth date, and not a head of household or spouse'),
            self::NO_MATCH => gettext('No person has this email'),
            self::NO_EMAIL => gettext('No personal email'),
            self::SHARED_EMAIL => gettext('Their email is shared with someone else'),
            self::EMAIL_UNCONFIRMED => gettext('Their email is not confirmed yet'),
            self::REQUEST_ADDRESS_CHANGED => gettext('The code or link went to an address that is no longer their email'),
            self::EMAIL_CHANGED => gettext('Their email changed'),
            self::SESSION_VERSION => gettext('Signed out by a change to their sign-in'),
            self::IDLE => gettext('Idle too long'),
            self::REPLACED => gettext('Replaced by another sign-in'),
            self::SIGNED_OUT_EVERYWHERE => gettext('Signed out everywhere'),
            self::SIGNED_OUT => gettext('Signed out'),
            self::THROTTLED_ADDRESS => gettext('Too many sign-in requests for this email address'),
            self::THROTTLED_IP => gettext('Too many sign-in requests from this IP address'),
            self::THROTTLED_PERSON => gettext('Too many sign-in emails today'),
            self::TOO_MANY_CODES => gettext('Too many wrong codes'),
            self::DEVICE_REMOVED => gettext('Their remembered device was signed out'),
            self::DEVICE_EXPIRED => gettext('Their remembered device had expired'),
            self::PASSKEY_UNKNOWN => gettext('The passkey is not on record, or was removed'),
            self::PASSKEY_INVALID => gettext('The passkey could not be verified'),
            self::STAFF_ACCOUNT => gettext('They have a staff account'),
            self::MEMBER_SIGN_IN_OFF => gettext('Member sign-in is off'),
        };
    }
}
