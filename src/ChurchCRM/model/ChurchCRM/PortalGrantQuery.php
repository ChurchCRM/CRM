<?php

/**
 * MIT License. This file is part of the Propel package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ChurchCRM\model\ChurchCRM;

use ChurchCRM\model\ChurchCRM\Base\PortalGrantQuery as BasePortalGrantQuery;

/**
 * Skeleton subclass for performing query and update operations on the 'portal_grant_pgr' table.
 *
 * A portal capability an administrator gave one person on one scope (design §8.3). Rule-derived grants are never stored
 *
 * You should add additional methods to this class to meet the
 * application requirements.  This class will only be generated as
 * long as it does not already exist in the output directory.
 *
 * @template ParentQuery extends \Propel\Runtime\ActiveQuery\TypedModelCriteria|null = null
 * @extends BasePortalGrantQuery<ParentQuery>
 */
class PortalGrantQuery extends BasePortalGrantQuery
{

}
