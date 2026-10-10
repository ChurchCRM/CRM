<?php

/**
 * MIT License. This file is part of the Propel package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ChurchCRM\model\ChurchCRM;

use ChurchCRM\model\ChurchCRM\Base\UserMasqueradeSessionQuery as BaseUserMasqueradeSessionQuery;

/**
 * Skeleton subclass for performing query and update operations on the 'user_masquerade_session_ums' table.
 *
 * One row per Login as User session (#9843): the administrator, the user they signed in as, when it started and ended, and how it ended (exit, signout or timeout). Rows are kept indefinitely; the user page lists them.
 *
 * You should add additional methods to this class to meet the
 * application requirements.  This class will only be generated as
 * long as it does not already exist in the output directory.
 *
 * @template ParentQuery extends \Propel\Runtime\ActiveQuery\TypedModelCriteria|null = null
 * @extends BaseUserMasqueradeSessionQuery<ParentQuery>
 */
class UserMasqueradeSessionQuery extends BaseUserMasqueradeSessionQuery
{

}
