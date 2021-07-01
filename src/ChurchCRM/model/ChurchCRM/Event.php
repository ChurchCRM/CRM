<?php

namespace ChurchCRM;

use ChurchCRM\Base\Event as BaseEvent;
use ChurchCRM\dto\SystemURLs;
use Propel\Runtime\ActiveQuery\Criteria;

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

  private $editable;

  public function __construct() {
    parent::__construct();
    $this->editable = true;
  }

  public function toArray($keyType = TableMap::TYPE_PHPNAME, $includeLazyLoadColumns = true, $alreadyDumpedObjects = array(), $includeForeignObjects = false)
  {

    $array = parent::toArray($keyType, $includeLazyLoadColumns, $alreadyDumpedObjects, $includeForeignObjects);
    $array['PinnedCalendars'] = array_map('intval',Base\CalendarEventQuery::create()
            ->filterByEventId($this->getId())
            ->select(Map\CalendarEventTableMap::COL_CALENDAR_ID)
            ->find()->toArray());
    return $array;

  }

  public function isEditable()
  {
    return $this->editable;
  }

  public function setEditable($editable) {
    $this->editable = $editable;
  }

  public function checkInPerson($PersonId)
  {
    $AttendanceRecord = EventAttendQuery::create()
            ->filterByEvent($this)
            ->filterByPersonId($PersonId)
            ->findOneOrCreate();

    $AttendanceRecord->setEvent($this)
      ->setPersonId($PersonId)
      ->setCheckinDate(date('Y-m-d H:i:s'))
      ->setCheckoutDate(null)
      ->save();

    return array("status"=>"success");

  }

  public function checkOutPerson($PersonId)
  {
    $AttendanceRecord = EventAttendQuery::create()
            ->filterByEvent($this)
            ->filterByPersonId($PersonId)
            ->filterByCheckinDate(NULL,  Criteria::NOT_EQUAL)
            ->findOne();

    $AttendanceRecord->setEvent($this)
      ->setPersonId($PersonId)
      ->setCheckoutDate(date('Y-m-d H:i:s'))
      ->save();

    return array("status"=>"success");

  }

  public function getViewURI()
  {
    return SystemURLs::getRootPath()."/EventEditor.php?calendarAction=".$this->getID();
  }
}
