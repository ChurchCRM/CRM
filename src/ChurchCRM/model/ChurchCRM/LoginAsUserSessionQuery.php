<?php

/**
 * MIT License. This file is part of the Propel package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ChurchCRM\model\ChurchCRM;

use ChurchCRM\model\ChurchCRM\Base\LoginAsUserSessionQuery as BaseLoginAsUserSessionQuery;

/**
 * Skeleton subclass for performing query and update operations on the 'login_as_user_session_las' table.
 *
 * One row per Login as User session (member sign-in design §6.5): the administrator acting (actor), the person signed in as (subject), when it started and ended, and why it ended: stop, signout, timeout or ended
 *
 * You should add additional methods to this class to meet the
 * application requirements.  This class will only be generated as
 * long as it does not already exist in the output directory.
 *
 * @template ParentQuery extends \Propel\Runtime\ActiveQuery\TypedModelCriteria|null = null
 * @extends BaseLoginAsUserSessionQuery<ParentQuery>
 */
class LoginAsUserSessionQuery extends BaseLoginAsUserSessionQuery
{

}
