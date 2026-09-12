<?php

namespace ChurchCRM\model\ChurchCRM;

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\model\ChurchCRM\Base\Event as BaseEvent;
use ChurchCRM\model\ChurchCRM\CalendarEventQuery;
use ChurchCRM\model\ChurchCRM\EventAttendQuery;
use ChurchCRM\model\ChurchCRM\EventAudienceQuery;
use ChurchCRM\model\ChurchCRM\EventCountsQuery;
use ChurchCRM\model\ChurchCRM\KioskAssignmentQuery;
use ChurchCRM\Plugin\Hook\HookManager;
use ChurchCRM\Plugin\Hooks;
use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\Connection\ConnectionInterface;
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

    /**
     * Cascade-delete child rows that reference this event before the row
     * itself is removed.
     *
     * Event child tables (all FK'd to events_event.event_id) are semantically
     * owned by the event and have no independent meaning once the event is
     * gone. Propel does not auto-cascade these FKs, so if we do not clean them
     * up explicitly they become orphaned rows that break integrity reports
     * and surface as "phantom" check-ins, audience links, etc.
     *
     * Handled here:
     *  - calendar_event         (which calendars the event appears on)
     *  - event_audience         (group audience links — cross-ref table)
     *  - eventattend_event_attend (attendance records)
     *  - kioskassignment_kasm    (kiosk → event pins)
     *  - eventcounts_evtcnt      (attendance summary — no FK at DB level)
     *
     * DELIBERATELY NOT handled here: Volunteer v2's `volunteer_occurrence_vocc` (#9713,
     * design §2.9/E12). A volunteer occurrence is NOT owned by the event — several
     * occurrences from different ministries may point at the same service (UC3), and the
     * assignments, responses and swaps hanging off one are the church's record of who
     * actually served. Deleting the calendar entry must not erase that history.
     *
     * `fk_vocc_event` is declared `ON DELETE SET NULL`, so the database nulls
     * `vocc_event_id` on its own and the occurrence survives on `vocc_OccurrenceDate`
     * (which is populated for linked rows precisely to be that anchor). There is nothing
     * for this method to do, and adding a `VolunteerOccurrenceQuery::...->delete()` line
     * to the list above would destroy exactly the data the SET NULL exists to keep.
     *
     * `events_event.event_ministry_id` points the other way — at a ministry, not at a
     * child row — and is likewise not this method's business.
     *
     * See #8670.
     */
    public function preDelete(ConnectionInterface $con = null): bool
    {
        $eventId = (int) $this->getId();

        CalendarEventQuery::create()->filterByEventId($eventId)->delete($con);
        EventAudienceQuery::create()->filterByEventId($eventId)->delete($con);
        EventAttendQuery::create()->filterByEventId($eventId)->delete($con);
        EventCountsQuery::create()->filterByEvtcntEventid($eventId)->delete($con);
        KioskAssignmentQuery::create()->filterByEventId($eventId)->delete($con);

        return parent::preDelete($con);
    }

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
        HookManager::doAction(Hooks::EVENT_CHECKIN, $AttendanceRecord, $this, $PersonId);

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
        HookManager::doAction(Hooks::EVENT_CHECKOUT, $AttendanceRecord, $this, $PersonId);

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
