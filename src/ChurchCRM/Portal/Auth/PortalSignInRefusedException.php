<?php

namespace ChurchCRM\Portal\Auth;

use RuntimeException;

/**
 * Thrown by `PortalSessionStarter::start()` when the person may not sign in.
 * The reason is for the audit and the caller's logic; never show it to the
 * person signing in (design D3, A6).
 */
final class PortalSignInRefusedException extends RuntimeException
{
    public function __construct(public readonly SignInReason $reason)
    {
        parent::__construct('Portal sign-in refused: ' . $reason->value);
    }
}
