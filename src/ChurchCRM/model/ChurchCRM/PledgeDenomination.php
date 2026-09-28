<?php

/**
 * MIT License. This file is part of the Propel package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ChurchCRM\model\ChurchCRM;

use ChurchCRM\model\ChurchCRM\Base\PledgeDenomination as BasePledgeDenomination;

/**
 * Skeleton subclass for representing a row from the 'pledge_denominations_pdem' table.
 *
 * Cash denomination counts recorded per pledge/payment GroupKey for a deposit (cash-counting workflow). Column plg_depID mirrors the name used in FinancialService raw SQL.
 *
 * You should add additional methods to this class to meet the
 * application requirements.  This class will only be generated as
 * long as it does not already exist in the output directory.
 */
class PledgeDenomination extends BasePledgeDenomination
{

}
