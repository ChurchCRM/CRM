<?php

namespace ChurchCRM\model\ChurchCRM;

use ChurchCRM\Authorization\RecordAccessPolicy;
use ChurchCRM\model\ChurchCRM\Base\FamilyQuery as BaseFamilyQuery;

/**
 * Skeleton subclass for performing query and update operations on the 'family_fam' table.
 *
 *
 *
 * You should add additional methods to this class to meet the
 * application requirements.  This class will only be generated as
 * long as it does not already exist in the output directory.
 */
class FamilyQuery extends BaseFamilyQuery
{
    /**
     * Hide protected households from users who may not read them.
     * Admins and members of the household still see it.
     */
    public function filterVisibleTo(User $user): self
    {
        if ($user->isAdmin()) {
            return $this;
        }

        return $this->where(
            '(family_fam.fam_Confidential = 0 OR family_fam.fam_ID = ?)',
            (new RecordAccessPolicy())->ownFamilyId($user),
            \PDO::PARAM_INT,
        );
    }
}
