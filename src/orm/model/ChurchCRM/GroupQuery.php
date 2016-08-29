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
    $this->joinPerson2group2roleP2g2r(null,'LEFT JOIN');
    $this->groupBy("Group.Id");
    $this->withColumn("COUNT(Person2group2roleP2g2r.P2g2rPerId)","memberCount");
    $this->join('ListOption');
    $this->where("list_lst.lst_ID =?", 3);
    $this->withColumn('list_lst.lst_OptionName','groupType');
    parent::preSelect($con);
  }
}
?>