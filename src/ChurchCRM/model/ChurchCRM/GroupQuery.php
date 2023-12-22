<?php

namespace ChurchCRM\model\ChurchCRM;

use ChurchCRM\model\ChurchCRM\Base\GroupQuery as BaseGroupQuery;
use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\ActiveQuery\Join;
use Propel\Runtime\Connection\ConnectionInterface;

/**
 * Skeleton subclass for performing query and update operations on the 'group_grp' table.
 *
 *
 *
 * You should add additional methods to this class to meet the
 * application requirements.  This class will only be generated as
 * long as it does not already exist in the output directory.
 */
class GroupQuery extends BaseGroupQuery
{
    public function preSelect(ConnectionInterface $con): void
    {
        $this->leftJoinPerson2group2roleP2g2r();
        $this->withColumn('COUNT(person2group2role_p2g2r.PersonId)', 'memberCount');
        $this->groupBy('Group.Id');
        $groupTypeJoin = new Join();
        $groupTypeJoin->addCondition('Group.Type', 'list_lst.lst_OptionId', self::EQUAL);
        $groupTypeJoin->addForeignValueCondition('list_lst', 'lst_ID', '', 3, self::EQUAL);
        $groupTypeJoin->setJoinType(Criteria::LEFT_JOIN);
        $this->addJoinObject($groupTypeJoin);
        $this->withColumn('list_lst.lst_OptionName', 'groupType');
        parent::preSelect($con);
    }
}
