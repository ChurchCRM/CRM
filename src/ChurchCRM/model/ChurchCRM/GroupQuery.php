<?php

namespace ChurchCRM\model\ChurchCRM;

use ChurchCRM\model\ChurchCRM\Base\GroupQuery as BaseGroupQuery;
use ChurchCRM\model\ChurchCRM\Map\ListOptionTableMap;
use ChurchCRM\model\ChurchCRM\Map\Person2group2roleP2g2rTableMap;
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
        $this->withColumn('COUNT(' . Person2group2roleP2g2rTableMap::COL_P2G2R_PER_ID . ')', 'memberCount');
        $this->groupBy('Group.Id');
        $groupTypeJoin = new Join();
        $groupTypeJoin->addCondition('Group.Type', 'list_lst.lst_OptionId', self::EQUAL);
        $groupTypeJoin->addForeignValueCondition('list_lst', 'lst_ID', '', 3, self::EQUAL);
        $groupTypeJoin->setJoinType(Criteria::LEFT_JOIN);
        $this->addJoinObject($groupTypeJoin);
        $this->withColumn(ListOptionTableMap::COL_LST_OPTIONNAME, 'groupType');
        parent::preSelect($con);
    }
}
