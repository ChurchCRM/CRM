<?php

namespace ChurchCRM;

use ChurchCRM\Base\Person2group2roleP2g2r as BasePerson2group2roleP2g2r;

/**
 * Skeleton subclass for representing a row from the 'person2group2role_p2g2r' table.
 *
 *
 *
 * You should add additional methods to this class to meet the
 * application requirements.  This class will only be generated as
 * long as it does not already exist in the output directory.
 *
 */
class Person2group2roleP2g2r extends BasePerson2group2roleP2g2r
{
  public function preSave(\Propel\Runtime\Connection\ConnectionInterface $con = null)
  {
    requireUserGroupMembership("bManageGroups");
    parent::preSave($con);
    return true;
  }
  
  public function preUpdate(\Propel\Runtime\Connection\ConnectionInterface $con = null)
  {
    requireUserGroupMembership("bManageGroups");
    parent::preUpdate($con);
    return true;
  }
  public function preDelete(\Propel\Runtime\Connection\ConnectionInterface $con = null)
  {
    requireUserGroupMembership("bManageGroups");
    parent::preDelete($con);
    return true;
  }
  public function preInsert(\Propel\Runtime\Connection\ConnectionInterface $con = null)
  {
    requireUserGroupMembership("bManageGroups");
    parent::preInsert($con);
    return true;
  }
}
