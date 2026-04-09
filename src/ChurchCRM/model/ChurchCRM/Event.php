<?php

namespace ChurchCRM\model\ChurchCRM;

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\model\ChurchCRM\Base\Event as BaseEvent;
use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\Map\TableMap;

/**
 * Skeleton subclass for representing a row from the 'events_event' table.
 *
 *
 *
 * You should add additional methods to this class to meet the
 * application requirements.  This class will only be generated as
 * long as it does not already exist in the output directory.
 */
class Event extends BaseEvent
{
    private bool $editable = true;

    public function toArray(string $keyType = TableMap::TYPE_PHPNAME, bool $includeLazyLoadColumns = true, array $alreadyDumpedObjects = [], bool $includeForeignObjects = false): array
    {
        $array = parent::toArray($keyType, $includeLazyLoadColumns, $alreadyDumpedObjects, $includeForeignObjects);
        $array['PinnedCalendars'] = array_map('intval', Base\CalendarEventQuery::create()
            ->filterByEventId($this->getId())
            ->select(Map\CalendarEventTableMap::COL_CALENDAR_ID)
            ->find()->toArray());

        return $array;
    }

    public function isEditable(): bool
    {
        return $this->editable;
    }

    public function setEditable(bool $editable): void
    {
        $this->editable = $editable;
    }

    public function checkInPerson(int $PersonId, ?int $CheckedInById = null): array
    {
        $AttendanceRecord = EventAttendQuery::create()
            ->filterByEvent($this)
            ->filterByPersonId($PersonId)
            ->findOneOrCreate();

        $AttendanceRecord->setEvent($this)
        ->setPersonId($PersonId)
        ->setCheckinDate(date('Y-m-d H:i:s'))
        ->setCheckoutDate(null);

        if ($CheckedInById !== null) {
            $AttendanceRecord->setCheckinId($CheckedInById);
        }

        $AttendanceRecord->save();

        $this->addTimelineNote(
            $PersonId,
            sprintf(gettext('Checked in to event: %s'), $this->getTitle()),
            $CheckedInById
        );

        return ['status' => 'success'];
    }

    public function checkOutPerson(int $PersonId, ?int $CheckedOutById = null): array
    {
        $AttendanceRecord = EventAttendQuery::create()
            ->filterByEvent($this)
            ->filterByPersonId($PersonId)
            ->filterByCheckinDate(null, Criteria::NOT_EQUAL)
            ->findOne();

        if ($AttendanceRecord === null) {
            return ['status' => 'not_checked_in'];
        }

        $AttendanceRecord->setEvent($this)
        ->setPersonId($PersonId)
        ->setCheckoutDate(date('Y-m-d H:i:s'));

        if ($CheckedOutById !== null) {
            $AttendanceRecord->setCheckoutId($CheckedOutById);
        }

        $AttendanceRecord->save();

        $this->addTimelineNote(
            $PersonId,
            sprintf(gettext('Checked out from event: %s'), $this->getTitle()),
            $CheckedOutById
        );

        return ['status' => 'success'];
    }

    /**
     * Add a timeline note for event check-in/out on a person's timeline.
     *
     * Kiosk device routes call Event::checkInPerson()/checkOutPerson() with no
     * authenticated user (the kiosk has its own cookie, not a User session).
     * Falling through to AuthenticationManager::getCurrentUser() in that case
     * throws and breaks the kiosk flow. If no actor id is supplied AND no user
     * is logged in, fall back to the recorded person themself so the note
     * still gets created without a fatal error.
     */
    private function addTimelineNote(int $personId, string $text, ?int $actionById, string $type = 'event'): void
    {
        if ($actionById === null) {
            if (AuthenticationManager::isUserAuthenticated()) {
                $actionById = AuthenticationManager::getCurrentUser()->getId();
            } else {
                // Kiosk device path — no User session. Attribute the note to
                // the person themself so the timeline still records it.
                $actionById = $personId;
            }
        }

        $note = new Note();
        $note->setPerId($personId);
        $note->setFamId(0);
        $note->setText($text);
        $note->setType($type);
        $note->setPrivate(0);
        $note->setEntered($actionById);
        $note->save();
    }

    public function getViewURI(): string
    {
        return SystemURLs::getRootPath() . '/event/view/' . $this->getID();
    }
}
