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

  public function build($type, $referenceId)
  {
    $this->setReferenceId($referenceId);
    $this->setToken(uniqid());
    switch ($type) {
      case "verify":
        $this->setValidUntilDate(strtotime("+1 week"));
        $this->setRemainingUses(5);
        $this->setType($type);
        break;
    }
  }


  public function isVerifyFamilyToken()
  {
    return "verifyFamily" === $this->getType();
  }

  public function isValid()
  {
    $hasUses = true;
    if ($this->getRemainingUses() !== null) {
      $hasUses = $this->getRemainingUses() > 0;
    }

    $stillValidDate = true;
    if ($this->getValidUntilDate() !== null) {
      $today = new \DateTime();
      $stillValidDate = $this->getValidUntilDate() > $today;
    }
    return $stillValidDate && $hasUses;
  }

}
