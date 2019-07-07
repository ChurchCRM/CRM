<?php

namespace ChurchCRM;

use ChurchCRM\Base\Pledge as BasePledge;
use Propel\Runtime\Exception\PropelException;
use Propel\Runtime\Connection\ConnectionInterface;

/**
 * Skeleton subclass for representing a row from the 'pledge_plg' table.
 *
 *
 *
 * You should add additional methods to this class to meet the
 * application requirements.  This class will only be generated as
 * long as it does not already exist in the output directory.
 */
class Pledge extends BasePledge
{

   /**
     * Code to be run before deleting the object in database
     * @param  ConnectionInterface $con
     * @return boolean
     */
    public function preDelete(ConnectionInterface $con = null)
    {
        $deposit = DepositQuery::create()->findOneById($this->getDepid());
        if (!$deposit->getClosed()) {
            return parent::preDelete($con);
        } else {
            throw new PropelException('Cannot delete a payment from a closed deposit', 500);
        }
    }
    
    public function toArray()
    {
      $array = parent::toArray();
      $family = $this->getFamily();
      
      if($family)
      {
        $array['FamilyString']=$family->getFamilyString();
      }
      
      return $array;
    }
}
