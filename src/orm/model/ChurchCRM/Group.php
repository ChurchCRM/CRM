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
  public function postInsert(\Propel\Runtime\Connection\ConnectionInterface $con = null)
  {
    $optionList = array("Member");
    if ($this->getType() == 4) {
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
