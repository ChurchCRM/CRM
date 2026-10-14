<?php

namespace ChurchCRM\model\ChurchCRM;

use ChurchCRM\Authorization\RecordAccessPolicy;
use ChurchCRM\model\ChurchCRM\Base\FamilyQuery as BaseFamilyQuery;
use Propel\Runtime\ActiveQuery\Criteria;

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
        $hidden = (new RecordAccessPolicy())->hiddenFamilyIds($user);

        return $hidden === [] ? $this : $this->filterById($hidden, Criteria::NOT_IN);
    }
}
