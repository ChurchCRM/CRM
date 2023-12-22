<?php

namespace ChurchCRM\model\ChurchCRM;

use ChurchCRM\dto\KioskAssignmentTypes;
use ChurchCRM\model\ChurchCRM\Base\KioskDevice as BaseKioskDevice;
use ChurchCRM\Utils\MiscUtils;
use Propel\Runtime\Connection\ConnectionInterface;

class KioskDevice extends BaseKioskDevice
{
    public function getActiveAssignment()
    {
        return $this->getKioskAssignments()[0];
    }

    public function setAssignment($assignmentType, $eventId): void
    {
        $assignment = $this->getActiveAssignment();
        if ($assignment === null) {
            $assignment = new KioskAssignment();
            $assignment->setKioskDevice($this);
        }
        $assignment->setAssignmentType($assignmentType);
        $assignment->setEventId($eventId);
        $assignment->save();
    }

    public function heartbeat(): array
    {
        $this->setLastHeartbeat(date('Y-m-d H:i:s'))
        ->save();

        $assignmentJSON = null;
        $assignment = $this->getActiveAssignment();

        if (isset($assignment) && $assignment->getAssignmentType() == KioskAssignmentTypes::EVENTATTENDANCEKIOSK) {
            $assignment->getEvent();
            $assignmentJSON = $assignment->toJSON();
        }

        return [
            'Accepted'   => $this->getAccepted(),
            'Name'       => $this->getName(),
            'Assignment' => $assignmentJSON,
            'Commands'   => $this->getPendingCommands(),
        ];
    }

    public function getPendingCommands()
    {
        $commands = parent::getPendingCommands();
        $this->setPendingCommands(null);
        $this->save();

        return $commands;
    }

    public function reloadKiosk(): bool
    {
        $this->setPendingCommands('Reload');
        $this->save();

        return true;
    }

    public function identifyKiosk(): bool
    {
        $this->setPendingCommands('Identify');
        $this->save();

        return true;
    }

    public function preInsert(ConnectionInterface $con = null): bool
    {
        if (empty($this->getName())) {
            $this->setName(MiscUtils::randomWord());
        }

        return true;
    }
}
