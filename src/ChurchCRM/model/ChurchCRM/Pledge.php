<?php

namespace ChurchCRM\model\ChurchCRM;

use ChurchCRM\model\ChurchCRM\Base\Pledge as BasePledge;
use Propel\Runtime\Connection\ConnectionInterface;
use Propel\Runtime\Exception\PropelException;
use Propel\Runtime\Map\TableMap;

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
    public function getFormattedFY()
    {
        return MakeFYString($this->getFyId());
    }

    /**
     * Code to be run before deleting the object in database.
     *
     * @param ConnectionInterface $con
     *
     * @return bool
     */
    public function preDelete(ConnectionInterface $con = null)
    {
        $deposit = DepositQuery::create()->findOneById($this->getDepId());
        if (!$deposit->getClosed()) {
            return parent::preDelete($con);
        } else {
            throw new PropelException('Cannot delete a payment from a closed deposit', 500);
        }
    }

    public function toArray($keyType = TableMap::TYPE_PHPNAME, $includeLazyLoadColumns = true, $alreadyDumpedObjects = [], $includeForeignObjects = false)
    {
        $array = parent::toArray($keyType, $includeLazyLoadColumns, $alreadyDumpedObjects, $includeForeignObjects);
        $family = $this->getFamily();

        if ($family) {
            // This must be done in the Pledge object model instead of during a query with Propel's ->WithColumn() syntax
            // because the getFamilyString logic is implemented in PHP, not SQL, and the consumer of this object
            // expects to see the fully-formatted family string (name, address, state) instead of only family name.
            // i.e. commit 33b40c973685b7f03cfb3e79241fe53594b83f04 does it incorrectly.
            $array['FamilyString'] = $family->getFamilyString();
        }

        return $array;
    }
}
