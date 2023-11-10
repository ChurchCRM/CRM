<?php

namespace ChurchCRM\model\ChurchCRM;

use ChurchCRM\dto\KioskAssignmentTypes;
use ChurchCRM\model\ChurchCRM\Base\KioskAssignment as BaseKioskAssignment;
use ChurchCRM\model\ChurchCRM\Map\ListOptionTableMap;
use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\ActiveQuery\Join;

/**
 * Skeleton subclass for representing a row from the 'kioskassginment_kasm' table.
 *
 *
 *
 * You should add additional methods to this class to meet the
 * application requirements.  This class will only be generated as
 * long as it does not already exist in the output directory.
 */
class KioskAssignment extends BaseKioskAssignment
{
    private function getActiveEvent()
    {
        if ($this->getAssignmentType() == KioskAssignmentTypes::EVENTATTENDANCEKIOSK) {
            $Event = EventQuery::create()
            ->filterByStart('now', Criteria::LESS_EQUAL)
            ->filterByEnd('now', Criteria::GREATER_EQUAL)
            ->filterById($this->getEventId())
            ->findOne();

            return $Event;
        } else {
            throw new \Exception('This kiosk does not support group attendance');
        }
    }

    public function getActiveGroupMembers()
    {
        if ($this->getAssignmentType() == KioskAssignmentTypes::EVENTATTENDANCEKIOSK) {
            $groupTypeJoin = new Join();
            $groupTypeJoin->addCondition('Person2group2roleP2g2r.RoleId', 'list_lst.lst_OptionId', Join::EQUAL);
            $groupTypeJoin->addForeignValueCondition('list_lst', 'lst_ID', '', $this->getActiveEvent()->getGroup()->getRoleListId(), Join::EQUAL);
            $groupTypeJoin->setJoinType(Criteria::LEFT_JOIN);

            $ssClass = PersonQuery::create()
                ->joinWithPerson2group2roleP2g2r()
                ->usePerson2group2roleP2g2rQuery()
                  ->filterByGroupId($this->getEvent()->getGroupId())
                  ->joinGroup()
                  ->addJoinObject($groupTypeJoin)
                ->withColumn(ListOptionTableMap::COL_LST_OPTIONNAME, 'RoleName')
                ->endUse()
                 ->leftJoin('EventAttend')
                 ->withColumn('(CASE WHEN event_attend.event_id is not null AND event_attend.checkout_date IS NULL then 1 else 0 end)', 'status')
                ->select(['Id', 'FirstName', 'LastName', 'status'])
                ->find();

            return $ssClass;
        } else {
            throw new \Exception('This kiosk does not support group attendance');
        }
    }
}
