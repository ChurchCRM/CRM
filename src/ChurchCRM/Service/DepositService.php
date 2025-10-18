<?php
namespace ChurchCRM\Service;

use ChurchCRM\model\ChurchCRM\Deposit;

class DepositService {
    /**
     * @return \stdClass[]
     */
    public function getPayments($depID = null): array
    {
        requireUserGroupMembership('bFinance');
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
                \RunQuery($q);
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
        requireUserGroupMembership('bFinance');
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
        requireUserGroupMembership('bFinance');
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
