<?php
namespace ChurchCRM\Service;

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\model\ChurchCRM\Deposit;
use ChurchCRM\model\ChurchCRM\DepositQuery;
use ChurchCRM\model\ChurchCRM\Map\DonationFundTableMap;
use ChurchCRM\model\ChurchCRM\Map\PledgeTableMap;
use ChurchCRM\model\ChurchCRM\PersonQuery;
use ChurchCRM\model\ChurchCRM\PledgeQuery;
use ChurchCRM\Service\AuthService;
use ChurchCRM\Service\FinancialService;
use ChurchCRM\Utils\CsvExporter;
use ChurchCRM\Utils\FunctionsUtils;
use ChurchCRM\Utils\InputUtils;
use Propel\Runtime\ActiveQuery\Criteria;

class DepositService {
    /**
     * Column headers for deposit payment CSV exports.
     * Used by both the bulk and per-deposit CSV endpoints.
     * Extracted here so all callers stay in sync automatically.
     */
    public const EXPORT_HEADERS = [
        'Deposit ID',
        'Pledge ID',
        'Family Name',
        'Date',
        'Amount',
        'Fund',
        'Method',
        'Check No',
        'Comment',
        'Fiscal Year',
    ];
    /**
     * @return \stdClass[]
     */
    public function getPayments($depID = null): array
    {
        AuthService::requireUserGroupMembership('bFinance');
        $query = PledgeQuery::create()
            ->joinWithDonationFund()
            ->joinWithFamily();
        if ($depID) {
            $query->filterByDepId($depID);
        }
        $pledges = $query->find();
        $payments = [];
        foreach ($pledges as $pledge) {
            $family = $pledge->getFamily();
            $donationFund = $pledge->getDonationFund();
            $values = new \stdClass();
            $values->plg_plgID = $pledge->getId();
            $values->plg_FamID = $pledge->getFamId();
            $values->familyString = $family ? $family->getFamilyString() : '';
            $values->plg_FYID = $pledge->getFyId();
            $values->FiscalYear = $pledge->getFyId() ? FinancialService::formatFiscalYear((int) $pledge->getFyId()) : '';
            $values->plg_date = $pledge->getDate();
            $values->plg_amount = $pledge->getAmount();
            $values->plg_schedule = $pledge->getSchedule();
            $values->plg_method = $pledge->getMethod();
            $values->plg_comment = $pledge->getComment();
            $values->plg_DateLastEdited = $pledge->getDateLastEdited();
            $values->plg_EditedBy = $pledge->getEditedBy();
            $values->plg_PledgeOrPayment = $pledge->getPledgeOrPayment();
            $values->plg_fundID = $pledge->getFundId();
            $values->fun_Name = $donationFund ? $donationFund->getName() : '';
            $values->plg_depID = $pledge->getDepId();
            $values->plg_CheckNo = $pledge->getCheckNo();
            $values->plg_Problem = $pledge->getProblem();
            $values->plg_scanString = $pledge->getScanString();
            $values->plg_aut_ID = $pledge->getAutId();
            $values->plg_aut_Cleared = $pledge->getAutCleared();
            $values->plg_aut_ResultID = $pledge->getAutResultId();
            $values->plg_NonDeductible = $pledge->getNondeductible();
            $values->plg_GroupKey = $pledge->getGroupKey();
            $payments[] = $values;
        }
        return $payments;
    }
    public function setDeposit(string $depositType, string $depositComment, string $depositDate, $iDepositSlipID = null, $depositClosed = false): void
    {
        if ($iDepositSlipID) {
            $deposit = DepositQuery::create()->findOneById($iDepositSlipID);
            $deposit
                ->setDate($depositDate)
                ->setComment($depositComment)
                ->setEnteredby(AuthenticationManager::getCurrentUser()->getId())
                ->setClosed(intval($depositClosed));
            $deposit->save();
            if ($depositClosed && ($depositType === 'CreditCard' || $depositType === 'BankDraft')) {
                // Delete any failed transactions on this deposit slip now that it is closing
                $q = 'DELETE FROM pledge_plg WHERE plg_depID = ' . $iDepositSlipID . ' AND plg_PledgeOrPayment="Payment" AND plg_aut_Cleared=0';
                FunctionsUtils::runQuery($q);
            }
        } else {
            $deposit = new Deposit();
            $deposit
                ->setDate($depositDate)
                ->setComment($depositComment)
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
        $query = PledgeQuery::create()
            ->filterByDepId($id)
            ->filterByPledgeOrPayment('Payment');
        if ($type) {
            $query->filterByMethod($type);
        }
        $total = 0;
        foreach ($query->find() as $pledge) {
            $total += $pledge->getAmount();
        }
        return $total;
    }

    public function getDepositPDF($depID): void
    {
    }

    public function getViewURI(string $Id): string
    {
        return SystemURLs::getRootPath() . '/DepositSlipEditor.php?DepositSlipID=' . $Id;
    }

    /**
     * Get pledges or payments from a deposit, grouped by GroupKey
     * @param int $depositId The deposit ID
     * @param string $type Must be exactly 'Pledge' or 'Payment' (case-sensitive)
     * @return array Array of pledge/payment records with FamilyString and FundName populated
     */
    public function getDepositItemsByType(int $depositId, string $type): array
    {
        AuthService::requireUserGroupMembership('bFinance');
        if (!in_array($type, ['Pledge', 'Payment'], true)) {
            throw new \InvalidArgumentException("Type must be 'Pledge' or 'Payment'");
        }
        
        $items = PledgeQuery::create()
            ->filterByDepId($depositId)
            ->filterByPledgeOrPayment($type)
            ->groupByGroupKey()
            ->addSelfSelectColumns()   // pin Pledge columns first so leftJoinWithFamily() offsets are correct
            ->addAsColumn('sumAmount', 'SUM(' . PledgeTableMap::COL_PLG_AMOUNT . ')')
            ->addAsColumn('FundName', 'GROUP_CONCAT(' . DonationFundTableMap::COL_FUN_NAME . " SEPARATOR ', ')")
            ->joinDonationFund()
            ->leftJoinWithFamily()
            ->orderBy('GroupKey', 'ASC')
            ->find();

        // Propel's ObjectCollection::toArray() doesn't call individual model's toArray(),
        // so we iterate to ensure each Pledge's custom toArray() executes (which populates FamilyString)
        return array_map(fn($pledge) => $pledge->toArray(), iterator_to_array($items));
    }

    public function createDeposit(string $depositType, string $depositComment, string $depositDate): Deposit
    {
        $deposit = new Deposit();
        $deposit->setType($depositType);
        $deposit->setComment(InputUtils::sanitizeAndEscapeText($depositComment));
        $deposit->setDate($depositDate);
        $deposit->save();
        return $deposit;
    }

    /**
     * Search deposits with optional filters.
     *
     * Supported filters (all optional, pass '' or null to skip):
     *   dateStart   string  Filter deposits on or after this date (Y-m-d)
     *   dateEnd     string  Filter deposits on or before this date (Y-m-d)
     *   depositId   int     Filter by exact deposit ID
     *   closed      string  '0' = open, '1' = closed, '' = all
     *   enteredBy   int     Filter by teller (dep_EnteredBy person ID)
     *   fundId      int     Filter by donation fund (deposits with at least one pledge in that fund)
     *   amountMin   float   Filter by minimum total deposit amount
     *   amountMax   float   Filter by maximum total deposit amount
     *
     * Returns an array of deposit row arrays, each including 'totalAmount' and 'tellerName'.
     *
     * @param array $filters
     * @return array
     */
    public function searchDeposits(array $filters = []): array
    {
        AuthService::requireUserGroupMembership('bFinance');

        // DepositQuery::preSelect() automatically joins Pledge, groups by Deposit.Id,
        // and adds the totalAmount virtual column.
        $query = DepositQuery::create();

        if (!empty($filters['dateStart'])) {
            $query->filterByDate(['min' => $filters['dateStart']]);
        }
        if (!empty($filters['dateEnd'])) {
            $query->filterByDate(['max' => $filters['dateEnd']]);
        }
        if (!empty($filters['depositId'])) {
            $query->filterById((int) $filters['depositId']);
        }
        if (isset($filters['closed']) && $filters['closed'] !== '') {
            $query->filterByClosed((bool) ((int) $filters['closed']));
        }
        if (!empty($filters['enteredBy'])) {
            $query->filterByEnteredby((int) $filters['enteredBy']);
        }
        if (!empty($filters['fundId'])) {
            // preSelect already INNER JOINs pledge_plg; add a WHERE on the fund
            // column directly to avoid usePledgeQuery() conflicting with preSelect.
            $query->where('pledge_plg.plg_fundID = ' . (int) $filters['fundId']);
        }
        $deposits = $query->orderByDate(Criteria::DESC)->find();

        // Collect unique teller IDs for a single bulk person lookup
        $tellerIds = [];
        $depositData = [];
        foreach ($deposits as $deposit) {
            $arr = $deposit->toArray();
            $arr['totalAmount'] = $deposit->getTotalAmount();
            $enteredBy = (int) $deposit->getEnteredby();
            if ($enteredBy > 0) {
                $tellerIds[] = $enteredBy;
            }
            $depositData[] = ['deposit' => $deposit, 'arr' => $arr, 'enteredBy' => $enteredBy];
        }

        // Bulk-load teller names to avoid N+1 queries
        $tellerMap = [];
        $uniqueTellerIds = array_unique(array_filter($tellerIds));
        if (!empty($uniqueTellerIds)) {
            $persons = PersonQuery::create()->filterById($uniqueTellerIds)->find();
            foreach ($persons as $person) {
                $tellerMap[(int) $person->getId()] = trim($person->getFirstName() . ' ' . $person->getLastName());
            }
        }

        $amountMin = isset($filters['amountMin']) && $filters['amountMin'] !== '' ? (float) $filters['amountMin'] : null;
        $amountMax = isset($filters['amountMax']) && $filters['amountMax'] !== '' ? (float) $filters['amountMax'] : null;

        $result = [];
        foreach ($depositData as $item) {
            $arr = $item['arr'];
            $total = (float) ($arr['totalAmount'] ?? 0);
            // PHP-side amount range filter (Propel HAVING clauses on virtual columns
            // are unreliable with grouped queries — see FinancePaymentSearchResultProvider.php)
            if ($amountMin !== null && $total < $amountMin) {
                continue;
            }
            if ($amountMax !== null && $total > $amountMax) {
                continue;
            }
            $arr['tellerName'] = $tellerMap[$item['enteredBy']] ?? '';
            $result[] = $arr;
        }

        return $result;
    }

    /**
     * Get all payment rows for the given deposit IDs, normalized for export
     * (CSV, OFX, PDF).  Only Payment records (not Pledges) are included.
     *
     * IDs are sanitised to positive integers; invalid/zero IDs are silently
     * discarded.  Returns an empty array when no matching payments exist.
     *
     * @param int[] $ids  Deposit IDs to export
     * @return array[]    Rows keyed by field name (depositId, pledgeId, …)
     */
    public function getDepositsForExport(array $ids): array
    {
        AuthService::requireUserGroupMembership('bFinance');

        // Sanitise / whitelist IDs to positive integers
        $safeIds = array_values(array_filter(
            array_map('intval', $ids),
            fn(int $id): bool => $id > 0
        ));

        if (empty($safeIds)) {
            return [];
        }

        $pledges = PledgeQuery::create()
            ->filterByDepId($safeIds)
            ->filterByPledgeOrPayment('Payment')
            ->joinWithDonationFund()
            ->leftJoinWithFamily()
            ->orderByDepId()
            ->orderByDate()
            ->find();

        $rows = [];
        foreach ($pledges as $pledge) {
            $family     = $pledge->getFamily();
            $fund       = $pledge->getDonationFund();
            $dateVal    = $pledge->getDate();
            $rows[] = [
                'depositId'  => (string) $pledge->getDepId(),
                'pledgeId'   => (string) $pledge->getId(),
                'familyName' => $family ? (string) $family->getName() : '',
                'date'       => $dateVal ? $dateVal->format('Y-m-d') : '',
                'amount'     => (string) $pledge->getAmount(),
                'fundName'   => $fund ? (string) $fund->getName() : '',
                'method'     => (string) $pledge->getMethod(),
                'checkNo'    => (string) $pledge->getCheckNo(),
                'comment'    => (string) $pledge->getComment(),
                'fiscalYear' => $pledge->getFyId()
                    ? FinancialService::formatFiscalYear((int) $pledge->getFyId())
                    : '',
            ];
        }

        return $rows;
    }
}
