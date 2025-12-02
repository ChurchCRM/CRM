<?php

namespace ChurchCRM\Service;

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\MICRFunctions;
use ChurchCRM\model\ChurchCRM\Deposit;
use ChurchCRM\model\ChurchCRM\DepositQuery;
use ChurchCRM\model\ChurchCRM\DonationFundQuery;
use ChurchCRM\model\ChurchCRM\FamilyQuery;
use ChurchCRM\model\ChurchCRM\Pledge;
use ChurchCRM\model\ChurchCRM\PledgeQuery;
use ChurchCRM\Utils\Functions;
use ChurchCRM\Utils\InputUtils;
use Propel\Runtime\ActiveQuery\Criteria;
use ChurchCRM\Service\AuthService;
use Propel\Runtime\Map\TableMap;
use Propel\Runtime\Collection\ObjectCollection;

class FinancialService
{
    public function deletePayment(string $groupKey): void
    {
        AuthService::requireUserGroupMembership('bFinance');
        PledgeQuery::create()->findOneByGroupKey($groupKey)->delete();
    }

    public function getPayments(?string $depID = null): array
    {
        $query = PledgeQuery::create();
        
        // If a specific deposit ID is provided, filter by that deposit
        if (!empty($depID)) {
            $query->filterByDepositId($depID);
        } else {
            // Filter by user permissions (only when getting all payments)
            if (!empty(AuthenticationManager::getCurrentUser()->getShowSince())) {
                $query->filterByDate(AuthenticationManager::getCurrentUser()->getShowSince(), Criteria::GREATER_EQUAL);
            }
            if (!AuthenticationManager::getCurrentUser()->isShowPayments()) {
                $query->filterByPledgeOrPayment('Payment', Criteria::NOT_EQUAL);
            }
            if (!AuthenticationManager::getCurrentUser()->isShowPledges()) {
                $query->filterByPledgeOrPayment('Pledge', Criteria::NOT_EQUAL);
            }
        }
        
        $query->innerJoinDonationFund()->withColumn('donationfund_fun.fun_Name', 'PledgeName');
        $data = $query->find();

        $rows = [];
        foreach ($data as $row) {
            $newRow['FormattedFY'] = $row->getFormattedFY();
            $newRow['GroupKey'] = $row->getGroupKey();
            $newRow['Amount'] = $row->getAmount();
            $newRow['Nondeductible'] = $row->getNondeductible();
            $newRow['Schedule'] = $row->getSchedule();
            $newRow['Method'] = $row->getMethod();
            $newRow['Comment'] = htmlspecialchars($row->getComment() ?? '', ENT_QUOTES, 'UTF-8');
            $newRow['PledgeOrPayment'] = $row->getPledgeOrPayment();
            $newRow['Date'] = $row->getDate('Y-m-d');
            $newRow['DateLastEdited'] = $row->getDateLastEdited('Y-m-d');
            $newRow['EditedBy'] = $row->getPerson()->getFullName();
            $newRow['Fund'] = $row->getPledgeName();
            $rows[] = $newRow;
        }

        return $rows;
    }

    public function getMemberByScanString(string $tScanString): array
    {
        if (!SystemConfig::getValue('bUseScannedChecks')) {
            throw new \Exception('Scanned Checks is disabled');
        }

        $micrObj = new MICRFunctions(); // Instantiate the MICR class
        $routeAndAccount = $micrObj->findRouteAndAccount($tScanString); // use routing and account number for matching
        if (!$routeAndAccount) {
            throw new \Exception('error in locating family');
        }
        $sSQL = 'SELECT fam_ID, fam_Name FROM family_fam WHERE fam_scanCheck="' . $routeAndAccount . '"';
        $rsFam = Functions::runQuery($sSQL);
        $row = mysqli_fetch_array($rsFam);
        $iCheckNo = $micrObj->findCheckNo($tScanString);

        return [
            'ScanString'      => $tScanString,
            'RouteAndAccount' => $routeAndAccount,
            'CheckNumber'     => $iCheckNo,
            'fam_ID'          => $row['fam_ID'],
            'fam_Name'        => $row['fam_Name'],
        ];
    }

