<?php

namespace ChurchCRM\model\ChurchCRM;

use ChurchCRM\model\ChurchCRM\Base\Token as BaseToken;

/**
 * Skeleton subclass for representing a row from the 'tokens' table.
 *
 *
 *
 * You should add additional methods to this class to meet the
 * application requirements.  This class will only be generated as
 * long as it does not already exist in the output directory.
 */
class Token extends BaseToken
{
    public const TYPE_FAMILY_VERIFY = 'verifyFamily';
    private const TYPE_PASSWORD = 'password';

    public function build($type, $referenceId): void
    {
        $this->setReferenceId($referenceId);
        $this->setToken(uniqid());
        switch ($type) {
            case 'verifyFamily':
                $this->setValidUntilDate(strtotime('+1 week'));
                $this->setRemainingUses(5);
                break;
            case 'password':
                $this->setValidUntilDate(strtotime('+1 day'));
                $this->setRemainingUses(1);
                break;
        }
        $this->setType($type);
    }

    public function isVerifyFamilyToken(): bool
    {
        return self::TYPE_FAMILY_VERIFY === $this->getType();
    }

    public function isPasswordResetToken(): bool
    {
        return self::TYPE_PASSWORD === $this->getType();
    }

    public function isValid(): bool
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
