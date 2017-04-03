<?php

namespace ChurchCRM;

use ChurchCRM\Base\User as BaseUser;
use ChurchCRM\dto\SystemConfig;

/**
 * Skeleton subclass for representing a row from the 'user_usr' table.
 *
 *
 *
 * You should add additional methods to this class to meet the
 * application requirements.  This class will only be generated as
 * long as it does not already exist in the output directory.
 */
class User extends BaseUser
{

    public function getId()
    {
        return $this->getPersonId();
    }

    public function getName()
    {
        return $this->getPerson()->getFullName();
    }

    public function getEmail()
    {
        return $this->getPerson()->getEmail();
    }

    public function getFullName()
    {
        return $this->getPerson()->getFullName();
    }

    public function isAddRecordsEnabled()
    {
        return $this->isAdmin() || $this->isAddRecords();
    }

    public function isEditRecordsEnabled()
    {
        return $this->isAdmin() || $this->isEditRecords();
    }

    public function isDeleteRecordsEnabled()
    {
        return $this->isAdmin() || $this->isDeleteRecords();
    }

    public function isMenuOptionsEnabled()
    {
        return $this->isAdmin() || $this->isMenuOptions();
    }

    public function isManageGroupsEnabled()
    {
        return $this->isAdmin() || $this->isManageGroups();
    }

    public function isFinanceEnabled()
    {
        return $this->isAdmin() || $this->isFinance();
    }

    public function isNotesEnabled()
    {
        return $this->isAdmin() || $this->isNotes();
    }

    public function isEditSelfEnabled()
    {
        return $this->isAdmin() || $this->isEditSelf();
    }

    public function isCanvasserEnabled()
    {
        return $this->isAdmin() || $this->isCanvasser();
    }

    public function updatePassword($password)
    {
        $this->setPassword($this->hashPassword($password));
    }

    public function isPasswordValid($password)
    {
        return $this->getPassword() == $this->hashPassword($password);
    }

    public function hashPassword($password)
    {
        return hash('sha256', $password . $this->getPersonId());
    }


    public function isLocked()
    {
        return SystemConfig::getValue('iMaxFailedLogins') > 0 && $this->getFailedLogins() >= SystemConfig::getValue('iMaxFailedLogins');
    }

    public static function randomPassword()
    {
        $alphabet = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ1234567890';
        $pass = array(); //remember to declare $pass as an array
        $alphaLength = strlen($alphabet) - 1; //put the length -1 in cache
        for ($i = 0; $i < 8; $i++) {
            $n = rand(0, $alphaLength);
            $pass[] = $alphabet[$n];
        }
        return implode($pass); //turn the array into a string
    }
}
