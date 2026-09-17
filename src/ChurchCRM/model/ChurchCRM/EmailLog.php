<?php

/**
 * MIT License. This file is part of the Propel package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ChurchCRM\model\ChurchCRM;

use ChurchCRM\model\ChurchCRM\Base\EmailLog as BaseEmailLog;

/**
 * Skeleton subclass for representing a row from the 'email_log_eml' table.
 *
 * One row per recipient for every email ChurchCRM sent through BaseEmail::send(): composer messages, birthday greetings, verification links, account emails, notifications. Links to the person/family the address belonged to at send time and to the user who sent a composer message. The rendered body is stored only for email classes that allow it (composer); account emails never store a body.
 *
 * You should add additional methods to this class to meet the
 * application requirements.  This class will only be generated as
 * long as it does not already exist in the output directory.
 */
class EmailLog extends BaseEmailLog
{

}
