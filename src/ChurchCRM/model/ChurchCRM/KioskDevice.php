<?php

namespace ChurchCRM;

use ChurchCRM\Base\KioskDevice as BaseKioskDevice;

use ChurchCRM\dto\KioskDeviceTypes;
use ChurchCRM\EventQuery;
use ChurchCRM\Event;

use ChurchCRM\EventAttendQuery;
use Propel\Runtime\ActiveQuery\Criteria;
use ChurchCRM\ConfigQuery;
use ChurchCRM\Family;
use ChurchCRM\ListOptionQuery;
use ChurchCRM\GroupQuery;
use ChurchCRM\Person2group2roleP2g2rQuery;
use ChurchCRM\Person;
use ChurchCRM\Map\ListOptionTableMap;

class KioskDevice extends BaseKioskDevice
{

  private function getActiveEvent()
  {
    if ($this->getDeviceType() == KioskDeviceTypes::GROUPATTENDANCEKIOSK)
    {
      $Event = EventQuery::create()
        ->filterByStart('now', Criteria::LESS_EQUAL)
        ->filterByEnd('now',Criteria::GREATER_EQUAL)
        ->filterByKioskId($this->getId())
        ->findOne();
      return $Event;
    }
    else
    {
      throw new \Exception("This kiosk does not support group attendance");
    }
  }
  
  public function heartbeat()
  {
    $this->setLastHeartbeat(date('Y-m-d H:i:s'))
      ->save();
    $event = $this->getActiveEvent();
    if ($event)
    {
      $event2 = $event->toJSON();
    }
    
    return array(
        "Status"=>"Good",
        "Event"=>$event2
      );
  }
  
  public function getActiveGroupMembers()
  {
    if ($this->getDeviceType() == KioskDeviceTypes::GROUPATTENDANCEKIOSK)
      {
        $ssClass = PersonQuery::create()
                  ->joinWithPerson2group2roleP2g2r()
                  ->usePerson2group2roleP2g2rQuery()
                    ->filterByGroupId($this->getActiveEvent()->getGroupId())
                    ->joinGroup()
                    ->innerJoin("ListOption")
                    ->addJoinCondition("ListOption", "Group.RoleListId = ListOption.Id")
                  ->withColumn(ListOptionTableMap::COL_LST_OPTIONNAME,"RoleName")
                  ->endUse()
                   ->leftJoin('EventAttend')
                   ->withColumn("(CASE WHEN event_attend.checkout_date IS NULL then 1 else 0 end)","status")
                  ->select(array("Id","FirstName","LastName","status"))
                  ->find();
        return $ssClass;
      }
      else
      {
        throw new \Exception("This kiosk does not support group attendance");
      }
  }
  
  public function checkInPerson($PersonId)
  {
    $Event = $this->getActiveEvent();
    
    $AttendanceRecord = EventAttendQuery::create()
            ->filterByEvent($Event)
            ->filterByPersonId($PersonId)
            ->findOneOrCreate();
    
    $AttendanceRecord->setEvent($Event)
      ->setPersonId($PersonId)
      ->setCheckinDate(date('Y-m-d H:i:s'))
      ->setCheckoutDate(null)
      ->save();
    
    return array("status"=>"success");
    
  }
  
  public function checkOutPerson($PersonId)
  {
    $Event = $this->getActiveEvent();
    
    $AttendanceRecord = EventAttendQuery::create()
            ->filterByEvent($Event)
            ->filterByPersonId($PersonId)
            ->filterByCheckinDate(NULL,  Criteria::NOT_EQUAL)
            ->findOne();
    
    $AttendanceRecord->setEvent($Event)
      ->setPersonId($PersonId)
      ->setCheckoutDate(date('Y-m-d H:i:s'))
      ->save();
    
    return array("status"=>"success");
    
  }

}
