<?php

namespace ChurchCRM\model\ChurchCRM;

use ChurchCRM\dto\KioskAssignmentTypes;
use ChurchCRM\model\ChurchCRM\Base\KioskDevice as BaseKioskDevice;
use ChurchCRM\Utils\MiscUtils;
use Propel\Runtime\Connection\ConnectionInterface;

class KioskDevice extends BaseKioskDevice
{
    public function getActiveAssignment(): ?KioskAssignment
    {
        $assignments = $this->getKioskAssignments();

        return $assignments->count() > 0 ? $assignments->getFirst() : null;
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
            $event = $assignment->getEvent();
            $assignmentArray = json_decode($assignment->toJSON(), true) ?: [];

            // Match the calendar page tz convention: emit times as ISO 8601
            // with the church's configured sTimeZone offset so JS moment parses
            // them as instants-in-time and the kiosk's open/end logic works
            // the same regardless of the device's browser timezone. Default
            // Propel serialization gives "Y-m-d H:i:s.u" (naive, no tz) which
            // moment misreads as device-local — wrong for any kiosk whose
            // browser tz isn't sTimeZone.
            //
            // CheckInOpensAt = event start minus 1 hour, so volunteers can
            // start checking people in before the event actually begins.
            if ($event !== null) {
                $startDt = $event->getStart();
                $endDt = $event->getEnd();
                if ($startDt instanceof \DateTimeInterface) {
                    $assignmentArray['Event']['Start'] = $startDt->format('c');
                    $opensAt = (clone $startDt)->modify('-1 hour');
                    $assignmentArray['Event']['CheckInOpensAt'] = $opensAt->format('c');
                }
                if ($endDt instanceof \DateTimeInterface) {
                    $assignmentArray['Event']['End'] = $endDt->format('c');
                }
            }

            $assignmentJSON = json_encode($assignmentArray);
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
