<?php

namespace ChurchCRM\Tasks;

use ChurchCRM\DepositQuery;
use Propel\Runtime\ActiveQuery\Criteria;
use ChurchCRM\dto\SystemURLs;

class UnsupportedDepositCheck implements iTask
{
    private $count;

    public function __construct()
    {
        $UnsupportedQuery = DepositQuery::create()
                ->filterByType("Bank", Criteria::NOT_EQUAL)
                ->find();
        $this->count = $UnsupportedQuery->count();
    }

    public function isActive()
    {
        return $this->count > 0;
    }

    public function isAdmin()
    {
        return false;
    }

    public function getLink()
    {
        return SystemURLs::getSupportURL(array_pop(explode('\\', __CLASS__)));
    }

    public function getTitle()
    {
        return gettext('Unsupported Deposit Types Detected') . " (" . $this->count . ")";
    }

    public function getDesc()
    {
        return gettext("Support for eGive, Credit Card, and Bank Draft payments has been deprecated.  Existing non-bank reports may no longer be accessible in future versions.");
    }

}
