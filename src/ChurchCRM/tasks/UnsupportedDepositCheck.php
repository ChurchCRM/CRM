<?php

namespace ChurchCRM\Tasks;

use ChurchCRM\dto\SystemURLs;
use ChurchCRM\model\ChurchCRM\DepositQuery;
use Propel\Runtime\ActiveQuery\Criteria;

class UnsupportedDepositCheck implements TaskInterface
{
    private $count;

    public function __construct()
    {
        $UnsupportedQuery = DepositQuery::create()
                ->filterByType('Bank', Criteria::NOT_EQUAL)
                ->find();
        $this->count = $UnsupportedQuery->count();
    }

    public function isActive(): bool
    {
        return $this->count > 0;
    }

    public function isAdmin(): bool
    {
        return false;
    }

    public function getLink(): string
    {
        return SystemURLs::getSupportURL(array_pop(explode('\\', self::class)));
    }

    public function getTitle(): string
    {
        return gettext('Unsupported Deposit Types Detected') . ' (' . $this->count . ')';
    }

    public function getDesc(): string
    {
        return gettext('Support for eGive, Credit Card, and Bank Draft payments has been deprecated.  Existing non-bank reports may no longer be accessible in future versions.');
    }
}