    public function setDeposit(string $depositType, string $depositComment, string $depositDate, $iDepositSlipID = null, $depositClosed = false): void
    {
        if ($iDepositSlipID) {
            $deposit = DepositQuery::create()->findOneById($iDepositSlipID);
            $deposit
                ->setDate($depositDate)
                ->setComment(InputUtils::sanitizeText($depositComment))
                ->setEnteredby(AuthenticationManager::getCurrentUser()->getId())
                ->setClosed(intval($depositClosed));
            $deposit->save();
            if ($depositClosed && ($depositType === 'CreditCard' || $depositType === 'BankDraft')) {
                // Delete any failed transactions on this deposit slip now that it is closing
                $q = 'DELETE FROM pledge_plg WHERE plg_depID = ' . $iDepositSlipID . ' AND plg_PledgeOrPayment="Payment" AND plg_aut_Cleared=0';
                Functions::runQuery($q);
            }
        } else {
            $deposit = new Deposit();
            $deposit
                ->setDate($depositDate)
                ->setComment(InputUtils::sanitizeText($depositComment))
                ->setEnteredby(AuthenticationManager::getCurrentUser()->getId())
                ->setType($depositType);
            $deposit->save();
            $deposit->reload();

            $iDepositSlipID = $deposit->getId();
        }
        $_SESSION['iCurrentDeposit'] = $iDepositSlipID;
    }

    public function getDepositTotal($id, $type = null)
    {
        AuthService::requireUserGroupMembership('bFinance');
        $sqlClause = '';
        if ($type) {
            $sqlClause = "AND plg_method = '" . $type . "'";
        }
        // Get deposit total
        $sSQL = "SELECT SUM(plg_amount) AS deposit_total FROM pledge_plg WHERE plg_depID = '$id' AND plg_PledgeOrPayment = 'Payment' " . $sqlClause;
        $rsDepositTotal = Functions::runQuery($sSQL);
        [$deposit_total] = mysqli_fetch_row($rsDepositTotal);

        return $deposit_total;
    }
        // ...existing code...

    public function getPaymentViewURI(string $groupKey): string
    {
        return SystemURLs::getRootPath() . '/PledgeEditor.php?GroupKey=' . $groupKey;
    }

    public function getViewURI(string $Id): string
    {
        return SystemURLs::getRootPath() . '/DepositSlipEditor.php?DepositSlipID=' . $Id;
    }

    private function validateDate(object $payment): void
    {
        // Validate Date
        if (isset($payment->Date) && strlen($payment->Date) > 0) {
            [$iYear, $iMonth, $iDay] = sscanf($payment->Date, '%04d-%02d-%02d');
            if (!checkdate($iMonth, $iDay, $iYear)) {
                throw new \Exception('Invalid Date');
            }
        }
    }

    private function validateFund(object $payment): void
    {
        //Validate that the fund selection is valid:
        //If a single fund is selected, that fund must exist, and not equal the default "Select a Fund" selection.
        //If a split is selected, at least one fund must be non-zero, the total must add up to the total of all funds, and all funds in the split must be valid funds.
        $FundSplit = $payment->FundSplit;
        if (count($FundSplit) >= 1 && $FundSplit[0]->FundID !== 'None') { // split
            $nonZeroFundAmountEntered = 0;
            foreach ($FundSplit as $fund) {
                //$fun_active = $fundActive[$fun_id];
                if ($fund->Amount > 0) {
                    $nonZeroFundAmountEntered++;
                }
                if (SystemConfig::getValue('bEnableNonDeductible') && isset($fund->NonDeductible)) {
                    //Validate the NonDeductible Amount
                    if ($fund->NonDeductible > $fund->Amount) { //Validate the NonDeductible Amount
                        throw new \Exception(gettext("NonDeductible amount can't be greater than total amount."));
                    }
                }
            } // end foreach
            if (!$nonZeroFundAmountEntered) {
                throw new \Exception(gettext('At least one fund must have a non-zero amount.'));
            }
        } else {
            throw new \Exception('Must select a valid fund');
        }
    }

