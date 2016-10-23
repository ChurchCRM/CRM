<?php

namespace ChurchCRM;

use ChurchCRM\Base\Pledge as BasePledge;
use Propel\Runtime\Exception\PropelException;

/**
 * Skeleton subclass for representing a row from the 'pledge_plg' table.
 *
 *
 *
 * You should add additional methods to this class to meet the
 * application requirements.  This class will only be generated as
 * long as it does not already exist in the output directory.
 *
 */
class Pledge extends BasePledge
{
  public function preDelete()
  {
    
   $deposit = DepositQuery::create()->findOneById($this->getDepid());
    if (!$deposit->getClosed()) {
      return true;
    } else {
     throw new PropelException("Cannot delete a payment from a closed deposit",500);
    }
  }
}
