<?php

namespace ChurchCRM;

use ChurchCRM\Base\KioskDevice as BaseKioskDevice;

use ChurchCRM\dto\KioskAssignmentTypes;
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
  
  public function getActiveAssignment()
  {
    return $this->getKioskAssignments()[0];
  }
  
  public function heartbeat()
  {
    $this->setLastHeartbeat(date('Y-m-d H:i:s'))
      ->save();
    
    $assignment = $this->getActiveAssignment();
    
    if ($assignment->getAssignmentType() == dto\KioskAssignmentTypes::EVENTATTENDANCEKIOSK )
    {
      $assignment->getEvent();
    }
    
    
    return array(
        "Status"=>"Good",
        "Assignment"=>$assignment->toJSON(),
        "Commands"=>$this->getPendingCommands()
      );
  }
  
  public function getPendingCommands()
  {
    $commands = parent::getPendingCommands();
    $this->setPendingCommands(null);
    $this->save();
    return $commands;
  }
  
  
  
  
  
  public function reloadKiosk()
  {
    $this->setPendingCommands("Reload");
    $this->save();
    return true;
  }

}
