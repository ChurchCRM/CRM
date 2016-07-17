<?php

namespace ChurchCRM;

use ChurchCRM\Base\DepositQuery as BaseDepositQuery;

/**
 * Skeleton subclass for performing query and update operations on the 'deposit_dep' table.
 *
 *
 *
 * You should add additional methods to this class to meet the
 * application requirements.  This class will only be generated as
 * long as it does not already exist in the output directory.
 *
 */
class DepositQuery extends BaseDepositQuery
{
  function preSelect(\Propel\Runtime\Connection\ConnectionInterface $con)
  {
    
    $this->joinPledge();
    $this->groupBy("Deposit.Id");
    $this->withColumn("SUM(Pledge.Amount)","totalAmount");
     parent::preSelect($con);
  }
}