    public function locateFamilyCheck(string $checkNumber, string $fam_ID)
    {
        AuthService::requireUserGroupMembership('bFinance');
        $sSQL = 'SELECT count(plg_FamID) from pledge_plg
                 WHERE plg_CheckNo = ' . $checkNumber . ' AND
                 plg_FamID = ' . $fam_ID;
        $rCount = Functions::runQuery($sSQL);

        return mysqli_fetch_array($rCount)[0];
    }

    public function validateChecks(object $payment): void
    {
        AuthService::requireUserGroupMembership('bFinance');
        //validate that the payment options are valid
        //If the payment method is a check, then the check number must be present, and it must not already have been used for this family
        //if the payment method is cash, there must not be a check number
        if (!empty($payment->type) && $payment->type === 'Payment' && !empty($payment->iMethod) && $payment->iMethod === 'CHECK' && !isset($payment->iCheckNo)) {
            throw new \Exception(gettext('Must specify non-zero check number'));
        }
        // detect check inconsistencies
        if (!empty($payment->type) && $payment->type === 'Payment' && isset($payment->iCheckNo)) {
            if (!empty($payment->iMethod) && $payment->iMethod === 'CASH') {
                throw new \Exception(gettext("Check number not valid for 'CASH' payment"));
            } elseif (!empty($payment->iMethod) && $payment->iMethod === 'CHECK' && !empty($payment->FamilyID) && $this->locateFamilyCheck($payment->iCheckNo, $payment->FamilyID)) {
                //build routine to make sure this check number hasn't been used by this family yet (look at group key)
                throw new \Exception("Check number '" . $payment->iCheckNo . "' for selected family already exists.");
            }
        }
    }

    public function processCurrencyDenominations(object $payment, string $groupKey): void
    {
        if (empty($payment->cashDenominations)) {
            return;
        }
        $currencyDenoms = json_decode($payment->cashDenominations, null, 512, JSON_THROW_ON_ERROR);
        foreach ($currencyDenoms as $cdom) {
            if (empty($payment->DepositID) || empty($cdom->currencyID) || empty($cdom->Count)) {
                continue;
            }
            $sSQL = "INSERT INTO pledge_denominations_pdem (pdem_plg_GroupKey, plg_depID, pdem_denominationID, pdem_denominationQuantity)
      VALUES ('" . $groupKey . "','" . $payment->DepositID . "','" . $cdom->currencyID . "','" . $cdom->Count . "')";
            Functions::runQuery($sSQL);
            unset($sSQL);
        }
    }

