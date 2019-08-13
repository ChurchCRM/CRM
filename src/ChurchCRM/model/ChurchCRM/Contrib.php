<?php

namespace ChurchCRM;

use ChurchCRM\Base\Contrib as BaseContrib;

/**
 * Skeleton subclass for representing a row from the 'contrib_con' table.
 *
 * This contains all contribution information
 *
 * You should add additional methods to this class to meet the
 * application requirements.  This class will only be generated as
 * long as it does not already exist in the output directory.
 *
 */
class Contrib extends BaseContrib
{
    // public function toArray()
    // {
    //   $array = parent::toArray();
    //   //$family = $this->getFamily();
    //   $person = PersonQuery::create()->findPk($this->con_ContribID, $con);
    //   if($person) {
    //     $array['PersonString']=$person->getFormattedName(SystemConfig::getValue('iPersonNameStyle')) . " - " . $person->getAddress();
    //   }

    //   return $array;
    // }
}
