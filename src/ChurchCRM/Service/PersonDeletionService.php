<?php

namespace ChurchCRM\Service;

use ChurchCRM\Exceptions\PersonDeletionBlockedException;
use ChurchCRM\model\ChurchCRM\EmailLogQuery;
use ChurchCRM\model\ChurchCRM\EventAttendQuery;
use ChurchCRM\model\ChurchCRM\NoteQuery;
use ChurchCRM\model\ChurchCRM\MultibuyQuery;
use ChurchCRM\model\ChurchCRM\PaddleNumQuery;
use ChurchCRM\model\ChurchCRM\Person;
use ChurchCRM\model\ChurchCRM\PersonQuery;
use ChurchCRM\model\ChurchCRM\PledgeQuery;
use ChurchCRM\model\ChurchCRM\VolunteerAssignmentQuery;
use ChurchCRM\model\ChurchCRM\VolunteerNotificationQuery;
use ChurchCRM\model\ChurchCRM\VolunteerResponseQuery;
use ChurchCRM\model\ChurchCRM\VolunteerSwapQuery;
use ChurchCRM\model\ChurchCRM\WhyCameQuery;
use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\Connection\ConnectionInterface;

final class PersonDeletionService
{
    public static function assertCanDelete(Person $person, ?ConnectionInterface $connection = null): void
    {
        $personId = (int) $person->getId();
        $categories = [];

        if (EventAttendQuery::create()->filterByPersonId($personId)->exists($connection)) {
            $categories[] = gettext('attendance');
        }

        $familyId = (int) $person->getFamId();
        $hasFamilyGiving = $familyId > 0
            && PersonQuery::create()->filterByFamId($familyId)->count($connection) === 1
            && PledgeQuery::create()->filterByFamId($familyId)->exists($connection);
        $editedFinancialRecords = PledgeQuery::create()->filterByEditedBy($personId)->exists($connection);
        if ($hasFamilyGiving || $editedFinancialRecords) {
            $categories[] = gettext('financial history');
        }

        if (self::hasSubstantiveNotes(NoteQuery::create()->filterByPerId($personId), $connection)
            || self::hasSubstantiveNotes(NoteQuery::create()->filterByEnteredBy($personId), $connection)
            || self::hasSubstantiveNotes(NoteQuery::create()->filterByEditedBy($personId), $connection)
        ) {
            $categories[] = gettext('notes');
        }

        if (WhyCameQuery::create()->filterByPerId($personId)->exists($connection)
            || MultibuyQuery::create()->filterByMbPerId($personId)->exists($connection)
            || PaddleNumQuery::create()->filterByPnPerId($personId)->exists($connection)
        ) {
            $categories[] = gettext('recorded activities');
        }

        if (EmailLogQuery::create()
            ->filterByPerId($personId)
            ->_or()->filterByUsrId($personId)
            ->exists($connection)
        ) {
            $categories[] = gettext('communication history');
        }

        if (VolunteerAssignmentQuery::create()->filterByPersonId($personId)->exists($connection)
            || VolunteerResponseQuery::create()->filterByPersonId($personId)->exists($connection)
            || VolunteerSwapQuery::create()
                ->filterByProposedByPersonId($personId)
                ->_or()->filterByProposedPersonId($personId)
                ->exists($connection)
            || VolunteerNotificationQuery::create()->filterByPersonId($personId)->exists($connection)
        ) {
            $categories[] = gettext('volunteer service history');
        }

        if ($categories !== []) {
            $message = gettext("This person can't be deleted because they have recorded activities. Deactivate the person instead to preserve their history.");
            $message .= ' ' . sprintf(gettext('Recorded history: %s.'), implode(', ', array_unique($categories)));
            throw new PersonDeletionBlockedException($message);
        }
    }

    private static function hasSubstantiveNotes(NoteQuery $query, ?ConnectionInterface $connection): bool
    {
        $allNotes = clone $query;

        return $query->filterByType(['create', 'edit'], Criteria::NOT_IN)->exists($connection)
            || $allNotes->filterByType(null, Criteria::ISNULL)->exists($connection);
    }
}