    public function insertPledgeorPayment(object $payment)
    {
        AuthService::requireUserGroupMembership('bFinance');
        // Only set PledgeOrPayment when the record is first created
        // loop through all funds and create non-zero amount pledge records
        $FundSplit = json_decode($payment->FundSplit, null, 512, JSON_THROW_ON_ERROR);
        foreach ($FundSplit as $Fund) {
            if ($Fund->Amount > 0) {  //Only insert a row in the pledge table if this fund has a non zero amount.
                if (!isset($sGroupKey)) {  //a GroupKey references a single familie's payment, and transcends the fund splits.  Sharing the same Group Key for this payment helps clean up reports.
                    if ($payment->iMethod === 'CHECK') {
                        $sGroupKey = genGroupKey($payment->iCheckNo, $payment->FamilyID, $Fund->FundID, $payment->Date);
                    } elseif ($payment->iMethod === 'BANKDRAFT') {
                        if (!isset($payment->iAutID)) {
                            $iAutID = 'draft';
                        }
                        $sGroupKey = genGroupKey($iAutID, $payment->FamilyID, $Fund->FundID, $payment->Date);
                    } elseif ($payment->iMethod === 'CREDITCARD') {
                        if (!isset($payment->iAutID)) {
                            $iAutID = 'credit';
                        }
                        $sGroupKey = genGroupKey($iAutID, $payment->FamilyID, $Fund->FundID, $payment->Date);
                    } else {
                        $sGroupKey = genGroupKey('cash', $payment->FamilyID, $Fund->FundID, $payment->Date);
                    }
                }

                $pledge = new Pledge();
                $pledge
                    ->setFamId($payment->FamilyID)
                    ->setFyId($payment->FYID)
                    ->setDate($payment->Date)
                    ->setAmount($Fund->Amount)
                    ->setMethod($payment->iMethod)
                    ->setComment($Fund->Comment)
                    ->setDateLastEdited(date('YmdHis'))
                    ->setEditedBy(AuthenticationManager::getCurrentUser()->getId())
                    ->setPledgeOrPayment($payment->type)
                    ->setFundId($Fund->FundID)
                    ->setDepId($payment->DepositID)
                    ->setGroupKey($sGroupKey);
                if ($payment->schedule) {
                    $pledge->setSchedule($payment->schedule);
                }
                if ($payment->iCheckNo) {
                    $pledge->setCheckNo($payment->iCheckNo);
                }
                if ($payment->tScanString) {
                    $pledge->setScanString($payment->tScanString);
                }
                if ($payment->iAutID) {
                    $pledge->setAutId($payment->iAutID);
                }
                if ($Fund->NonDeductible) {
                    $pledge->setNondeductible($Fund->NonDeductible);
                }
                $pledge->save();
                return $sGroupKey;
            }
        }
    }

    public function submitPledgeOrPayment(object $payment): string
    {
        AuthService::requireUserGroupMembership('bFinance');
        $this->validateFund($payment);
        $this->validateChecks($payment);
        $this->validateDate($payment);
        $groupKey = $this->insertPledgeorPayment($payment);

        return $this->getPledgeorPayment($groupKey);
    }

    public function getPledgeorPayment(string $GroupKey): string
    {
        AuthService::requireUserGroupMembership('bFinance');
        $total = 0;
        $sSQL = 'SELECT plg_plgID, plg_FamID, plg_date, plg_fundID, plg_amount, plg_NonDeductible,plg_comment, plg_FYID, plg_method, plg_EditedBy from pledge_plg where plg_GroupKey="' . $GroupKey . '"';
        $rsKeys = Functions::runQuery($sSQL);
        $payment = new \stdClass();
        $payment->funds = [];
        while ($aRow = mysqli_fetch_array($rsKeys)) {
            extract($aRow);
            $family = FamilyQuery::create()->findOneById($plg_FamID);
            $payment->Family = $family->getFamilyString();
            $payment->Date = $plg_date;
            $payment->FYID = $plg_FYID;
            $payment->iMethod = $plg_method;
            $fund['FundID'] = $plg_fundID;
            $fund['Amount'] = $plg_amount;
            $fund['NonDeductible'] = $plg_NonDeductible;
            $fund['Comment'] = $plg_comment;
            $payment->funds[] = $fund;
            $total += $plg_amount;
            $onePlgID = $aRow['plg_plgID'];
            $oneFundID = $aRow['plg_fundID'];
            $iOriginalSelectedFund = $oneFundID; // remember the original fund in case we switch to splitting
            $fund2PlgIds[$oneFundID] = $onePlgID;
        }
        $payment->total = $total;

        return json_encode($payment, JSON_THROW_ON_ERROR);
    }

    public function getDepositPDF($depID): void
    {
    }

