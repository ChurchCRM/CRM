<?php

namespace ChurchCRM\model\ChurchCRM;

use ChurchCRM\dto\SystemURLs;
use ChurchCRM\model\ChurchCRM\Base\Group as BaseGroup;
use Propel\Runtime\Connection\ConnectionInterface;

/**
 * Skeleton subclass for representing a row from the 'group_grp' table.
 *
 *
 *
 * You should add additional methods to this class to meet the
 * application requirements.  This class will only be generated as
 * long as it does not already exist in the output directory.
 */
class Group extends BaseGroup
{
    protected $typeSundaySchool = 4;

    public function isSundaySchool(): bool
    {
        return $this->getType() == $this->typeSundaySchool;
    }

    public function makeSundaySchool(): void
    {
        $this->setType($this->typeSundaySchool);
    }

    public function preSave(ConnectionInterface $con = null): bool
    {
        requireUserGroupMembership('bManageGroups');
        parent::preSave($con);

        return true;
    }

    public function preUpdate(ConnectionInterface $con = null): bool
    {
        requireUserGroupMembership('bManageGroups');
        parent::preUpdate($con);

        return true;
    }

    public function preDelete(ConnectionInterface $con = null): bool
    {
        requireUserGroupMembership('bManageGroups');
        parent::preDelete($con);

        return true;
    }

    public function preInsert(ConnectionInterface $con = null): bool
    {
        requireUserGroupMembership('bManageGroups');
        $defaultRole = 1;
        if ($this->isSundaySchool()) {
            $defaultRole = 2;
        }
        $newListID = ListOptionQuery::create()->withColumn('MAX(ListOption.Id)', 'newListId')->find()->getColumnValues('newListId')[0] + 1;
        $this->setRoleListId($newListID);
        $this->setDefaultRole($defaultRole);
        parent::preInsert($con);

        return true;
    }

    public function postInsert(ConnectionInterface $con = null): bool
    {
        $optionList = ['Member'];
        if ($this->isSundaySchool()) {
            $optionList = ['Teacher', 'Student'];
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

    public function checkAgainstCart(): bool
    {
        $groupMemberships = $this->getPerson2group2roleP2g2rsJoinPerson();
        $bNoneInCart = true;
        $bAllInCart = true;
        //Loop through the recordset
        foreach ($groupMemberships as $groupMembership) {
            if (!isset($_SESSION['aPeopleCart'])) {
                $bAllInCart = false;
            } elseif (!in_array($groupMembership->getPersonId(), $_SESSION['aPeopleCart'], false)) {
                // Cart does not exist.  This person is not in cart.
                $bAllInCart = false;
            } elseif (in_array($groupMembership->getPersonId(), $_SESSION['aPeopleCart'], false)) {
                // This person is not in cart.
                $bNoneInCart = false;
            } // This person is in the cart
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

    public function getViewURI(): string
    {
        return SystemURLs::getRootPath() . '/GroupView.php?GroupID=' . $this->getId();
    }
}
