<?php

namespace ChurchCRM\model\ChurchCRM;

use ChurchCRM\model\ChurchCRM\Base\Token as BaseToken;
use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\Connection\ConnectionInterface;

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
    public const TYPE_PASSWORD = 'password';

    public function build($type, $referenceId): void
    {
        $this->setReferenceId($referenceId);
        $this->setToken(bin2hex(random_bytes(32)));
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

    public static function passwordResetStorageKey(string $token): string
    {
        return 'sha256:' . hash('sha256', $token);
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

    public function consume(?ConnectionInterface $connection = null): bool
    {
        $remainingUses = $this->getRemainingUses();
        if ($remainingUses === null || $remainingUses <= 0) {
            return false;
        }

        // Compare-and-swap prevents stale objects from consuming the same use twice.
        $updated = TokenQuery::create()
            ->filterByToken($this->getToken())
            ->filterByType($this->getType())
            ->filterByRemainingUses($remainingUses)
            ->filterByValidUntilDate(null, Criteria::ISNULL)
            ->_or()
            ->filterByValidUntilDate(date('Y-m-d H:i:s'), Criteria::GREATER_THAN)
            ->update(['RemainingUses' => $remainingUses - 1], $connection);

        if ($updated !== 1) {
            return false;
        }

        $this->setRemainingUses($remainingUses - 1);
        $this->resetModified();

        return true;
    }
}