    public function getDepositCSV(string $depID): \stdClass
    {
        AuthService::requireUserGroupMembership('bFinance');
        $retstring = '';
        $line = [];
        $payments = $this->getPayments($depID);
        if (count($payments) === 0) {
            throw new \Exception('No Payments on this Deposit', 404);
        }
        foreach ($payments[0] as $key => $value) {
            $line[] = $key;
        }
        $retstring = implode(',', $line) . "\n";
        foreach ($payments as $payment) {
            $line = [];
            foreach ($payment as $value) {
                $line[] = str_replace(',', '', $value);
            }
            $retstring .= implode(',', $line) . "\n";
        }

        $CSVReturn = new \stdClass();
        $CSVReturn->content = $retstring;
        // Export file
        $CSVReturn->header = 'Content-Disposition: attachment; filename=ChurchCRM-DepositCSV-' . $depID . '-' . date(SystemConfig::getValue('sDateFilenameFormat')) . '.csv';

        return $CSVReturn;
    }

    public function getCurrencyTypeOnDeposit(string $currencyID, string $depositID)
    {
        // Get the list of Currency denominations
        $sSQL = 'select sum(pdem_denominationQuantity) from pledge_denominations_pdem
                 where  plg_depID = ' . $depositID . '
                 AND
                 pdem_denominationID = ' . $currencyID;
        $rscurrencyDenomination = Functions::runQuery($sSQL);

        return mysqli_fetch_array($rscurrencyDenomination)[0];
    }

    /**
     * @return \stdClass[]
     */
    public function getCurrency(): array
    {
        $currencies = [];
        // Get the list of Currency denominations
        $sSQL = 'SELECT * FROM currency_denominations_cdem';
        $rscurrencyDenomination = Functions::runQuery($sSQL);
        mysqli_data_seek($rscurrencyDenomination, 0);
        while ($row = mysqli_fetch_array($rscurrencyDenomination)) {
            $currency = new \stdClass();
            $currency->id = $row['cdem_denominationID'];
            $currency->Name = $row['cdem_denominationName'];
            $currency->Value = $row['cdem_denominationValue'];
            $currency->cClass = $row['cdem_denominationClass'];
            $currencies[] = $currency;
        } // end while

        return $currencies;
    }

    /**
     * @return \stdClass[]
     */
    public function getActiveFunds(): array
    {
        AuthService::requireUserGroupMembership('bFinance');
        $funds = [];
        $sSQL = 'SELECT fun_ID,fun_Name,fun_Description,fun_Active FROM donationfund_fun';
        $sSQL .= " WHERE fun_Active = 'true'"; // New donations should show only active funds.
        $rsFunds = Functions::runQuery($sSQL);
        mysqli_data_seek($rsFunds, 0);
        while ($aRow = mysqli_fetch_array($rsFunds)) {
            $fund = new \stdClass();
            $fund->ID = $aRow['fun_ID'];
            $fund->Name = $aRow['fun_Name'];
            $fund->Description = $aRow['fun_Description'];
            $funds[] = $fund;
        } // end while

        return $funds;
    }

    /**
     * Format a fiscal year ID into a human-readable string.
     *
     * @param int $fyId Fiscal year ID
     * @return string Formatted fiscal year (e.g., "2024" or "2023/24")
     */
    public static function formatFiscalYear(int $fyId): string
    {
        if (SystemConfig::getValue('iFYMonth') === 1) {
            return (string) (1996 + $fyId);
        } else {
            return (1995 + $fyId) . '/' . mb_substr(1996 + $fyId, 2, 2);
        }
    }

