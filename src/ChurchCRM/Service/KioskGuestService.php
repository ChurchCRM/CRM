<?php

namespace ChurchCRM\Service;

use ChurchCRM\model\ChurchCRM\Event;
use ChurchCRM\model\ChurchCRM\Map\PersonTableMap;
use ChurchCRM\model\ChurchCRM\Person;
use Propel\Runtime\Propel;

class KioskGuestService
{
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
            $event->checkInPerson($guest->getId(), null, $con);
            $con->commit();
        } catch (\Throwable $e) {
            $con->rollBack();
            throw $e;
        }
    }
}
