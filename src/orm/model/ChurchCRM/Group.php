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

  public function isSundaySchool() {
    return $this->getType() == $this->typeSundaySchool;
  }

  public function preInsert(\Propel\Runtime\Connection\ConnectionInterface $con = null)
  {
    requireUserGroupMembership("bManageGroups");
    $defaultRole = 1;
    if ($this->isSundaySchool()) {
      $defaultRole = 2;
    }
    $newListID = ListOptionQuery::create()->withColumn("MAX(ListOption.Id)","newListId")->find()->getColumnValues('newListId')[0] + 1;
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
}
