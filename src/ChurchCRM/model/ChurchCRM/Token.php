<?php

namespace ChurchCRM;

use ChurchCRM\Base\Token as BaseToken;

/**
 * Skeleton subclass for representing a row from the 'tokens' table.
 *
 *
 *
 * You should add additional methods to this class to meet the
 * application requirements.  This class will only be generated as
 * long as it does not already exist in the output directory.
 *
 */
class Token extends BaseToken
{
  public function isVerifyToken()
  {
    return "verify" === $this->getType();
  }

  public function isValid()
  {
    $valid = true;
    if ($this->getUseCount() !== null) {
      $valid = $this->getUseCount() > 0;
    }

    if ($this->getValidUntilDate() !== null) {
      $today = new \DateTime();
      $valid = $this->getValidUntilDate() > $today;
    }
    return $valid;
  }

}
