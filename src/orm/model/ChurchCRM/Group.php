<?php

namespace ChurchCRM;

use ChurchCRM\Base\Group as BaseGroup;

/**
 * Skeleton subclass for representing a row from the 'group_grp' table.
 *
 *
 *
 * You should add additional methods to this class to meet the
 * application requirements.  This class will only be generated as
 * long as it does not already exist in the output directory.
 *
 */
class Group extends BaseGroup
{
  public function preSave(\Propel\Runtime\Connection\ConnectionInterface $con = null)
  {
    requireUserGroupMembership("bManageGroups");
    parent::preSave($con);
  }
  
  public function preUpdate(\Propel\Runtime\Connection\ConnectionInterface $con = null)
  {
    requireUserGroupMembership("bManageGroups");
    parent::preUpdate($con);
  }
  public function preDelete(\Propel\Runtime\Connection\ConnectionInterface $con = null)
  {
    requireUserGroupMembership("bManageGroups");
    parent::preDelete($con);
  }
  public function preInsert(\Propel\Runtime\Connection\ConnectionInterface $con = null)
  {
    requireUserGroupMembership("bManageGroups");
    parent::preInsert($con);
  }
}
