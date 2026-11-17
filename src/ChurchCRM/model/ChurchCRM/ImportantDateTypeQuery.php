<?php

/**
 * MIT License. This file is part of the Propel package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ChurchCRM\model\ChurchCRM;

use ChurchCRM\model\ChurchCRM\Base\ImportantDateTypeQuery as BaseImportantDateTypeQuery;

/**
 * @template ParentQuery extends \Propel\Runtime\ActiveQuery\TypedModelCriteria|null = null
 * @extends BaseImportantDateTypeQuery<ParentQuery>
 */
class ImportantDateTypeQuery extends BaseImportantDateTypeQuery
{
    public function orderForDisplay(): self
    {
        return $this->orderBySortOrder()->orderByName();
    }
}
