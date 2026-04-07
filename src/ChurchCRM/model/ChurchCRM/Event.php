<?php

namespace ChurchCRM\model\ChurchCRM;

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

    public function checkInPerson($PersonId, $CheckedInById = null): array
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

        return ['status' => 'success'];
    }

    public function checkOutPerson($PersonId, $CheckedOutById = null): array
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

        return ['status' => 'success'];
    }

    public function getViewURI(): string
    {
        return SystemURLs::getRootPath() . '/EventEditor.php?calendarAction=' . $this->getID();
    }
}
