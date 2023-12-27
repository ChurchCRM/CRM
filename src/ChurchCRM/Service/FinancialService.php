<?php

namespace ChurchCRM\Service;

$bSuppressSessionTests = true; // DO NOT MOVE
require_once dirname(__DIR__) . '/../Include/Functions.php';

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\MICRFunctions;
use ChurchCRM\model\ChurchCRM\Deposit;
use ChurchCRM\model\ChurchCRM\FamilyQuery;
use ChurchCRM\model\ChurchCRM\Pledge;
use ChurchCRM\model\ChurchCRM\PledgeQuery;

class FinancialService
{
    public function deletePayment($groupKey): void
    {
        requireUserGroupMembership('bFinance');
        PledgeQuery::create()->findOneByGroupKey($groupKey)->delete();
    }

    public function getMemberByScanString($tScanString): array
    {
        requireUserGroupMembership('bFinance');

        if (!SystemConfig::getValue('bUseScannedChecks')) {
            throw new \Exception('Scanned Checks is disabled');
        }

        $micrObj = new MICRFunctions(); // Instantiate the MICR class
        $routeAndAccount = $micrObj->findRouteAndAccount($tScanString); // use routing and account number for matching
        if (!$routeAndAccount) {
            throw new \Exception('error in locating family');
        }
        $sSQL = 'SELECT fam_ID, fam_Name FROM family_fam WHERE fam_scanCheck="' . $routeAndAccount . '"';
        $rsFam = RunQuery($sSQL);
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
            $sSQL = "UPDATE deposit_dep SET dep_Date = '" . $depositDate . "', dep_Comment = '" . $depositComment . "', dep_EnteredBy = " . AuthenticationManager::getCurrentUser()->getId() . ', dep_Closed = ' . intval($depositClosed) . ' WHERE dep_ID = ' . $iDepositSlipID . ';';
            $bGetKeyBack = false;
            if ($depositClosed && ($depositType === 'CreditCard' || $depositType === 'BankDraft')) {
                // Delete any failed transactions on this deposit slip now that it is closing
                $q = 'DELETE FROM pledge_plg WHERE plg_depID = ' . $iDepositSlipID . ' AND plg_PledgeOrPayment="Payment" AND plg_aut_Cleared=0';
                RunQuery($q);
            }
            RunQuery($sSQL);
        } else {
            $deposit = new Deposit();
            $deposit
                ->setDate($depositDate)
                ->setComment($depositComment)
                ->setEnteredby(AuthenticationManager::getCurrentUser()->getId())
                ->setType($depositType);
            $deposit->save();

            $sSQL = 'SELECT MAX(dep_ID) AS iDepositSlipID FROM deposit_dep';
            $rsDepositSlipID = RunQuery($sSQL);
            $iDepositSlipID = mysqli_fetch_array($rsDepositSlipID)[0];
        }
        $_SESSION['iCurrentDeposit'] = $iDepositSlipID;
    }

    public function getDepositTotal($id, $type = null)
    {
        requireUserGroupMembership('bFinance');
        $sqlClause = '';
        if ($type) {
            $sqlClause = "AND plg_method = '" . $type . "'";
        }
        // Get deposit total
        $sSQL = "SELECT SUM(plg_amount) AS deposit_total FROM pledge_plg WHERE plg_depID = '$id' AND plg_PledgeOrPayment = 'Payment' " . $sqlClause;
        $rsDepositTotal = RunQuery($sSQL);
        [$deposit_total] = mysqli_fetch_row($rsDepositTotal);

        return $deposit_total;
    }

    /**
     * @return \stdClass[]
     */
    public function getPayments($depID = null): array
    {
        requireUserGroupMembership('bFinance');
        $sSQL = 'SELECT * from pledge_plg
            INNER JOIN
            donationfund_fun
            ON
            pledge_plg.plg_fundID = donationfund_fun.fun_ID';

        if ($depID) {
            $sSQL .= ' WHERE plg_depID = ' . $depID;
        }
        $rsDep = RunQuery($sSQL);

        $payments = [];
        while ($aRow = mysqli_fetch_array($rsDep)) {
            extract($aRow);
            $family = FamilyQuery::create()->findOneById($plg_FamID);
            $values = new \stdClass();
            $values->plg_plgID = $plg_plgID;
            $values->plg_FamID = $plg_FamID;
            $values->familyString = $family->getFamilyString();
            $values->plg_FYID = $plg_FYID;
            $values->FiscalYear = MakeFYString($plg_FYID);
            $values->plg_date = $plg_date;
            $values->plg_amount = $plg_amount;
            $values->plg_schedule = $plg_schedule;
            $values->plg_method = $plg_method;
            $values->plg_comment = $plg_comment;
            $values->plg_DateLastEdited = $plg_DateLastEdited;
            $values->plg_EditedBy = $plg_EditedBy;
            $values->plg_PledgeOrPayment = $plg_PledgeOrPayment;
            $values->plg_fundID = $plg_fundID;
            $values->fun_Name = $fun_Name;
            $values->plg_depID = $plg_depID;
            $values->plg_CheckNo = $plg_CheckNo;
            $values->plg_Problem = $plg_Problem;
            $values->plg_scanString = $plg_scanString;
            $values->plg_aut_ID = $plg_aut_ID;
            $values->plg_aut_Cleared = $plg_aut_Cleared;
            $values->plg_aut_ResultID = $plg_aut_ResultID;
            $values->plg_NonDeductible = $plg_NonDeductible;
            $values->plg_GroupKey = $plg_GroupKey;

            $payments[] = $values;
        }

        return $payments;
    }

    public function getPaymentViewURI(string $groupKey): string
    {
        return SystemURLs::getRootPath() . '/PledgeEditor.php?GroupKey=' . $groupKey;
    }

    public function getViewURI(string $Id): string
    {
        return SystemURLs::getRootPath() . '/DepositSlipEditor.php?DepositSlipID=' . $Id;
    }

    private function validateDate(array $payment): void
    {
        // Validate Date
        if (strlen($payment->Date) > 0) {
            [$iYear, $iMonth, $iDay] = sscanf($payment->Date, '%04d-%02d-%02d');
            if (!checkdate($iMonth, $iDay, $iYear)) {
                throw new \Exception('Invalid Date');
            }
        }
    }

    private function validateFund(array $payment): void
    {
        //Validate that the fund selection is valid:
        //If a single fund is selected, that fund must exist, and not equal the default "Select a Fund" selection.
        //If a split is selected, at least one fund must be non-zero, the total must add up to the total of all funds, and all funds in the split must be valid funds.
        $FundSplit = $payment['FundSplit'];
        if (count($FundSplit) >= 1 && $FundSplit[0]->FundID != 'None') { // split
            $nonZeroFundAmountEntered = 0;
            foreach ($FundSplit as $fun_id => $fund) {
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
        requireUserGroupMembership('bFinance');
        $sSQL = 'SELECT count(plg_FamID) from pledge_plg
                 WHERE plg_CheckNo = ' . $checkNumber . ' AND
                 plg_FamID = ' . $fam_ID;
        $rCount = RunQuery($sSQL);

        return mysqli_fetch_array($rCount)[0];
    }

    public function validateChecks($payment): void
    {
        requireUserGroupMembership('bFinance');
        //validate that the payment options are valid
        //If the payment method is a check, then the check number must be present, and it must not already have been used for this family
        //if the payment method is cash, there must not be a check number
        if ($payment->type === 'Payment' && $payment->iMethod === 'CHECK' && !isset($payment->iCheckNo)) {
            throw new \Exception(gettext('Must specify non-zero check number'));
        }
        // detect check inconsistencies
        if ($payment->type === 'Payment' && isset($payment->iCheckNo)) {
            if ($payment->iMethod === 'CASH') {
                throw new \Exception(gettext("Check number not valid for 'CASH' payment"));
            } elseif ($payment->iMethod === 'CHECK' && $this->locateFamilyCheck($payment->iCheckNo, $payment->FamilyID)) {
                //build routine to make sure this check number hasn't been used by this family yet (look at group key)
                throw new \Exception("Check number '" . $payment->iCheckNo . "' for selected family already exists.");
            }
        }
    }

    public function processCurrencyDenominations($payment, string $groupKey): void
    {
        $currencyDenoms = json_decode($payment->cashDenominations, null, 512, JSON_THROW_ON_ERROR);
        foreach ($currencyDenoms as $cdom) {
            $sSQL = "INSERT INTO pledge_denominations_pdem (pdem_plg_GroupKey, plg_depID, pdem_denominationID, pdem_denominationQuantity)
      VALUES ('" . $groupKey . "','" . $payment->DepositID . "','" . $cdom->currencyID . "','" . $cdom->Count . "')";
            if (isset($sSQL)) {
                RunQuery($sSQL);
                unset($sSQL);
            }
        }
    }

    public function insertPledgeorPayment($payment)
    {
        requireUserGroupMembership('bFinance');
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

    public function submitPledgeOrPayment(array $payment): string
    {
        requireUserGroupMembership('bFinance');
        $this->validateFund($payment);
        $this->validateChecks($payment);
        $this->validateDate($payment);
        $groupKey = $this->insertPledgeorPayment($payment);

        return $this->getPledgeorPayment($groupKey);
    }

    public function getPledgeorPayment(string $GroupKey): string
    {
        requireUserGroupMembership('bFinance');
        $total = 0;
        $sSQL = 'SELECT plg_plgID, plg_FamID, plg_date, plg_fundID, plg_amount, plg_NonDeductible,plg_comment, plg_FYID, plg_method, plg_EditedBy from pledge_plg where plg_GroupKey="' . $GroupKey . '"';
        $rsKeys = RunQuery($sSQL);
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

    private function generateBankDepositSlip($thisReport): void
    {
        // --------------------------------
        // BEGIN FRONT OF BANK DEPOSIT SLIP
        $thisReport->pdf->addPage('L', [187, 84]);
        $thisReport->pdf->SetFont('Courier', '', 18);
        // Print Deposit Slip portion of report

        $thisReport->pdf->SetXY($thisReport->date1X, $thisReport->date1Y);
        $thisReport->pdf->Write(8, $thisReport->deposit->dep_Date);

        $thisReport->pdf->SetXY($thisReport->customerName1X, $thisReport->customerName1Y);
        $thisReport->pdf->Write(8, SystemConfig::getValue('sChurchName'));

        $thisReport->pdf->SetXY($thisReport->AccountNumberX, $thisReport->AccountNumberY);
        $thisReport->pdf->Cell(55, 7, SystemConfig::getValue('sChurchChkAcctNum'), 1, 1, 'R');

        if ($thisReport->deposit->totalCash > 0) {
            $totalCashStr = sprintf('%.2f', $thisReport->deposit->totalCash);
            $thisReport->pdf->SetXY($thisReport->cashX, $thisReport->cashY);
            $thisReport->pdf->Cell(46, 7, $totalCashStr, 1, 1, 'R');
        }

        if ($thisReport->deposit->totalChecks > 0) {
            $totalChecksStr = sprintf('%.2f', $thisReport->deposit->totalChecks);
            $thisReport->pdf->SetXY($thisReport->checksX, $thisReport->checksY);
            $thisReport->pdf->Cell(46, 7, $totalChecksStr, 1, 1, 'R');
        }

        $grandTotalStr = sprintf('%.2f', $thisReport->deposit->dep_Total);
        $cashReceivedStr = sprintf('%.2f', 0);

        $thisReport->pdf->SetXY($thisReport->cashReceivedX, $thisReport->cashReceivedY);
        $thisReport->pdf->Cell(46, 7, $cashReceivedStr, 1, 1, 'R');

        $thisReport->pdf->SetXY($thisReport->topTotalX, $thisReport->topTotalY);
        $thisReport->pdf->Cell(46, 7, $grandTotalStr, 1, 1, 'R');

        // --------------------------------
        // BEGIN BACK OF BANK DEPOSIT SLIP

        $thisReport->pdf->addPage('P', [84, 187]);
        $numItems = 0;
        foreach ($thisReport->payments as $payment) {
            // List all the checks and total the cash
            if ($payment->plg_method === 'CHECK') {
                $plgSumStr = sprintf('%.2f', $payment->plg_amount);
                $thisReport->pdf->SetFontSize(14);
                $thisReport->pdf->SetXY($thisReport->depositSlipBackCheckNosX, $thisReport->depositSlipBackCheckNosY + $numItems * $thisReport->depositSlipBackCheckNosHeight);
                $thisReport->pdf->Cell($thisReport->depositSlipBackCheckNosWidth, $thisReport->depositSlipBackCheckNosHeight, $payment->plg_CheckNo, 1, 0, 'L');
                $thisReport->pdf->SetFontSize(18);
                $thisReport->pdf->Cell($thisReport->depositSlipBackDollarsWidth, $thisReport->depositSlipBackDollarsHeight, $plgSumStr, 1, 1, 'R');
                $numItems += 1;
            }
        }
    }

    private function generateDepositSummary($thisReport): void
    {
        $thisReport->depositSummaryParameters = new \stdClass();

        $thisReport->depositSummaryParameters->title = new \stdClass();
        $thisReport->depositSummaryParameters->title->x = 85;
        $thisReport->depositSummaryParameters->title->y = 7;

        $thisReport->depositSummaryParameters->date = new \stdClass();
        $thisReport->depositSummaryParameters->date->x = 185;
        $thisReport->depositSummaryParameters->date->y = 7;

        $thisReport->depositSummaryParameters->summary = new \stdClass();
        $thisReport->depositSummaryParameters->summary->x = 12;
        $thisReport->depositSummaryParameters->summary->y = 15;
        $thisReport->depositSummaryParameters->summary->intervalY = 4;
        $thisReport->depositSummaryParameters->summary->FundX = 15;
        $thisReport->depositSummaryParameters->summary->MethodX = 55;
        $thisReport->depositSummaryParameters->summary->FromX = 80;
        $thisReport->depositSummaryParameters->summary->MemoX = 120;
        $thisReport->depositSummaryParameters->summary->AmountX = 185;

        $thisReport->depositSummaryParameters->aggregateX = 135;
        $thisReport->depositSummaryParameters->displayBillCounts = false;

        $thisReport->pdf->addPage();
        $thisReport->pdf->SetXY($thisReport->depositSummaryParameters->date->x, $thisReport->depositSummaryParameters->date->y);
        $thisReport->pdf->Write(8, $thisReport->deposit->dep_Date);

        $thisReport->pdf->SetXY($thisReport->depositSummaryParameters->title->x, $thisReport->depositSummaryParameters->title->y);
        $thisReport->pdf->SetFont('Courier', 'B', 20);
        $thisReport->pdf->Write(8, 'Deposit Summary ' . $thisReport->deposit->dep_ID);
        $thisReport->pdf->SetFont('Times', 'B', 10);

        $thisReport->curX = $thisReport->depositSummaryParameters->summary->x;
        $thisReport->curY = $thisReport->depositSummaryParameters->summary->y;

        $thisReport->pdf->SetFont('Times', 'B', 10);
        $thisReport->pdf->SetXY($thisReport->curX, $thisReport->curY);
        $thisReport->pdf->Write(8, 'Chk No.');

        $thisReport->pdf->SetXY($thisReport->curX + $thisReport->depositSummaryParameters->summary->FundX, $thisReport->curY);
        $thisReport->pdf->Write(8, 'Fund');

        $thisReport->pdf->SetXY($thisReport->curX + $thisReport->depositSummaryParameters->summary->MethodX, $thisReport->curY);
        $thisReport->pdf->Write(8, 'PmtMethod');

        $thisReport->pdf->SetXY($thisReport->curX + $thisReport->depositSummaryParameters->summary->FromX, $thisReport->curY);
        $thisReport->pdf->Write(8, 'Rcd From');

        $thisReport->pdf->SetXY($thisReport->curX + $thisReport->depositSummaryParameters->summary->MemoX, $thisReport->curY);
        $thisReport->pdf->Write(8, 'Memo');

        $thisReport->pdf->SetXY($thisReport->curX + $thisReport->depositSummaryParameters->summary->AmountX, $thisReport->curY);
        $thisReport->pdf->Write(8, 'Amount');
        $thisReport->curY += 2 * $thisReport->depositSummaryParameters->summary->intervalY;

        $totalAmount = 0;

        //while ($aRow = mysqli_fetch_array($rsPledges))
        foreach ($thisReport->payments as $payment) {
            $thisReport->pdf->SetFont('Times', '', 10);

            // Format Data
            if (strlen($payment->plg_CheckNo) > 8) {
                $payment->plg_CheckNo = '...' . mb_substr($payment->plg_CheckNo, -8, 8);
            }
            if (strlen($payment->fun_Name) > 20) {
                $payment->fun_Name = mb_substr($payment->fun_Name, 0, 20) . '...';
            }
            if (strlen($payment->plg_comment) > 40) {
                $payment->plg_comment = mb_substr($payment->plg_comment, 0, 38) . '...';
            }
            if (strlen($payment->familyName) > 25) {
                $payment->familyName = mb_substr($payment->familyName, 0, 24) . '...';
            }

            $thisReport->pdf->printRightJustified($thisReport->curX + 2, $thisReport->curY, $payment->plg_CheckNo);

            $thisReport->pdf->SetXY($thisReport->curX + $thisReport->depositSummaryParameters->summary->FundX, $thisReport->curY);
            $thisReport->pdf->Write(8, $payment->fun_Name);

            $thisReport->pdf->SetXY($thisReport->curX + $thisReport->depositSummaryParameters->summary->MethodX, $thisReport->curY);
            $thisReport->pdf->Write(8, $payment->plg_method);

            $thisReport->pdf->SetXY($thisReport->curX + $thisReport->depositSummaryParameters->summary->FromX, $thisReport->curY);
            $thisReport->pdf->Write(8, $payment->familyName);

            $thisReport->pdf->SetXY($thisReport->curX + $thisReport->depositSummaryParameters->summary->MemoX, $thisReport->curY);
            $thisReport->pdf->Write(8, $payment->plg_comment);

            $thisReport->pdf->SetFont('Courier', '', 8);

            $thisReport->pdf->printRightJustified($thisReport->curX + $thisReport->depositSummaryParameters->summary->AmountX, $thisReport->curY, $payment->plg_amount);

            $thisReport->curY += $thisReport->depositSummaryParameters->summary->intervalY;

            if ($thisReport->curY >= 250) {
                $thisReport->pdf->addPage();
                $thisReport->curY = $thisReport->topY;
            }
        }

        $thisReport->curY += $thisReport->depositSummaryParameters->summary->intervalY;

        $thisReport->pdf->SetXY($thisReport->curX + $thisReport->depositSummaryParameters->summary->MemoX, $thisReport->curY);
        $thisReport->pdf->Write(8, 'Deposit total');

        $grandTotalStr = sprintf('%.2f', $thisReport->deposit->dep_Total);
        $thisReport->pdf->printRightJustified($thisReport->curX + $thisReport->depositSummaryParameters->summary->AmountX, $thisReport->curY, $grandTotalStr);

        // Now print deposit totals by fund
        $thisReport->curY += 2 * $thisReport->depositSummaryParameters->summary->intervalY;
        if ($thisReport->depositSummaryParameters->displayBillCounts) {
            $this->generateCashDenominations($thisReport);
        }
        $thisReport->curX = $thisReport->depositSummaryParameters->aggregateX;
        $this->generateTotalsByFund($thisReport);

        $thisReport->curY += $thisReport->summaryIntervalY;
        $this->generateTotalsByCurrencyType($thisReport);
        $thisReport->curY += $thisReport->summaryIntervalY * 2;

        $thisReport->curY += 130;
        $thisReport->curX = $thisReport->depositSummaryParameters->summary->x;

        $this->generateWitnessSignature($thisReport);
    }

    private function generateWitnessSignature($thisReport): void
    {
        $thisReport->pdf->setXY($thisReport->curX, $thisReport->curY);
        $thisReport->pdf->write(8, 'Witness 1');
        $thisReport->pdf->line($thisReport->curX + 17, $thisReport->curY + 8, $thisReport->curX + 80, $thisReport->curY + 8);

        $thisReport->curY += 10;
        $thisReport->pdf->setXY($thisReport->curX, $thisReport->curY);
        $thisReport->pdf->write(8, 'Witness 2');
        $thisReport->pdf->line($thisReport->curX + 17, $thisReport->curY + 8, $thisReport->curX + 80, $thisReport->curY + 8);

        $thisReport->curY += 10;
        $thisReport->pdf->setXY($thisReport->curX, $thisReport->curY);
        $thisReport->pdf->write(8, 'Witness 3');
        $thisReport->pdf->line($thisReport->curX + 17, $thisReport->curY + 8, $thisReport->curX + 80, $thisReport->curY + 8);
    }

    public function getDepositPDF($depID): void
    {
    }

    public function getDepositCSV(string $depID): \stdClass
    {
        requireUserGroupMembership('bFinance');
        $retstring = '';
        $line = [];
        $firstLine = true;
        $payments = $this->getPayments($depID);
        if (count($payments) == 0) {
            throw new \Exception('No Payments on this Deposit', 404);
        }
        foreach ($payments[0] as $key => $value) {
            $line[] = $key;
        }
        $retstring = implode(',', $line) . "\n";
        foreach ($payments as $payment) {
            $line = [];
            foreach ($payment as $key => $value) {
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
        $currencies = [];
        // Get the list of Currency denominations
        $sSQL = 'select sum(pdem_denominationQuantity) from pledge_denominations_pdem
                 where  plg_depID = ' . $depositID . '
                 AND
                 pdem_denominationID = ' . $currencyID;
        $rscurrencyDenomination = RunQuery($sSQL);

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
        $rscurrencyDenomination = RunQuery($sSQL);
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
        requireUserGroupMembership('bFinance');
        $funds = [];
        $sSQL = 'SELECT fun_ID,fun_Name,fun_Description,fun_Active FROM donationfund_fun';
        $sSQL .= " WHERE fun_Active = 'true'"; // New donations should show only active funds.
        $rsFunds = RunQuery($sSQL);
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
}
