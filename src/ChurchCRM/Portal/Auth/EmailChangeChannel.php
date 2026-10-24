<?php

namespace ChurchCRM\Portal\Auth;

/**
 * How a person's personal email was changed (member sign-in design §9). Stored
 * in the audit and, for a change after the first sign-in, as
 * `psi_UnconfirmedSource`, so the values never change once released.
 */
enum EmailChangeChannel: string
{
    case STAFF = 'staff';
    case IMPORT = 'import';
    case API = 'api';
    case REGISTRATION = 'registration';
    case PORTAL = 'portal';
    /** Fix shared emails, on the Email needs attention list: an address two people shared was cleared. */
    case SHARED_EMAIL_FIX = 'shared_email_fix';
}
