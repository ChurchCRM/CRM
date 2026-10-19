<?php

namespace ChurchCRM\Shepherd;

use ChurchCRM\model\ChurchCRM\User;

final class SecurityPolicy
{
    public static function apiEligible(User $user): bool
    {
        return $user->getSignInBlockedReason() === null && $user->isApiAuthenticationEligible();
    }
}
