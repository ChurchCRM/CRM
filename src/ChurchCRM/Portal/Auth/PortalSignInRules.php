<?php

namespace ChurchCRM\Portal\Auth;

use ChurchCRM\model\ChurchCRM\Person;
use ChurchCRM\model\ChurchCRM\PersonSignin;

/**
 * Who may hold a portal session (member sign-in design §4). The one place the
 * shared step checks at sign-in and `PortalSessionMiddleware` checks again on
 * every portal request, so a change takes effect on the person's next request.
 */
final class PortalSignInRules
{
    public static function refusalReason(?Person $person, ?PersonSignin $state, SignInMethod $method): ?SignInReason
    {
        if (!$person instanceof Person) {
            return SignInReason::PERSON_NOT_FOUND;
        }

        $blocked = $person->getSignInBlockedReason();
        if ($blocked !== null) {
            return SignInReason::from($blocked);
        }

        if ($state instanceof PersonSignin && $state->getSignInDisabled()) {
            return SignInReason::SIGN_IN_DISABLED;
        }

        // Row 4 adds, when $method->appliesMemberRule(), the blocks for member
        // sign-in only (family deactivated, awaiting review) and the member rule.

        return null;
    }
}
