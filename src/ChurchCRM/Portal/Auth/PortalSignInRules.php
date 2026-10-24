<?php

namespace ChurchCRM\Portal\Auth;

use ChurchCRM\model\ChurchCRM\Person;
use ChurchCRM\model\ChurchCRM\PersonQuery;
use ChurchCRM\model\ChurchCRM\PersonSignin;
use Propel\Runtime\ActiveQuery\Criteria;

/**
 * Who may hold a portal session (member sign-in design §4). The one place the
 * shared step checks at sign-in and `PortalSessionMiddleware` checks again on
 * every portal request, so a change takes effect on the person's next request.
 */
final class PortalSignInRules
{
    public static function refusalReason(
        ?Person $person,
        ?PersonSignin $state,
        SignInMethod $method,
        ?MemberSignInRule $memberRule = null,
    ): ?SignInReason {
        if (!$person instanceof Person) {
            return SignInReason::PERSON_NOT_FOUND;
        }

        $blocked = Person::signInBlockedReasonFor(
            $person->getDateDeceased(),
            $person->getDateDeactivated(),
            $state instanceof PersonSignin && $state->getSignInDisabled(),
        );
        if ($blocked !== null) {
            return SignInReason::from($blocked);
        }

        if (!$method->appliesMemberRule()) {
            return null;
        }
        // Member sign-in only, so no staff account is locked out by its family's status or a review flag.
        if (!empty($person->getFamily()?->getDateDeactivated())) {
            return SignInReason::FAMILY_INACTIVE;
        }
        if ((bool) $person->getNeedsReview()) {
            return SignInReason::NEEDS_REVIEW;
        }

        return ($memberRule ?? MemberSignInRule::fromSettings())->check($person);
    }

    /**
     * Narrows an unaliased PersonQuery to the people none of the blocks for
     * everyone apply to: living, active, and without Disable sign-in.
     */
    public static function filterNotBlocked(PersonQuery $query): PersonQuery
    {
        return $query
            ->filterByDateDeceased(null, Criteria::ISNULL)
            ->filterByDateDeactivated(null, Criteria::ISNULL)
            ->usePersonSigninNotExistsQuery()
                ->filterBySignInDisabled(true)
            ->endUse();
    }

    /**
     * `filterNotBlocked()` plus the blocks for member sign-in: in an active
     * family or none, and not awaiting review.
     */
    public static function filterMemberNotBlocked(PersonQuery $query): PersonQuery
    {
        return self::filterNotBlocked($query)
            ->filterByNeedsReview(false)
            ->useFamilyQuery(null, Criteria::LEFT_JOIN)
                ->filterByDateDeactivated(null, Criteria::ISNULL)
            ->endUse();
    }
}
