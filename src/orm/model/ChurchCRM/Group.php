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

  protected $typeSundaySchool = 4;

  public function isSundaySchool()
  {
    return $this->getType() == $this->typeSundaySchool;
  }

  public function makeSundaySchool() {
    $this->setType($this->typeSundaySchool);
  }

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
    $defaultRole = 1;
    if ($this->isSundaySchool()) {
      $defaultRole = 2;
    }
    $newListID = ListOptionQuery::create()->withColumn("MAX(ListOption.Id)", "newListId")->find()->getColumnValues('newListId')[0] + 1;
    $this->setRoleListId($newListID);
    $this->setDefaultRole($defaultRole);
    parent::preInsert($con);
    return true;
  }

  public function postInsert(\Propel\Runtime\Connection\ConnectionInterface $con = null)
  {
    $optionList = array("Member");
    if ($this->isSundaySchool()) {
      $optionList = array("Teacher", "Student");
    }

    $i = 1;
    foreach ($optionList as $option) {
      $listOption = new ListOption();
      $listOption->setId($this->getRoleListId());
      $listOption->setOptionId($i);
      $listOption->setOptionSequence($i);
      $listOption->setOptionName($option);
      $listOption->save();
      $i++;
    }

    parent::postInsert($con);
    return true;
  }


  public function checkAgainstCart()
  {
    $groupMemberships = $this->getPerson2group2roleP2g2rsJoinPerson();
    $bNoneInCart = TRUE;
    $bAllInCart = TRUE;
    //Loop through the recordset
    foreach ($groupMemberships as $groupMembership) {
      if (!isset ($_SESSION['aPeopleCart']))
        $bAllInCart = FALSE; // Cart does not exist.  This person is not in cart.
      elseif (!in_array($groupMembership->getPersonId(), $_SESSION['aPeopleCart'], false))
        $bAllInCart = FALSE; // This person is not in cart.
      elseif (in_array($groupMembership->getPersonId(), $_SESSION['aPeopleCart'], false))
        $bNoneInCart = FALSE; // This person is in the cart
    }

    if (!$bAllInCart) {
      //there is at least one person in this group who is not in the cart.  Return false
      return false;
    }
    if (!$bNoneInCart) {
      //every member of this group is in the cart.  Return true
      return true;
    }
    return false;
  }
}
