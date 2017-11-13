<?php

namespace ChurchCRM;

use ChurchCRM\Base\Event as BaseEvent;
use Propel\Runtime\ActiveQuery\Criteria;
use ChurchCRM\dto\SystemURLs;

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
  
  public function getEventURI()
  {
  	return SystemURLs::getRootPath()."EventEditor.php?calendarAction=".$this->getID();
  }
}
