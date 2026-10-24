<?php

namespace ChurchCRM\Portal\Auth;

/**
 * How a passkey sign-in on `/portal/login` ended (member sign-in design §6.3).
 * Only SIGNED_IN starts a session.
 */
enum PasskeySignInOutcome
{
    case SIGNED_IN;
    /** No sign-in challenge was waiting in this session, or it had expired. */
    case EXPIRED;
    /** An unknown or removed passkey, a failed check, a refused person, or too many failures. */
    case FAILED;
}
