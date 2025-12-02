<?php
namespace ChurchCRM\Service;

use ChurchCRM\model\ChurchCRM\Deposit;
use ChurchCRM\Service\AuthService;
use ChurchCRM\Utils\Functions;

class DepositService {
    /**
     * @return \stdClass[]
     */
    public function getPayments($depID = null): array
    {
        AuthService::requireUserGroupMembership('bFinance');
        $query = \ChurchCRM\model\ChurchCRM\PledgeQuery::create()
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
            $values->FiscalYear = \MakeFYString($pledge->getFyId() ? (int) $pledge->getFyId() : null);
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
            $deposit = \ChurchCRM\model\ChurchCRM\DepositQuery::create()->findOneById($iDepositSlipID);
            $deposit
                ->setDate($depositDate)
                ->setComment($depositComment)
                ->setEnteredby(\ChurchCRM\Authentication\AuthenticationManager::getCurrentUser()->getId())
                ->setClosed(intval($depositClosed));
            $deposit->save();
            if ($depositClosed && ($depositType === 'CreditCard' || $depositType === 'BankDraft')) {
                // Delete any failed transactions on this deposit slip now that it is closing
                $q = 'DELETE FROM pledge_plg WHERE plg_depID = ' . $iDepositSlipID . ' AND plg_PledgeOrPayment="Payment" AND plg_aut_Cleared=0';
                Functions::runQuery($q);
            }
        } else {
            $deposit = new \ChurchCRM\model\ChurchCRM\Deposit();
            $deposit
                ->setDate($depositDate)
                ->setComment($depositComment)
                ->setEnteredby(\ChurchCRM\Authentication\AuthenticationManager::getCurrentUser()->getId())
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
        $query = \ChurchCRM\model\ChurchCRM\PledgeQuery::create()
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
        $CSVReturn->header = 'Content-Disposition: attachment; filename=ChurchCRM-DepositCSV-' . $depID . '-' . date(\ChurchCRM\dto\SystemConfig::getValue('sDateFilenameFormat')) . '.csv';

        return $CSVReturn;
    }

    public function getViewURI(string $Id): string
    {
        return \ChurchCRM\dto\SystemURLs::getRootPath() . '/DepositSlipEditor.php?DepositSlipID=' . $Id;
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
        
        $items = \ChurchCRM\model\ChurchCRM\PledgeQuery::create()
            ->filterByDepId($depositId)
            ->filterByPledgeOrPayment($type)
            ->groupByGroupKey()
            ->withColumn('SUM(Pledge.Amount)', 'sumAmount')
            ->withColumn('GROUP_CONCAT(DonationFund.Name SEPARATOR \', \')', 'FundName')
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
        $deposit->setComment(htmlspecialchars($depositComment, ENT_QUOTES, 'UTF-8'));
        $deposit->setDate($depositDate);
        $deposit->save();
        return $deposit;
    }
}
