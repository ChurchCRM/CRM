<?php

namespace ChurchCRM\model\ChurchCRM;

use ChurchCRM\model\ChurchCRM\Base\PledgeQuery as BasePledgeQuery;

/**
 * Skeleton subclass for performing query and update operations on the 'pledge_plg' table.
 *
 *
 *
 * You should add additional methods to this class to meet the
 * application requirements.  This class will only be generated as
 * long as it does not already exist in the output directory.
 */
class PledgeQuery extends BasePledgeQuery
{
    /**
     * Filter for Tax Report (Giving Report)
     * Payments with optional date range, funds, families, and classifications
     *
     * @param string $dateStart Start date (Y-m-d format)
     * @param string $dateEnd End date (Y-m-d format)
     * @param array $fundIds Optional fund IDs to filter
     * @param array $familyIds Optional family IDs to filter
     * @param array $classificationIds Optional classification IDs to filter
     * @return self
     */
    public function filterForTaxReport(
        string $dateStart = '',
        string $dateEnd = '',
        array $fundIds = [],
        array $familyIds = [],
        array $classificationIds = []
    ): self {
        $this->filterByPledgeOrPayment('Payment');

        if (!empty($dateStart)) {
            $this->filterByDate($dateStart, \Propel\Runtime\ActiveQuery\Criteria::GREATER_EQUAL);
        }
        if (!empty($dateEnd)) {
            $this->filterByDate($dateEnd, \Propel\Runtime\ActiveQuery\Criteria::LESS_EQUAL);
        }
        if (!empty($fundIds)) {
            $this->filterByFundId($fundIds, \Propel\Runtime\ActiveQuery\Criteria::IN);
        }
        if (!empty($familyIds)) {
            $this->filterByFamId($familyIds, \Propel\Runtime\ActiveQuery\Criteria::IN);
        }
        // Note: Classification filtering is complex and requires post-processing
        // as it involves a relationship through ListOption. Can be added to service layer if needed.

        return $this->leftJoinWithFamily()
            ->leftJoinWithDonationFund()
            ->leftJoinWithPerson()
            ->orderByFamId()
            ->orderByDate();
    }

    /**
     * Filter for Advanced Deposit Report
     * Payments with sorting, date range, funds, families, methods, and classifications
     *
     * @param string $dateStart Start date (Y-m-d format)
     * @param string $dateEnd End date (Y-m-d format)
     * @param array $fundIds Optional fund IDs to filter
     * @param array $familyIds Optional family IDs to filter
     * @param array $methods Optional payment methods to filter
     * @param array $classificationIds Optional classification IDs to filter
     * @param string $sort Sort order: 'deposit', 'fund', or 'family'
     * @return self
     */
    public function filterForAdvancedDeposit(
        string $dateStart = '',
        string $dateEnd = '',
        array $fundIds = [],
        array $familyIds = [],
        array $methods = [],
        array $classificationIds = [],
        string $sort = 'deposit'
    ): self {
        $this->filterByPledgeOrPayment('Payment');

        if (!empty($dateStart)) {
            $this->filterByDate($dateStart, \Propel\Runtime\ActiveQuery\Criteria::GREATER_EQUAL);
        }
        if (!empty($dateEnd)) {
            $this->filterByDate($dateEnd, \Propel\Runtime\ActiveQuery\Criteria::LESS_EQUAL);
        }
        if (!empty($fundIds)) {
            $this->filterByFundId($fundIds, \Propel\Runtime\ActiveQuery\Criteria::IN);
        }
        if (!empty($familyIds)) {
            $this->filterByFamId($familyIds, \Propel\Runtime\ActiveQuery\Criteria::IN);
        }
        // Handle payment methods - filter by all methods using IN
        if (!empty($methods)) {
            $this->filterByMethod($methods, \Propel\Runtime\ActiveQuery\Criteria::IN);
        }
        // Note: Classification filtering is complex and requires post-processing
        // as it involves a relationship through ListOption. Can be added to service layer if needed.

        // Use left joins to avoid filtering out records with missing relationships
        $this->leftJoinWithFamily()
            ->leftJoinWithDeposit()
            ->leftJoinWithDonationFund()
            ->leftJoinWithPerson();

        // Apply sorting
        if ($sort === 'fund') {
            $this->orderByFundId()
                ->orderByFamId();
        } elseif ($sort === 'family') {
            $this->orderByFamId()
                ->orderByFundId();
        } else {
            // default: 'deposit'
            $this->orderByDepId()
                ->orderByFundId()
                ->orderByFamId();
        }

        return $this;
    }

    /**
     * Filter for Zero Givers Report
     * Get pledges by date range and optional filters
     *
     * @param string $dateStart Start date (Y-m-d format)
     * @param string $dateEnd End date (Y-m-d format)
     * @return self
     */
    public function filterForZeroGivers(
        string $dateStart = '',
        string $dateEnd = ''
    ): self {
        $this->filterByPledgeOrPayment('Payment');

        if (!empty($dateStart)) {
            $this->filterByDate($dateStart, \Propel\Runtime\ActiveQuery\Criteria::GREATER_EQUAL);
        }
        if (!empty($dateEnd)) {
            $this->filterByDate($dateEnd, \Propel\Runtime\ActiveQuery\Criteria::LESS_EQUAL);
        }

        return $this->orderByFamId();
    }
}
