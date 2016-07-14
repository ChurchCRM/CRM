<?php

namespace ChurchCRM;

use ChurchCRM\Base\Deposit as BaseDeposit;
use Propel\Runtime\ActiveQuery\Criteria;
use ChurchCRM\PledgeQuery as ChildPledgeQuery;

/**
 * Skeleton subclass for representing a row from the 'deposit_dep' table.
 *
 *
 *
 * You should add additional methods to this class to meet the
 * application requirements.  This class will only be generated as
 * long as it does not already exist in the output directory.
 *
 */
class Deposit extends BaseDeposit
{

  public function getFundTotals()
  {
     //there is probably a better way to do this with Propel ORM...
     $funds = array();
     foreach($this->getPledges() as $pledge)
     {
       if ($pledge->getFundid() && is_null($funds[$pledge->getFundid()]))
       {
         $funds[$pledge->getFundid()] =  new \stdClass();
       }
      $funds[$pledge->getFundid()]->Total += $pledge->getAmount();
      $funds[$pledge->getFundid()]->Name = $pledge->getDonationFund()->getName();
    }
    return $funds;
  }
  
   public function getPledgesJoinAll(Criteria $criteria = null, ConnectionInterface $con = null, $joinBehavior = Criteria::LEFT_JOIN)
    {
        $query = ChildPledgeQuery::create(null, $criteria);
        $query->joinWith('Family', Criteria::RIGHT_JOIN);
        $query->joinWith('DonationFund', Criteria::RIGHT_JOIN);
        return $this->getPledges($query, $con);
    }
}