    /**
     * Get Advanced Deposit Report data using ORM
     * 
     * Security: Checks finance permissions before returning data
     *
     * @param string $sort Sort order: 'deposit', 'fund', or 'family'
     * @param string $dateStart Start date (Y-m-d format)
     * @param string $dateEnd End date (Y-m-d format)
     * @param int|null $depositId Optional deposit ID filter
     * @param array $fundIds Optional fund IDs filter
     * @param array $familyIds Optional family IDs filter
     * @param array $methods Optional payment methods filter
     * @param array $classificationIds Optional classification IDs filter
     * @return array
     */
    public function getAdvancedDepositReportData(
        string $sort = 'deposit',
        string $dateStart = '',
        string $dateEnd = '',
        ?int $depositId = null,
        array $fundIds = [],
        array $familyIds = [],
        array $methods = [],
        array $classificationIds = [],
        string $datetype = 'Payment'
    ): array {
        AuthService::requireUserGroupMembership('bFinance');

        $query = PledgeQuery::create()->filterForAdvancedDeposit(
            $dateStart,
            $dateEnd,
            $fundIds,
            $familyIds,
            $methods,
            $classificationIds,
            $datetype,
            $sort
        );

        // Add deposit ID filter if specified
        if ($depositId > 0) {
            $query->filterByDepId($depositId);
        }

        // Get results and convert to array with foreign objects included
        $collection = $query->find();
        $results = [];
        foreach ($collection as $pledge) {
            $results[] = $pledge->toArray(TableMap::TYPE_PHPNAME, true, [], true);
        }
        return $results;
    }

    /**
     * Get Tax Report (Giving Report) data using ORM
     * 
     * Security: Checks finance permissions before returning data
     *
     * @param string $dateStart Start date (Y-m-d format)
     * @param string $dateEnd End date (Y-m-d format)
     * @param int|null $depositId Optional deposit ID filter
     * @param int|null $minimumAmount Optional minimum amount filter
     * @param array $fundIds Optional fund IDs filter
     * @param array $familyIds Optional family IDs filter
     * @param array $classificationIds Optional classification IDs filter
     * @return array
     */
    public function getTaxReportData(
        string $dateStart = '',
        string $dateEnd = '',
        ?int $depositId = null,
        ?int $minimumAmount = null,
        array $fundIds = [],
        array $familyIds = [],
        array $classificationIds = []
    ): array {
        AuthService::requireUserGroupMembership('bFinance');

        $query = PledgeQuery::create()->filterForTaxReport(
            $dateStart,
            $dateEnd,
            $fundIds,
            $familyIds,
            $classificationIds
        );

        // Add optional filters
        if ($depositId > 0) {
            $query->filterByDepId($depositId);
        }
        if ($minimumAmount > 0) {
            $query->filterByAmount($minimumAmount, Criteria::GREATER_EQUAL);
        }

        // Get results and convert to array with foreign objects included
        $collection = $query->find();
        $results = [];
        foreach ($collection as $pledge) {
            $results[] = $pledge->toArray(\Propel\Runtime\Map\TableMap::TYPE_PHPNAME, true, [], true);
        }
        return $results;
    }

    /**
     * Get Zero Givers Report data using ORM
     * 
     * Security: Checks finance permissions before returning data
     * Returns families with members (classification 1) who didn't give in the date range
     *
     * @param string $dateStart Start date (Y-m-d format)
     * @param string $dateEnd End date (Y-m-d format)
     * @return array
     */
    public function getZeroGiversReportData(string $dateStart = '', string $dateEnd = ''): array
    {
        AuthService::requireUserGroupMembership('bFinance');

        // Get all families with at least one member (classification ID 1)
        $familyQuery = FamilyQuery::create()
            ->usePersonQuery()
                ->filterByClsId(1)
            ->endUse();

        // Get family IDs that made payments in the date range
        $paidFamilyIds = PledgeQuery::create()
            ->filterForZeroGivers($dateStart, $dateEnd)
            ->select(['FamId'])
            ->distinct()
            ->find()
            ->toArray();

        // Flatten the array of arrays to just IDs
        $paidFamilyIds = array_map(function($row) {
            return is_array($row) ? $row[0] : $row;
        }, $paidFamilyIds);

        // Exclude families that made payments
        if (!empty($paidFamilyIds)) {
            $familyQuery->filterById($paidFamilyIds, Criteria::NOT_IN);
        }

        return $familyQuery
            ->orderById()
            ->find()
            ->toArray();
    }

