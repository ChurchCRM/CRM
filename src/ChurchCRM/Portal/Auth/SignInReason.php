<?php

namespace ChurchCRM\Portal\Auth;

use ChurchCRM\model\ChurchCRM\User;

/**
 * Why a sign-in was refused or a portal session ended, as the audit stores it
 * (member sign-in design §11.2). Later rows add their own cases: the family and
 * review blocks and the member rule (row 4), the email-rule refusals (row 5).
 */
enum SignInReason: string
{
    case PERSON_NOT_FOUND = 'person_not_found';
    case DECEASED = User::SIGN_IN_BLOCKED_DECEASED;
    case INACTIVE = User::SIGN_IN_BLOCKED_INACTIVE;
    case SIGN_IN_DISABLED = 'sign_in_disabled';
    case SESSION_VERSION = 'session_version';
    case IDLE = 'idle';
    case REPLACED = 'replaced';
}
