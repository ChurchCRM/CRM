<?php

namespace ChurchCRM\Service;

use ChurchCRM\model\ChurchCRM\Family;
use ChurchCRM\model\ChurchCRM\Note;
use ChurchCRM\model\ChurchCRM\NoteQuery;
use ChurchCRM\model\ChurchCRM\Person;
use ChurchCRM\model\ChurchCRM\PersonSigninQuery;
use ChurchCRM\Utils\DateTimeUtils;
use Propel\Runtime\ActiveQuery\Criteria;

/**
 * Where a family stands on verification: when it last confirmed its details, when it was last
 * invited, and when a member last signed in to the portal. Dates arrive formatted for display.
 */
class FamilyVerificationStatusService
{
    /**
     * @return array{verified: ?array{date: string, byFamily: bool}, invited: ?string, lastSignIn: ?string}
     */
    public function forFamily(Family $family): array
    {
        $verified = $this->latestNote($family, 'verify');
        $invited = $this->latestNote($family, 'verify-link');

        return [
            'verified' => $verified === null ? null : [
                'date' => $this->display($verified),
                'byFamily' => (int) $verified->getEnteredBy() === Person::SELF_VERIFY,
            ],
            'invited' => $invited === null ? null : $this->display($invited),
            'lastSignIn' => $this->lastSignIn($family),
        ];
    }

    private function latestNote(Family $family, string $type): ?Note
    {
        return NoteQuery::create()
            ->filterByFamId($family->getId())
            ->filterByType($type)
            ->orderByDateEntered(Criteria::DESC)
            ->findOne();
    }

    private function lastSignIn(Family $family): ?string
    {
        $personIds = [];
        foreach ($family->getPeople() as $person) {
            $personIds[] = $person->getId();
        }
        if ($personIds === []) {
            return null;
        }

        $state = PersonSigninQuery::create()
            ->filterByPersonId($personIds)
            ->filterByLastSignIn(null, Criteria::ISNOTNULL)
            ->orderByLastSignIn(Criteria::DESC)
            ->findOne();

        return $state === null ? null : DateTimeUtils::formatDate($state->getLastSignIn('Y-m-d H:i:s'), true);
    }

    private function display(Note $note): string
    {
        return DateTimeUtils::formatDate($note->getDateEntered('Y-m-d H:i:s'));
    }
}