    // =========================================================================
    // Dashboard Methods
    // =========================================================================

    /**
     * Calculate fiscal year date range based on system configuration.
     *
     * @return array{startDate: string, endDate: string, label: string, month: int}
     */
    public function getFiscalYearDates(): array
    {
        $iFYMonth = (int) SystemConfig::getValue('iFYMonth');
        $currentYear = (int) date('Y');
        $currentMonth = (int) date('n');

        if ($iFYMonth === 1) {
            // Calendar year fiscal year
            $fyStartDate = $currentYear . '-01-01';
            $fyEndDate = $currentYear . '-12-31';
            $fyLabel = (string) $currentYear;
        } else {
            // Non-calendar fiscal year
            if ($currentMonth >= $iFYMonth) {
                $fyStartYear = $currentYear;
                $fyEndYear = $currentYear + 1;
            } else {
                $fyStartYear = $currentYear - 1;
                $fyEndYear = $currentYear;
            }
            $fyStartDate = $fyStartYear . '-' . str_pad($iFYMonth, 2, '0', STR_PAD_LEFT) . '-01';
            // Calculate end date (last day of month before fiscal year month)
            $endMonth = $iFYMonth - 1;
            if ($endMonth === 0) {
                $endMonth = 12;
            }
            $fyEndDate = $fyEndYear . '-' . str_pad($endMonth, 2, '0', STR_PAD_LEFT) . '-' . date('t', strtotime($fyEndYear . '-' . $endMonth . '-01'));
            $fyLabel = $fyStartYear . '/' . substr((string) $fyEndYear, 2, 2);
        }

        return [
            'startDate' => $fyStartDate,
            'endDate' => $fyEndDate,
            'label' => $fyLabel,
            'month' => $iFYMonth,
        ];
    }

    /**
     * Get deposit statistics (total, open, closed counts).
     *
     * @return array{total: int, open: int, closed: int}
     */
    public function getDepositStatistics(): array
    {
        return [
            'total' => DepositQuery::create()->count(),
            'open' => DepositQuery::create()->filterByClosed(false)->count(),
            'closed' => DepositQuery::create()->filterByClosed(true)->count(),
        ];
    }

    /**
     * Get recent deposits within the current fiscal year.
     *
     * @param int $limit Maximum number of deposits to return
     * @param string|null $fyStartDate Optional fiscal year start date filter
     * @return ObjectCollection Collection of Deposit objects
     */
    public function getRecentDeposits(int $limit = 5, ?string $fyStartDate = null): ObjectCollection
    {
        $query = DepositQuery::create()
            ->orderByDate(Criteria::DESC)
            ->limit($limit);

        // Filter to only show deposits from current fiscal year
        if ($fyStartDate !== null) {
            $query->filterByDate($fyStartDate, Criteria::GREATER_EQUAL);
        }

        return $query->find();
    }

    /**
     * Get active donation funds.
     *
     * @return ObjectCollection Collection of DonationFund objects
     */
    public function getActiveDonationFunds(): ObjectCollection
    {
        return DonationFundQuery::create()
            ->filterByActive('true')
            ->orderByName()
            ->find();
    }

    /**
     * Get total count of donation funds.
     *
     * @return int
     */
    public function getTotalFundCount(): int
    {
        return DonationFundQuery::create()->count();
    }

    /**
     * Get Year-to-Date payment total for a fiscal year.
     *
     * @param string $fyStartDate Fiscal year start date
     * @param string $fyEndDate Fiscal year end date
     * @return float|null
     */
    public function getYtdPaymentTotal(string $fyStartDate, string $fyEndDate): ?float
    {
        return PledgeQuery::create()
            ->filterByPledgeOrPayment('Payment')
            ->filterByDate(['min' => $fyStartDate, 'max' => $fyEndDate])
            ->withColumn('SUM(plg_amount)', 'TotalAmount')
            ->select(['TotalAmount'])
            ->findOne();
    }

