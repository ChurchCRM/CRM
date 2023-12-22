<?php

namespace ChurchCRM\model\ChurchCRM;

use ChurchCRM\model\ChurchCRM\Base\DepositQuery as BaseDepositQuery;
use Propel\Runtime\Connection\ConnectionInterface;

/**
 * Skeleton subclass for performing query and update operations on the 'deposit_dep' table.
 *
 *
 *
 * You should add additional methods to this class to meet the
 * application requirements.  This class will only be generated as
 * long as it does not already exist in the output directory.
 */
class DepositQuery extends BaseDepositQuery
{
    public function preSelect(ConnectionInterface $con): void
    {
        $this->joinPledge();
        $this->groupBy('Deposit.Id');
        $this->withColumn('SUM(Pledge.Amount)', 'totalAmount');
        parent::preSelect($con);
    }
}
