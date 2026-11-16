<?php

namespace ChurchCRM\model\ChurchCRM;

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
    public function filterByActive(): self
    {
        return $this->filterByDateDeactivated(null, Criteria::ISNULL);
    }
}
