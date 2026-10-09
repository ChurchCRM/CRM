<?php

namespace ChurchCRM\Service;

use ChurchCRM\model\ChurchCRM\Event;
use ChurchCRM\model\ChurchCRM\Map\PersonTableMap;
use ChurchCRM\model\ChurchCRM\Note;
use ChurchCRM\model\ChurchCRM\Person;
use Propel\Runtime\Propel;

class KioskGuestService
{
    /**
     * Why this event cannot take walk-in guests yet, or null when it can: it needs a linked
     * group (the class) and must be inside the kiosk's check-in window, which opens an hour
     * before the event starts (KioskDevice::getDeviceInfo) and closes when it ends.
     */
    public static function notReadyReason(Event $event): ?string
    {
        if ($event->getGroups()->count() === 0) {
            return gettext('This event has no group assigned, so guests cannot be registered');
        }
        $start = $event->getStart();
        $end = $event->getEnd();
        if (!$start instanceof \DateTimeInterface || !$end instanceof \DateTimeInterface) {
            return gettext('This event has no start and end time');
        }
        $now = new \DateTimeImmutable();
        if ($now < \DateTimeImmutable::createFromInterface($start)->modify('-1 hour')) {
            return gettext('Check-in has not opened for this event yet');
        }
        if ($now > $end) {
            return gettext('This event has ended');
        }

        return null;
    }

    /**
     * Save a walk-in guest and check them in to the event in one transaction,
     * so a failed check-in never leaves an orphan Person behind.
     *
     * @throws \Throwable the original failure, after the transaction is rolled back
     */
    public function registerAndCheckIn(Person $guest, Event $event): void
    {
        $con = Propel::getWriteConnection(PersonTableMap::DATABASE_NAME);
        $con->beginTransaction();
        try {
            $guest->save($con);
            $note = new Note();
            $note->setPerId($guest->getId());
            $note->setFamId(0);
            $note->setType('event');
            $note->setPrivate(0);
            $note->setEntered(Person::SELF_REGISTER);
            $note->setText(sprintf(gettext('Registered as a walk-in guest at the kiosk during event: %s'), $event->getTitle()));
            $note->save($con);
            $event->checkInPerson($guest->getId(), null, $con);
            $con->commit();
        } catch (\Throwable $e) {
            $con->rollBack();
            throw $e;
        }
    }
}
