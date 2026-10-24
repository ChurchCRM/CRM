<?php

namespace ChurchCRM\Portal\Auth;

use ChurchCRM\model\ChurchCRM\Person;
use ChurchCRM\model\ChurchCRM\PersonQuery;
use ChurchCRM\model\ChurchCRM\PersonSignin;
use ChurchCRM\model\ChurchCRM\User;
use ChurchCRM\model\ChurchCRM\UserQuery;
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

        if ($method === SignInMethod::ASSISTED) {
            return self::loginAsUserRefusal($person, $state);
        }

        $blocked = Person::signInBlockedReasonFor(
            $person->getDateDeceased(),
            $person->getDateDeactivated(),
            $person->getFamily()?->getDateDeactivated(),
            (bool) $person->getNeedsReview(),
            $state instanceof PersonSignin && $state->getSignInDisabled(),
        );
        if ($blocked !== null) {
            return SignInReason::from($blocked);
        }

        if ($method->appliesMemberRule()) {
            return ($memberRule ?? MemberSignInRule::fromSettings())->check($person);
        }

        return null;
    }

    /**
     * Login as User checks only these (design §6.5): the office may need to see
     * what an inactive person, or one outside the member rule, would see.
     */
    private static function loginAsUserRefusal(Person $person, ?PersonSignin $state): ?SignInReason
    {
        if (!empty($person->getDateDeceased())) {
            return SignInReason::DECEASED;
        }
        if ($state instanceof PersonSignin && $state->getSignInDisabled()) {
            return SignInReason::SIGN_IN_DISABLED;
        }
        $user = UserQuery::create()->findPk($person->getId());
        if ($user instanceof User && !$user->isEditSelfExclusive()) {
            return SignInReason::STAFF_ACCOUNT;
        }

        return null;
    }

    /**
     * Narrows an unaliased PersonQuery to the people none of the blocks for
     * everyone apply to: living, active, in an active family or none, not
     * awaiting review, and without Disable sign-in.
     */
    public static function filterNotBlocked(PersonQuery $query): PersonQuery
    {
        return $query
            ->filterByDateDeceased(null, Criteria::ISNULL)
            ->filterByDateDeactivated(null, Criteria::ISNULL)
            ->filterByNeedsReview(false)
            ->useFamilyQuery(null, Criteria::LEFT_JOIN)
                ->filterByDateDeactivated(null, Criteria::ISNULL)
            ->endUse()
            ->usePersonSigninNotExistsQuery()
                ->filterBySignInDisabled(true)
            ->endUse();
    }
}
