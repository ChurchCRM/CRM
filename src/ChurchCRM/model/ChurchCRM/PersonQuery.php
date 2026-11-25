<?php

namespace ChurchCRM\model\ChurchCRM;

use ChurchCRM\model\ChurchCRM\Base\PersonQuery as BasePersonQuery;
use ChurchCRM\Service\ClassificationService;
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
     * Active people: not deactivated, not awaiting review, outside the Inactive classifications.
     */
    public function filterByActive(): self
    {
        return $this->filterByDateDeactivated(null, Criteria::ISNULL)->filterByNeedsReview(false)->filterByActiveClassification();
    }

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
     * Only persons outside the Inactive classifications (sInactiveClassification).
     */
    public function filterByActiveClassification(): self
    {
        return (new ClassificationService())->excludeInactive($this);
    }
}
