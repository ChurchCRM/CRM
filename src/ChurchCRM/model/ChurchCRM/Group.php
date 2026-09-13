<?php

namespace ChurchCRM\model\ChurchCRM;

use ChurchCRM\dto\SystemURLs;
use ChurchCRM\model\ChurchCRM\Base\Group as BaseGroup;
use ChurchCRM\model\ChurchCRM\Map\ListOptionTableMap;
use ChurchCRM\Service\AuthService;
use ChurchCRM\Service\VolunteerPoolWriter;
use Exception;
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

    /**
     * The `bManageGroups` gate, plus the one Volunteer v2 exception (D19).
     *
     * Order matters and is the reason V1 is unchanged: the V1 answer is asked FIRST and
     * returns immediately when it is yes, so a group with no ministry — every group in an
     * installation that has never made one — takes exactly the path it always took, with
     * no extra query and the same thrown 401 when it is refused.
     *
     * Only a caller V1 would have refused reaches the second question, and only a group
     * that IS a ministry's volunteer pool can answer it yes: its coordinator, a global
     * volunteer manager, an administrator, or a V2 service that has already authorized
     * the write itself (`VolunteerPoolWriter`).
     */
    private function assertGroupWritable(): void
    {
        if (AuthService::hasUserGroupMembership('bManageGroups')) {
            return;
        }

        if (VolunteerPoolWriter::mayWriteMinistryGroup($this->getMinistryId() === null ? null : (int) $this->getMinistryId())) {
            return;
        }

        // Unchanged V1 behaviour, including the message and the 401 code.
        AuthService::requireUserGroupMembership('bManageGroups');
    }

    public function preSave(ConnectionInterface $con = null): bool
    {
        $this->assertGroupWritable();
        parent::preSave($con);

        return true;
    }

    public function preUpdate(ConnectionInterface $con = null): bool
    {
        $this->assertGroupWritable();
        parent::preUpdate($con);

        return true;
    }

    /**
     * Deleting a ministry's volunteer pool group is NOT a coordinator's to do.
     *
     * D19: the group is created with the ministry and removed with it, so the only path
     * that may delete one is `VolunteerSetupService::deleteMinistry()`, which opens the
     * managed-write context. Everything else — the Groups module, the API, an
     * administrator — is refused here, which is what makes the 409 the `/api/groups`
     * route answers a statement about the model rather than a UI convention.
     */
    public function preDelete(ConnectionInterface $con = null): bool
    {
        if ($this->getMinistryId() !== null && !VolunteerPoolWriter::isManagedWriteOpen()) {
            throw new Exception(
                gettext('This group is a volunteer ministry\'s pool and is deleted with the ministry'),
                409
            );
        }

        $this->assertGroupWritable();
        parent::preDelete($con);

        return true;
    }

    public function preInsert(ConnectionInterface $con = null): bool
    {
        $this->assertGroupWritable();
        $defaultRole = 1;
        if ($this->isSundaySchool()) {
            $defaultRole = 2;
        }
        $newListID = ListOptionQuery::create()->addAsColumn('newListId', 'MAX(' . ListOptionTableMap::COL_LST_ID . ')')->find()->getColumnValues('newListId')[0] + 1;
        $this->setRoleListId($newListID);
        $this->setDefaultRole($defaultRole);
        parent::preInsert($con);

        return true;
    }

    public function postInsert(ConnectionInterface $con = null): void
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
        return SystemURLs::getRootPath() . '/groups/view/' . $this->getId();
    }
}
