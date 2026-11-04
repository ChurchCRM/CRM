<?php

/**
 * MIT License. This file is part of the Propel package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ChurchCRM\model\ChurchCRM;

use ChurchCRM\model\ChurchCRM\Base\LoginAsPersonAction as BaseLoginAsPersonAction;

/**
 * Skeleton subclass for representing a row from the 'login_as_person_action_lpa' table.
 *
 * One row per write request (any method but GET, HEAD and OPTIONS) made during a Login as Person session: method, path without the query string, response status and time
 *
 * You should add additional methods to this class to meet the
 * application requirements.  This class will only be generated as
 * long as it does not already exist in the output directory.
 */
class LoginAsPersonAction extends BaseLoginAsPersonAction
{

}
