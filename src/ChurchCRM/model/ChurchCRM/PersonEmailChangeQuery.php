<?php

/**
 * MIT License. This file is part of the Propel package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ChurchCRM\model\ChurchCRM;

use ChurchCRM\model\ChurchCRM\Base\PersonEmailChangeQuery as BasePersonEmailChangeQuery;

/**
 * Skeleton subclass for performing query and update operations on the 'person_email_change_pec' table.
 *
 * An emailed link that changes or confirms a person's sign-in email (member sign-in design §9). 'change' goes to the new address and makes it the person's proven email; 'confirm' goes to the previous, proven address and confirms an unconfirmed replacement
 *
 * You should add additional methods to this class to meet the
 * application requirements.  This class will only be generated as
 * long as it does not already exist in the output directory.
 *
 * @template ParentQuery extends \Propel\Runtime\ActiveQuery\TypedModelCriteria|null = null
 * @extends BasePersonEmailChangeQuery<ParentQuery>
 */
class PersonEmailChangeQuery extends BasePersonEmailChangeQuery
{

}
