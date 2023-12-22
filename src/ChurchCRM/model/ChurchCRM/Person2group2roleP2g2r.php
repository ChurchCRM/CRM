<?php

namespace ChurchCRM\model\ChurchCRM;

use ChurchCRM\model\ChurchCRM\Base\Person2group2roleP2g2r as BasePerson2group2roleP2g2r;
use Propel\Runtime\Connection\ConnectionInterface;

/**
 * Skeleton subclass for representing a row from the 'person2group2role_p2g2r' table.
 *
 *
 *
 * You should add additional methods to this class to meet the
 * application requirements.  This class will only be generated as
 * long as it does not already exist in the output directory.
 */
class Person2group2roleP2g2r extends BasePerson2group2roleP2g2r
{
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
        parent::preInsert($con);

        return true;
    }
}
