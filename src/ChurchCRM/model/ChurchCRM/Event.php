<?php

namespace ChurchCRM;

use ChurchCRM\Base\Event as BaseEvent;
use Propel\Runtime\ActiveQuery\Criteria;
use ChurchCRM\dto\SystemURLs;
use dto\SystemConfig;

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
    if($_SESSION['bAddEvent'])
      return SystemURLs::getRootPath()."EventEditor.php?calendarAction=".$this->getID();
    else 
      return '';
  }
  
  public function toVEVENT() {
    $now = new \DateTime();
        
    return "BEGIN:VEVENT\r\n".
          "UID:".$this->getId()."@".dto\ChurchMetaData::getChurchName()."\r\n".
          "DTSTAMP:".$now->setTimezone(new \DateTimeZone("UTC"))->format('Ymd\THis\Z')."\r\n".
          "DTSTART:".$this->getStart()->setTimezone(new \DateTimeZone("UTC"))->format('Ymd\THis\Z')."\r\n".
          "DTEND:".$this->getEnd()->setTimezone(new \DateTimeZone("UTC"))->format('Ymd\THis\Z')."\r\n".
          "SUMMARY:".$this->getTitle()."\r\n".
          "END:VEVENT\r\n";
  }
}
