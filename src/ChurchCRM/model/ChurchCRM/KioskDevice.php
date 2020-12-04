<?php

namespace ChurchCRM;

use ChurchCRM\Base\KioskDevice as BaseKioskDevice;
use ChurchCRM\Map\ListOptionTableMap;


class KioskDevice extends BaseKioskDevice
{

  public function getActiveAssignment()
  {
    return $this->getKioskAssignments()[0];
  }

  public function setAssignment($assignmentType,$eventId)
  {
    $assignment = $this->getActiveAssignment();
    if (is_null($assignment))
    {
      $assignment = new KioskAssignment();
      $assignment->setKioskDevice($this);
    }
    $assignment->setAssignmentType($assignmentType);
    $assignment->setEventId($eventId);
    $assignment->save();
  }

  public function heartbeat()
  {
    $this->setLastHeartbeat(date('Y-m-d H:i:s'))
      ->save();

    $assignmentJSON = null;
    $assignment = $this->getActiveAssignment();

    if (isset($assignment) && $assignment->getAssignmentType() == dto\KioskAssignmentTypes::EVENTATTENDANCEKIOSK )
    {
      $assignment->getEvent();
      $assignmentJSON = $assignment->toJSON();
    }


    return array(
        "Accepted"=>$this->getAccepted(),
        "Name"=>$this->getName(),
        "Assignment"=>$assignmentJSON,
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

  public function identifyKiosk()
  {
    $this->setPendingCommands("Identify");
    $this->save();
    return true;
  }

  public function preInsert(\Propel\Runtime\Connection\ConnectionInterface $con = null) {
    if (!isset($this->Name))
    {
      $this->setName(Utils\MiscUtils::random_word());
    }
    return true;
  }

}
