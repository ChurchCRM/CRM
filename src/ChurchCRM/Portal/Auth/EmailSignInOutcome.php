<?php

namespace ChurchCRM\Portal\Auth;

/**
 * How an email link or code sign-in ended (member sign-in design §6.1). Only
 * SIGNED_IN starts a session; the others decide which page the person sees.
 */
enum EmailSignInOutcome
{
    case SIGNED_IN;
    /** The link is unknown, expired, used or cancelled. */
    case LINK_UNAVAILABLE;
    /** A wrong code, or no live request bound to this browser. */
    case CODE_NOT_ACCEPTED;
    /** The request was spent, but the shared step refused the person. */
    case REFUSED;
}
