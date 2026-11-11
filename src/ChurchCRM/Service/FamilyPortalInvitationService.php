<?php

namespace ChurchCRM\Service;

use ChurchCRM\dto\SystemConfig;
use ChurchCRM\model\ChurchCRM\Family;
use ChurchCRM\model\ChurchCRM\Person;
use ChurchCRM\Portal\Auth\MemberSignInRule;

/**
 * Who a family's member portal invitation reaches. The invitation asks members
 * to sign in with the address it was sent to, so it goes only to the personal
 * email of a living member who may sign in with it now (member sign-in design §4).
 */
final class FamilyPortalInvitationService
{
    /**
     * @return array{emails: list<string>, signInOff: bool, cannotSignIn: list<array{name: string, reason: string}>}
     *     `cannotSignIn` lists every other living member and why; it is empty while member sign-in is off
     */
    public function recipientsFor(Family $family): array
    {
        if (!SystemConfig::getBooleanValue('bMemberSignIn')) {
            return ['emails' => [], 'signInOff' => true, 'cannotSignIn' => []];
        }

        $members = array_values(array_filter($family->getPeopleSorted(), static fn (Person $person): bool => !$person->isDeceased()));
        $reasons = MemberSignInRule::fromSettings()->statusesFor($members);

        $emails = [];
        $cannotSignIn = [];
        foreach ($members as $member) {
            $reason = $reasons[(int) $member->getId()] ?? null;
            if ($reason === null) {
                $emails[] = trim((string) $member->getEmail());
            } else {
                $cannotSignIn[] = ['name' => $member->getFullName(), 'reason' => $reason->label()];
            }
        }

        return ['emails' => $emails, 'signInOff' => false, 'cannotSignIn' => $cannotSignIn];
    }

    /**
     * What staff are told when `recipientsFor()` found nobody to invite.
     *
     * @param array{signInOff: bool} $recipients
     */
    public function nobodyCanSignInMessage(Family $family, array $recipients): string
    {
        return sprintf(
            $recipients['signInOff']
                ? gettext('Member sign-in is off, so nobody in the %s family can sign in to the member portal.')
                : gettext('Nobody in the %s family can sign in to the member portal under the current sign-in rule.'),
            $family->getName(),
        );
    }
}
