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
  
  public function checkAgainstCart() 
  {
    $members = $this->getPerson2group2roleP2g2rs();
    //echo "Members: ".count($members);
    $bNoneInCart = TRUE;
    $bAllInCart = TRUE;
    //Loop through the recordset
    foreach ($members as $member) {
      if (!isset ($_SESSION['aPeopleCart']))
        $bAllInCart = FALSE; // Cart does not exist.  This person is not in cart.
      elseif (!in_array($member->getP2g2rPerId(), $_SESSION['aPeopleCart'], false))
        $bAllInCart = FALSE; // This person is not in cart.
      elseif (in_array($member->getP2g2rPerId(), $_SESSION['aPeopleCart'], false))
        $bNoneInCart = FALSE; // This person is in the cart
    }

    if (!$bAllInCart) {
      //there is at least one person in this group who is not in the cart.  Return false
      return json_encode(["bAllInCart"=>"false"]);
    }
    if (!$bNoneInCart) {
      //every member of this group is in the cart.  Return true
      return json_encode(["bAllInCart"=>"true"]);;
    }
    return json_encode(["bAllInCart"=>"false"]);;
  }
}