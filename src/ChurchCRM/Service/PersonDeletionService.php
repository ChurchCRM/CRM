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
use ChurchCRM\Service\EmailLogService;
use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\Connection\ConnectionInterface;

final class PersonDeletionService
{
    public static function assertCanDelete(Person $person, ?ConnectionInterface $connection = null): void
    {
        $blockedReason = $person->getLoginDeletionBlockedReason();
        if ($blockedReason !== null) {
            throw new PersonDeletionBlockedException($blockedReason);
        }

        $personId = (int) $person->getId();
        $categories = [];

        // Note writes lock the same person row (Note::preSave), so a note
        // saved while this deletion runs waits for it instead of being read
        // as absent here and then deleted with the person.
        self::lockPeople([$personId], $connection);

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

        // A recipient row still queued with no delivery attempt is not
        // history yet; deletion cancels it (cancelUnsentMail).
        if (EmailLogQuery::create()->filterByUsrId($personId)->exists($connection)
            || EmailLogQuery::create()->filterByPerId($personId)->filterByStatus(EmailLogService::STATUS_QUEUED, Criteria::NOT_EQUAL)->exists($connection)
            || EmailLogQuery::create()->filterByPerId($personId)->filterByAttempts(0, Criteria::GREATER_THAN)->exists($connection)
        ) {
            $categories[] = gettext('communication history');
        }

        if (VolunteerAssignmentQuery::create()->filterByPersonId($personId)->exists($connection)
            || VolunteerResponseQuery::create()->filterByPersonId($personId)->exists($connection)
            || VolunteerSwapQuery::create()
                ->filterByProposedByPersonId($personId)
                ->_or()->filterByProposedPersonId($personId)
                ->exists($connection)
            // A notification never attempted and never sent (pending, or
            // skipped without a try) is not history; it cascades away.
            || VolunteerNotificationQuery::create()->filterByPersonId($personId)->filterByAttempts(0, Criteria::GREATER_THAN)->exists($connection)
            || VolunteerNotificationQuery::create()->filterByPersonId($personId)->filterBySentDate(null, Criteria::ISNOTNULL)->exists($connection)
        ) {
            $categories[] = gettext('volunteer service history');
        }

        if ($categories !== []) {
            $message = gettext("This person can't be deleted because they have recorded activities. Deactivate the person instead to preserve their history.");
            $message .= ' ' . sprintf(gettext('Recorded history: %s.'), implode(', ', array_unique($categories)));
            throw new PersonDeletionBlockedException($message);
        }
    }

    /**
     * Refuses deleting a family's notes with the family when any of them is a
     * substantive note (not an automatic create or edit entry).
     */
    public static function assertFamilyNotesCanBeDeleted(int $familyId, ?ConnectionInterface $connection = null): void
    {
        if (self::hasSubstantiveNotes(NoteQuery::create()->filterByFamId($familyId), $connection)) {
            throw new PersonDeletionBlockedException(gettext("This family can't be deleted because it has notes. Deactivate the family instead to preserve their history."));
        }
    }

    /**
     * Cancels the person's queued mail that no delivery attempt has touched.
     * The status and attempt conditions make it skip a row the queue worker
     * has just claimed.
     */
    public static function cancelUnsentMail(int $personId, ?ConnectionInterface $connection = null): void
    {
        EmailLogQuery::create()
            ->filterByPerId($personId)
            ->filterByStatus(EmailLogService::STATUS_QUEUED)
            ->filterByAttempts(0)
            ->delete($connection);
    }

    /**
     * Locks person rows for the rest of the current transaction, in id order
     * so two writers never wait on each other.
     *
     * @param int[] $personIds
     */
    public static function lockPeople(array $personIds, ?ConnectionInterface $connection = null): void
    {
        $personIds = array_values(array_unique(array_filter(array_map('intval', $personIds), fn (int $id): bool => $id > 0)));
        if ($personIds === [] || $connection === null || !$connection->inTransaction()) {
            return;
        }
        sort($personIds);
        PersonQuery::create()
            ->filterById($personIds, Criteria::IN)
            ->orderById()
            ->lockForUpdate()
            ->select(['Id'])
            ->find($connection);
    }

    private static function hasSubstantiveNotes(NoteQuery $query, ?ConnectionInterface $connection): bool
    {
        $allNotes = clone $query;

        return $query->filterByType(['create', 'edit'], Criteria::NOT_IN)->exists($connection)
            || $allNotes->filterByType(null, Criteria::ISNULL)->exists($connection);
    }
}
