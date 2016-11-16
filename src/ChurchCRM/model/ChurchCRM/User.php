<?php

namespace ChurchCRM;

use ChurchCRM\Base\User as BaseUser;

/**
 * Skeleton subclass for representing a row from the 'user_usr' table.
 *
 *
 *
 * You should add additional methods to this class to meet the
 * application requirements.  This class will only be generated as
 * long as it does not already exist in the output directory.
 *
 */
class User extends BaseUser
{
  public function getName()
  {
    return $this->getPerson()->getFullName();
  }

  public function isAddRecordsEnabled()
  {
    return ($this->isAdmin() ? true : $this->isAddRecords());
  }

  public function isEditRecordsEnabled()
  {
    return ($this->isAdmin() ? true : $this->isEditRecords());
  }

  public function isDeleteRecordsEnabled()
  {
    return ($this->isAdmin() ? true : $this->isDeleteRecords());
  }

  public function isMenuOptionsEnabled()
  {
    return ($this->isAdmin() ? true : $this->isMenuOptions());
  }

  public function isManageGroupsEnabled()
  {
    return ($this->isAdmin() ? true : $this->isManageGroups());
  }

  public function isFinanceEnabled()
  {
    return ($this->isAdmin() ? true : $this->isFinance());
  }

  public function isNotesEnabled()
  {
    return ($this->isAdmin() ? true : $this->isNotes());
  }

  public function isEditSelfEnabled()
  {
    return ($this->isAdmin() ? true : $this->isEditSelf());
  }

  public function isCanvasserEnabled()
  {
    return ($this->isAdmin() ? true : $this->isCanvasser());
  }
}
