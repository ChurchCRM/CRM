<?php

namespace ChurchCRM\model\ChurchCRM;

use ChurchCRM\model\ChurchCRM\Base\Person2group2roleP2g2r as BasePerson2group2roleP2g2r;
use ChurchCRM\Service\AuthService;
use ChurchCRM\Volunteer\Service\VolunteerPoolWriter;
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
    /**
     * The `bManageGroups` gate, plus the one Volunteer v2 exception (D19).
     *
     * Same shape and same ordering rule as `Group::assertGroupWritable()`: the V1 answer
     * is asked first and wins immediately, so a membership row in an ordinary group takes
     * the path it always took — no ministry lookup, no extra query, the same 401.
     *
     * Only when V1 says no is the group loaded to see whether it is a ministry's volunteer
     * pool. Unlike `Group`, membership writes have NO extra rule for delete: adding and
     * removing pool members is exactly what a coordinator and the "I'd like to help"
     * action are allowed to do, and is what the Groups module keeps doing for anyone with
     * Manage Groups.
     */
    private function assertMembershipWritable(): void
    {
        if (AuthService::hasUserGroupMembership('bManageGroups')) {
            return;
        }

        $group = $this->getGroupId() === null ? null : $this->getGroup();
        $ministryId = $group === null || $group->getMinistryId() === null
            ? null
            : (int) $group->getMinistryId();

        if (VolunteerPoolWriter::mayWriteMinistryGroup($ministryId)) {
            return;
        }

        // Unchanged V1 behaviour, including the message and the 401 code.
        AuthService::requireUserGroupMembership('bManageGroups');
    }

    public function preSave(ConnectionInterface $con = null): bool
    {
        $this->assertMembershipWritable();
        parent::preSave($con);

        return true;
    }

    public function preUpdate(ConnectionInterface $con = null): bool
    {
        $this->assertMembershipWritable();
        parent::preUpdate($con);

        return true;
    }

    public function preDelete(ConnectionInterface $con = null): bool
    {
        $this->assertMembershipWritable();
        parent::preDelete($con);

        return true;
    }

    public function preInsert(ConnectionInterface $con = null): bool
    {
        $this->assertMembershipWritable();
        parent::preInsert($con);

        return true;
    }
}
