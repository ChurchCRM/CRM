<?php

namespace ChurchCRM\model\ChurchCRM;

use ChurchCRM\Authorization\RecordAccessPolicy;
use ChurchCRM\model\ChurchCRM\Base\PersonQuery as BasePersonQuery;
use Propel\Runtime\ActiveQuery\Criteria;

/**
 * Skeleton subclass for performing query and update operations on the 'person_per' table.
 *
 *
 *
 * You should add additional methods to this class to meet the
 * application requirements.  This class will only be generated as
 * long as it does not already exist in the output directory.
 */
class PersonQuery extends BasePersonQuery
{
    /**
     * Only living persons (per_DateDeceased IS NULL).
     */
    public function filterByLiving(): self
    {
        return $this->filterByDateDeceased(null, Criteria::ISNULL);
    }

    /**
     * Only deceased persons (per_DateDeceased IS NOT NULL).
     */
    public function filterByDeceased(): self
    {
        return $this->filterByDateDeceased(null, Criteria::ISNOTNULL);
    }

    /**
     * Hide members of protected households from users who may not read them.
     * Admins, members of the household, and the user's own record still pass.
     */
    public function filterVisibleTo(User $user): self
    {
        if ($user->isAdmin()) {
            return $this;
        }

        return $this->where(
            'person_per.per_fam_ID NOT IN (SELECT fam_ID FROM family_fam WHERE fam_Confidential = 1 AND fam_ID <> ?)',
            (new RecordAccessPolicy())->ownFamilyId($user),
            \PDO::PARAM_INT,
        );
    }
}
