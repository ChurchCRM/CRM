<?php

namespace ChurchCRM\model\ChurchCRM;

use ChurchCRM\model\ChurchCRM\Base\PledgeQuery as BasePledgeQuery;
use ChurchCRM\model\ChurchCRM\Map\DepositTableMap;
use ChurchCRM\model\ChurchCRM\Map\DonationFundTableMap;
use ChurchCRM\model\ChurchCRM\Map\FamilyTableMap;
use Propel\Runtime\ActiveQuery\Criteria;

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
     * @param bool $byDepositDate True to apply the date range to the deposit date instead of the payment date
     * @return self
     */
    public function filterForTaxReport(
        string $dateStart = '',
        string $dateEnd = '',
        array $fundIds = [],
        array $familyIds = [],
        array $classificationIds = [],
        bool $byDepositDate = false
    ): self {
        $this->filterByPledgeOrPayment('Payment');
        $this->filterByDateRange($dateStart, $dateEnd, $byDepositDate);

        if (!empty($fundIds)) {
            $this->filterByFundId($fundIds, Criteria::IN);
        }
        $this->filterByFamiliesAndClassifications($familyIds, $classificationIds);

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
     * @param string $datetype 'Payment' for pledge date, 'Deposit' for deposit date
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
        string $datetype = 'Payment',
        string $sort = 'deposit'
    ): self {
        $this->filterByPledgeOrPayment('Payment');
        $this->addSelfSelectColumns(); // pin Pledge columns first before any leftJoinWithXxx()

        $this->filterByDateRange($dateStart, $dateEnd, $datetype === 'Deposit');

        if (!empty($fundIds)) {
            $this->filterByFundId($fundIds, Criteria::IN);
        }
        $this->filterByFamiliesAndClassifications($familyIds, $classificationIds);
        // Handle payment methods - use IN clause (supports array)
        if (!empty($methods)) {
            $this->filterByMethod($methods, Criteria::IN);
        }

        // Left joins for optional relationships
        $this->leftJoinWithFamily()
            ->leftJoinWithDonationFund()
            ->leftJoinWithPerson();

        // Add columns from joined tables to avoid needing foreign objects in toArray()
        $this->addAsColumn('FamilyName', FamilyTableMap::COL_FAM_NAME)
            ->addAsColumn('FamilyAddress1', FamilyTableMap::COL_FAM_ADDRESS1)
            ->addAsColumn('FamilyAddress2', FamilyTableMap::COL_FAM_ADDRESS2)
            ->addAsColumn('FamilyCity', FamilyTableMap::COL_FAM_CITY)
            ->addAsColumn('FamilyState', FamilyTableMap::COL_FAM_STATE)
            ->addAsColumn('FamilyZip', FamilyTableMap::COL_FAM_ZIP)
            ->addAsColumn('FamilyCountry', FamilyTableMap::COL_FAM_COUNTRY)
            ->addAsColumn('FundName', DonationFundTableMap::COL_FUN_NAME)
            ->addAsColumn('DepositDate', DepositTableMap::COL_DEP_DATE);

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
            $this->filterByDate($dateStart, Criteria::GREATER_EQUAL);
        }
        if (!empty($dateEnd)) {
            $this->filterByDate($dateEnd, Criteria::LESS_EQUAL);
        }

        return $this->orderByFamId();
    }

    /**
     * Apply a date range to the payment date, or to the deposit date the payment was banked on.
     *
     * The deposit join is made once inside a single useDepositQuery() block: calling
     * innerJoinWithDeposit() first, or opening a second useDepositQuery(), leaves
     * conditions pointing at a stale alias and the query returns no rows.
     */
    private function filterByDateRange(string $dateStart, string $dateEnd, bool $byDepositDate): void
    {
        if ($byDepositDate) {
            $depositQuery = $this->useDepositQuery(null, Criteria::INNER_JOIN);
            if (!empty($dateStart)) {
                $depositQuery->filterByDate($dateStart, Criteria::GREATER_EQUAL);
            }
            if (!empty($dateEnd)) {
                $depositQuery->filterByDate($dateEnd, Criteria::LESS_EQUAL);
            }
            $depositQuery->endUse();

            return;
        }

        if (!empty($dateStart)) {
            $this->filterByDate($dateStart, Criteria::GREATER_EQUAL);
        }
        if (!empty($dateEnd)) {
            $this->filterByDate($dateEnd, Criteria::LESS_EQUAL);
        }
        $this->leftJoinWithDeposit();
    }

    /**
     * Restrict payments to the chosen families, and to families that have a member in one of
     * the chosen classifications. Both given: a family must satisfy both.
     */
    private function filterByFamiliesAndClassifications(array $familyIds, array $classificationIds): void
    {
        $familyIds = array_values(array_unique(array_map('intval', $familyIds)));

        if (!empty($classificationIds)) {
            $classifiedFamilyIds = FamilyQuery::create()
                ->usePersonQuery()
                    ->filterByClsId(array_map('intval', $classificationIds), Criteria::IN)
                ->endUse()
                ->select('Id')
                ->distinct()
                ->find()
                ->toArray();
            $classifiedFamilyIds = array_map('intval', $classifiedFamilyIds);
            $familyIds = empty($familyIds) ? $classifiedFamilyIds : array_values(array_intersect($familyIds, $classifiedFamilyIds));

            // No family qualifies: match nothing rather than dropping the filter.
            if (empty($familyIds)) {
                $familyIds = [-1];
            }
        }

        if (!empty($familyIds)) {
            $this->filterByFamId($familyIds, Criteria::IN);
        }
    }
}
