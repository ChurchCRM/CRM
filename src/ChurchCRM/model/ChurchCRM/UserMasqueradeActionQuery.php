<?php

/**
 * MIT License. This file is part of the Propel package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ChurchCRM\model\ChurchCRM;

use ChurchCRM\model\ChurchCRM\Base\UserMasqueradeActionQuery as BaseUserMasqueradeActionQuery;

/**
 * Skeleton subclass for performing query and update operations on the 'user_masquerade_action_uma' table.
 *
 * One row per write request (any method but GET, HEAD and OPTIONS) made during a Login as User session (#9843): method, path without the query string, response status and time. The session row names the administrator who made it.
 *
 * You should add additional methods to this class to meet the
 * application requirements.  This class will only be generated as
 * long as it does not already exist in the output directory.
 *
 * @template ParentQuery extends \Propel\Runtime\ActiveQuery\TypedModelCriteria|null = null
 * @extends BaseUserMasqueradeActionQuery<ParentQuery>
 */
class UserMasqueradeActionQuery extends BaseUserMasqueradeActionQuery
{

}