    /**
     * Get Year-to-Date pledge total for a fiscal year.
     *
     * @param string $fyStartDate Fiscal year start date
     * @param string $fyEndDate Fiscal year end date
     * @return float|null
     */
    public function getYtdPledgeTotal(string $fyStartDate, string $fyEndDate): ?float
    {
        return PledgeQuery::create()
            ->filterByPledgeOrPayment('Pledge')
            ->filterByDate(['min' => $fyStartDate, 'max' => $fyEndDate])
            ->withColumn('SUM(plg_amount)', 'TotalAmount')
            ->select(['TotalAmount'])
            ->findOne();
    }

    /**
     * Get Year-to-Date payment count for a fiscal year.
     *
     * @param string $fyStartDate Fiscal year start date
     * @param string $fyEndDate Fiscal year end date
     * @return int
     */
    public function getYtdPaymentCount(string $fyStartDate, string $fyEndDate): int
    {
        return PledgeQuery::create()
            ->filterByPledgeOrPayment('Payment')
            ->filterByDate(['min' => $fyStartDate, 'max' => $fyEndDate])
            ->count();
    }

    /**
     * Get count of unique donor families for a fiscal year.
     *
     * @param string $fyStartDate Fiscal year start date
     * @param string $fyEndDate Fiscal year end date
     * @return int|null
     */
    public function getYtdDonorFamilyCount(string $fyStartDate, string $fyEndDate): ?int
    {
        return PledgeQuery::create()
            ->filterByPledgeOrPayment('Payment')
            ->filterByDate(['min' => $fyStartDate, 'max' => $fyEndDate])
            ->withColumn('COUNT(DISTINCT plg_FamID)', 'FamilyCount')
            ->select(['FamilyCount'])
            ->findOne();
    }

    /**
     * Get current deposit from session.
     *
     * @return Deposit|null
     */
    public function getCurrentDeposit(): ?Deposit
    {
        $currentDepositId = $_SESSION['iCurrentDeposit'] ?? null;
        if ($currentDepositId) {
            return DepositQuery::create()->findOneById((int) $currentDepositId);
        }
        return null;
    }

    /**
     * Get current deposit ID from session.
     *
     * @return int|null
     */
    public function getCurrentDepositId(): ?int
    {
        return $_SESSION['iCurrentDeposit'] ?? null;
    }

    /**
     * Get all dashboard data in a single call.
     * 
     * This method consolidates all the dashboard queries into a single 
     * service call to simplify the view layer.
     *
     * @return array Dashboard data including fiscal year info, statistics, and deposits
     */
    public function getDashboardData(): array
    {
        $fiscalYear = $this->getFiscalYearDates();
        $depositStats = $this->getDepositStatistics();
        $currentDeposit = $this->getCurrentDeposit();

        return [
            'fiscalYear' => $fiscalYear,
            'depositStats' => $depositStats,
            'recentDeposits' => $this->getRecentDeposits(5, $fiscalYear['startDate']),
            'activeFunds' => $this->getActiveDonationFunds(),
            'activeFundCount' => $this->getActiveDonationFunds()->count(),
            'totalFundCount' => $this->getTotalFundCount(),
            'ytdPaymentTotal' => $this->getYtdPaymentTotal($fiscalYear['startDate'], $fiscalYear['endDate']),
            'ytdPledgeTotal' => $this->getYtdPledgeTotal($fiscalYear['startDate'], $fiscalYear['endDate']),
            'ytdPaymentCount' => $this->getYtdPaymentCount($fiscalYear['startDate'], $fiscalYear['endDate']),
            'ytdDonorFamilies' => $this->getYtdDonorFamilyCount($fiscalYear['startDate'], $fiscalYear['endDate']),
            'currentDeposit' => $currentDeposit,
            'currentDepositId' => $this->getCurrentDepositId(),
        ];
    }
}
