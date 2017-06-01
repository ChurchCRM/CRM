<?php

namespace ChurchCRM;

use ChurchCRM\dto\KioskAssignmentTypes;
use ChurchCRM\PersonQuery;
use ChurchCRM\EventQuery;
use ChurchCRM\Person2group2roleP2g2r;
use ChurchCRM\Base\KioskAssignment as BaseKioskAssignment;
use ChurchCRM\Map\ListOptionTableMap;

/**
 * Skeleton subclass for representing a row from the 'kioskassginment_kasm' table.
 *
 *
 *
 * You should add additional methods to this class to meet the
 * application requirements.  This class will only be generated as
 * long as it does not already exist in the output directory.
 *
 */
class KioskAssignment extends BaseKioskAssignment
{
  
  private function getActiveEvent()
  {
    if ($this->getDeviceType() == KioskAssignmentTypes::GROUPATTENDANCEKIOSK)
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
  
  public function getActiveGroupMembers()
  {
    if ($this->getAssignmentType() == KioskAssignmentTypes::EVENTATTENDANCEKIOSK)
    {
      $ssClass = PersonQuery::create()
                ->joinWithPerson2group2roleP2g2r()
                ->usePerson2group2roleP2g2rQuery()
                  ->filterByGroupId($this->getEvent()->getGroupId())
                  ->joinGroup()
                  ->innerJoin("ListOption")
                  ->addJoinCondition("ListOption", "Group.RoleListId = ListOption.Id")
                ->withColumn(ListOptionTableMap::COL_LST_OPTIONNAME,"RoleName")
                ->endUse()
                 ->leftJoin('EventAttend')
                 ->withColumn("(CASE WHEN event_attend.event_id is not null AND event_attend.checkout_date IS NULL then 1 else 0 end)","status")
                ->select(array("Id","FirstName","LastName","status"))
                ->find();
      return $ssClass;
    }
    else
    {
      throw new \Exception("This kiosk does not support group attendance");
    }
  }

}
