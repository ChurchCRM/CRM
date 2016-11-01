<?php

namespace ChurchCRM;

use ChurchCRM\Base\GroupQuery as BaseGroupQuery;

/**
 * Skeleton subclass for performing query and update operations on the 'group_grp' table.
 *
 *
 *
 * You should add additional methods to this class to meet the
 * application requirements.  This class will only be generated as
 * long as it does not already exist in the output directory.
 *
 */
class GroupQuery extends BaseGroupQuery
{
  function preSelect(\Propel\Runtime\Connection\ConnectionInterface $con)
  {
    $this->leftJoinPerson2group2roleP2g2r();
    $this->withColumn("COUNT(person2group2role_p2g2r.PersonId)","memberCount");
    $this->groupBy("Group.Id");
    $this->leftJoinListOption();
    $this->addJoinCondition("ListOption","ListOption.Id = ?","3");
    $this->withColumn('list_lst.lst_OptionName','groupType');
    parent::preSelect($con);
  }
}
?>