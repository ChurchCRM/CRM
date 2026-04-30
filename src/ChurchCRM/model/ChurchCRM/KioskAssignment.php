<?php

namespace ChurchCRM\model\ChurchCRM;

use ChurchCRM\dto\KioskAssignmentTypes;
use ChurchCRM\model\ChurchCRM\Base\KioskAssignment as BaseKioskAssignment;
use ChurchCRM\model\ChurchCRM\Map\ListOptionTableMap;
use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\ActiveQuery\Join;
use Propel\Runtime\Collection\ArrayCollection;

/**
 * Skeleton subclass for representing a row from the 'kioskassginment_kasm' table.
 *
 * You should add additional methods to this class to meet the
 * application requirements.  This class will only be generated as
 * long as it does not already exist in the output directory.
 */
class KioskAssignment extends BaseKioskAssignment
{
    private function getActiveEvent(): ?Event
    {
        if ($this->getAssignmentType() == KioskAssignmentTypes::EVENTATTENDANCEKIOSK) {
            return EventQuery::create()
            ->filterByStart('now', Criteria::LESS_EQUAL)
            ->filterByEnd('now', Criteria::GREATER_EQUAL)
            ->filterById($this->getEventId())
            ->findOne();
        } else {
            throw new \Exception('This kiosk does not support group attendance');
        }
    }

    public function getActiveGroupMembers()
    {
        if ($this->getAssignmentType() == KioskAssignmentTypes::EVENTATTENDANCEKIOSK) {
            // Get the event's linked groups
            $event = $this->getEvent();
            $groups = $event->getGroups();
            
            if ($groups->count() === 0) {
                // No groups linked to this event - return empty collection
                return new ArrayCollection();
            }
            
            // Get the first linked group for role list
            $firstGroup = $groups->getFirst();
            
            $groupTypeJoin = new Join();
            $groupTypeJoin->addCondition('Person2group2roleP2g2r.RoleId', 'list_lst.lst_OptionId', Join::EQUAL);
            $groupTypeJoin->addForeignValueCondition('list_lst', 'lst_ID', '', $firstGroup->getRoleListId(), Join::EQUAL);
            $groupTypeJoin->setJoinType(Criteria::LEFT_JOIN);

            // Use leftJoinEventAttend() with addJoinCondition to filter by the current event only.
            // Without this filter the unscoped LEFT JOIN on EventAttend returns one row per
            // historical attendance record for each person, causing Propel to create duplicate
            // Person objects with non-deterministic `status` values – manifesting as the
            // check-in counter flipping back to 0 after every heartbeat.
            return PersonQuery::create()
                ->joinWithPerson2group2roleP2g2r()
                ->usePerson2group2roleP2g2rQuery()
                    ->filterByGroup($groups)
                    ->joinGroup()
                    ->addJoinObject($groupTypeJoin)
                ->withColumn(ListOptionTableMap::COL_LST_OPTIONNAME, 'RoleName')
                ->endUse()
                    ->leftJoinEventAttend()
                    ->addJoinCondition('EventAttend', 'event_attend.event_id = ?', $event->getId())
                    ->withColumn('(CASE WHEN event_attend.event_id is not null AND event_attend.checkout_date IS NULL then 1 else 0 end)', 'status')
                ->find();
        } else {
            throw new \Exception('This kiosk does not support group attendance');
        }
    }

    /**
     * Return Person objects that are currently checked in to this event but are
     * NOT members of the event's linked group(s). These are walk-in guests
     * registered directly on the kiosk.
     *
     * @return Person[]
     */
    public function getEventGuests(): array
    {
        if ($this->getAssignmentType() != KioskAssignmentTypes::EVENTATTENDANCEKIOSK) {
            return [];
        }

        $event = $this->getEvent();
        if ($event === null) {
            return [];
        }

        $groups = $event->getGroups();

        // Collect all current group-member person IDs so we can exclude them
        $groupMemberIds = [];
        if ($groups->count() > 0) {
            foreach ($groups as $group) {
                $memberIds = Person2group2roleP2g2rQuery::create()
                    ->filterByGroupId($group->getId())
                    ->select(['PersonId'])
                    ->find()
                    ->toArray();
                $groupMemberIds = array_merge($groupMemberIds, array_map('intval', $memberIds));
            }
            $groupMemberIds = array_unique($groupMemberIds);
        }

        // Find attendees currently checked in (no checkout) who are not group members.
        // Use joinWithPerson() to eager-load Person objects in one query (avoids N+1).
        $query = EventAttendQuery::create()
            ->filterByEventId($event->getId())
            ->filterByCheckoutDate(null)
            ->filterByCheckinDate(null, Criteria::NOT_EQUAL)
            ->joinWithPerson();

        if (!empty($groupMemberIds)) {
            $query->filterByPersonId($groupMemberIds, Criteria::NOT_IN);
        }

        $attendees = $query->find();

        $guests = [];
        foreach ($attendees as $attend) {
            $person = $attend->getPerson();
            if ($person !== null) {
                $guests[] = $person;
            }
        }

        return $guests;
    }
}
