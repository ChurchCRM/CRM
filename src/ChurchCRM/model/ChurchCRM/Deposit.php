<?php

namespace ChurchCRM\model\ChurchCRM;

use ChurchCRM\dto\SystemConfig;
use ChurchCRM\model\ChurchCRM\Base\Deposit as BaseDeposit;
use ChurchCRM\model\ChurchCRM\Map\DonationFundTableMap;
use ChurchCRM\model\ChurchCRM\Map\PledgeTableMap;
use ChurchCRM\model\ChurchCRM\PledgeQuery as ChildPledgeQuery;
use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\Connection\ConnectionInterface;

/**
 * Skeleton subclass for representing a row from the 'deposit_dep' table.
 *
 *
 *
 * You should add additional methods to this class to meet the
 * application requirements.  This class will only be generated as
 * long as it does not already exist in the output directory.
 */
class Deposit extends BaseDeposit
{
    public function preDelete(ConnectionInterface $con = null): bool
    {
        $this->getPledges()->delete();

        return true;
    }

    public function getOFX(): \stdClass
    {
        $OFXReturn = new \stdClass();
        if ($this->getPledges()->count() == 0) {
            throw new \Exception('No Payments on this Deposit', 404);
        }

        $orgName = 'ChurchCRM Deposit Data';
        $OFXReturn->content = 'OFXHEADER:100' . PHP_EOL .
            'DATA:OFXSGML' . PHP_EOL .
            'VERSION:102' . PHP_EOL .
            'SECURITY:NONE' . PHP_EOL .
            'ENCODING:USASCII' . PHP_EOL .
            'CHARSET:1252' . PHP_EOL .
            'COMPRESSION:NONE' . PHP_EOL .
            'OLDFILEUID:NONE' . PHP_EOL .
            'NEWFILEUID:NONE' . PHP_EOL . PHP_EOL;
        $OFXReturn->content .= '<OFX>';
        $OFXReturn->content .= '<SIGNONMSGSRSV1><SONRS><STATUS><CODE>0<SEVERITY>INFO</STATUS><DTSERVER>' . date('YmdHis.u[O:T]') . '<LANGUAGE>ENG<FI><ORG>' . $orgName . '<FID>12345</FI></SONRS></SIGNONMSGSRSV1>';
        $OFXReturn->content .= '<BANKMSGSRSV1>' .
            '<STMTTRNRS>' .
            '<TRNUID>' .
            '<STATUS>' .
            '<CODE>0' .
            '<SEVERITY>INFO' .
            '</STATUS>';

        foreach ($this->getFundTotals() as $fund) {
            $OFXReturn->content .= '<STMTRS>' .
                '<CURDEF>USD' .
                '<BANKACCTFROM>' .
                '<BANKID>' . $orgName .
                '<ACCTID>' . $fund['Name'] .
                '<ACCTTYPE>SAVINGS' .
                '</BANKACCTFROM>';
            $OFXReturn->content .=
                '<STMTTRN>' .
                '<TRNTYPE>CREDIT' .
                '<DTPOSTED>' . $this->getDate('Ymd') .
                '<TRNAMT>' . $fund['Total'] .
                '<FITID>' .
                '<NAME>' . $this->getComment() .
                '<MEMO>' . $fund['Name'] .
                '</STMTTRN></STMTRS>';
        }

        $OFXReturn->content .= '</STMTTRNRS></BANKTRANLIST></OFX>';
        // Export file
        $OFXReturn->header = 'Content-Disposition: attachment; filename=ChurchCRM-Deposit-' . $this->getId() . '-' . date(SystemConfig::getValue('sDateFilenameFormat')) . '.ofx';

        return $OFXReturn;
    }

    private function generateCashDenominations(\stdClass $thisReport): void
    {
        $thisReport->pdf->SetXY($thisReport->curX, $thisReport->curY);
        $cashDenominations = ['0.01', '0.05', '0.10', '0.25', '0.50', '1.00'];
        $thisReport->pdf->Cell(10, 10, 'Coin', 1, 0, 'L');
        $thisReport->pdf->Cell(20, 10, 'Counts', 1, 0, 'L');
        $thisReport->pdf->Cell(20, 10, 'Totals', 1, 2, 'L');
        $thisReport->pdf->SetX($thisReport->curX);
        foreach ($cashDenominations as $denomination) {
            $thisReport->pdf->Cell(10, 10, $denomination, 1, 0, 'L');
            $thisReport->pdf->Cell(20, 10, '', 1, 0, 'L');
            $thisReport->pdf->Cell(20, 10, '', 1, 2, 'L');
            $thisReport->pdf->SetX($thisReport->curX);
        }
        $thisReport->pdf->Cell(50, 10, 'Total Coin', 1, 2, 'L');

        $thisReport->curX += 70;
        $thisReport->pdf->SetXY($thisReport->curX, $thisReport->curY);

        $cashDenominations = ['$1', '$2', '$5', '$10', '$20', '$50', '$100'];
        $thisReport->pdf->Cell(10, 10, 'Bill', 1, 0, 'L');
        $thisReport->pdf->Cell(20, 10, 'Counts', 1, 0, 'L');
        $thisReport->pdf->Cell(20, 10, 'Totals', 1, 2, 'L');
        $thisReport->pdf->SetX($thisReport->curX);
        foreach ($cashDenominations as $denomination) {
            $thisReport->pdf->Cell(10, 10, $denomination, 1, 0, 'L');
            $thisReport->pdf->Cell(20, 10, '', 1, 0, 'L');
            $thisReport->pdf->Cell(20, 10, '', 1, 2, 'L');
            $thisReport->pdf->SetX($thisReport->curX);
        }
        $thisReport->pdf->Cell(50, 10, 'Total Cash', 1, 2, 'L');
    }

    private function generateTotalsByCurrencyType(\stdClass $thisReport): void
    {
        $thisReport->pdf->SetFont('Times', 'B', 10);
        $thisReport->pdf->SetXY($thisReport->curX, $thisReport->curY);
        $thisReport->pdf->Write(8, 'Deposit totals by Currency Type');
        $thisReport->pdf->SetFont('Courier', '', 8);
        $thisReport->curY += 4;
        $thisReport->pdf->SetXY($thisReport->curX, $thisReport->curY);
        $thisReport->pdf->Write(8, 'Checks: ');
        $thisReport->pdf->write(8, '(' . $this->getCountChecks() . ')');
        $thisReport->pdf->printRightJustified($thisReport->curX + 55, $thisReport->curY, sprintf('%.2f', $this->getTotalChecks()));
        $thisReport->curY += 4;
        $thisReport->pdf->SetXY($thisReport->curX, $thisReport->curY);
        $thisReport->pdf->Write(8, 'Cash: ');
        $thisReport->pdf->printRightJustified($thisReport->curX + 55, $thisReport->curY, sprintf('%.2f', $this->getTotalCash()));
    }

    private function generateTotalsByFund(\stdClass $thisReport): void
    {
        $thisReport->pdf->SetFont('Times', 'B', 10);
        $thisReport->pdf->SetXY($thisReport->curX, $thisReport->curY);
        $thisReport->pdf->Write(8, 'Deposit totals by fund');
        $thisReport->pdf->SetFont('Courier', '', 8);

        $thisReport->curY += 4;

        foreach ($this->getFundTotals() as $fund) { //iterate through the defined funds
            $thisReport->pdf->SetXY($thisReport->curX, $thisReport->curY);
            $thisReport->pdf->Write(8, $fund['Name']);
            $amountStr = sprintf('%.2f', $fund['Total']);
            $thisReport->pdf->printRightJustified($thisReport->curX + 55, $thisReport->curY, $amountStr);
            $thisReport->curY += 4;
        }
    }

    private function generateQBDepositSlip(\stdClass $thisReport): void
    {
        $thisReport->pdf->addPage();

        $thisReport->QBDepositTicketParameters = json_decode(SystemConfig::getValue('sQBDTSettings'), null, 512, JSON_THROW_ON_ERROR);
        $thisReport->pdf->SetXY($thisReport->QBDepositTicketParameters->date1->x, $thisReport->QBDepositTicketParameters->date1->y);
        $thisReport->pdf->Write(8, $this->getDate()->format('Y-m-d'));

        //print_r($thisReport->QBDepositTicketParameters);
        //logically, we print the cash in the first possible key=value pair column
        if ($this->getTotalCash() > 0) {
            $totalCashStr = sprintf('%.2f', $this->getTotalCash());
            $thisReport->pdf->printRightJustified($thisReport->QBDepositTicketParameters->leftX + $thisReport->QBDepositTicketParameters->amountOffsetX, $thisReport->QBDepositTicketParameters->topY, $totalCashStr);
        }
        $thisReport->curX = $thisReport->QBDepositTicketParameters->leftX + $thisReport->QBDepositTicketParameters->lineItemInterval->x;
        $thisReport->curY = $thisReport->QBDepositTicketParameters->topY;

        $pledges = PledgeQuery::create()
            ->filterByDepId($this->getId())
            ->groupByGroupKey()
            ->withColumn('SUM(Pledge.Amount)', 'sumAmount')
            ->joinFamily(null, Criteria::LEFT_JOIN)
            ->withColumn('Family.Name')
            ->find();
        foreach ($pledges as $pledge) {
            // then all of the checks in key-value pairs, in 3 separate columns.  Left to right, then top to bottom.
            if ($pledge->getMethod() === 'CHECK') {
                $thisReport->pdf->printRightJustified($thisReport->curX, $thisReport->curY, $pledge->getCheckNo());
                $thisReport->pdf->printRightJustified($thisReport->curX + $thisReport->QBDepositTicketParameters->amountOffsetX, $thisReport->curY, $pledge->getsumAmount());

                $thisReport->curX += $thisReport->QBDepositTicketParameters->lineItemInterval->x;
                if ($thisReport->curX > $thisReport->QBDepositTicketParameters->max->x) {
                    $thisReport->curX = $thisReport->QBDepositTicketParameters->leftX;
                    $thisReport->curY += $thisReport->QBDepositTicketParameters->lineItemInterval->y;
                }
            }
        }

        $grandTotalStr = sprintf('%.2f', $this->getTotalAmount());
        $thisReport->pdf->printRightJustified($thisReport->QBDepositTicketParameters->subTotal->x, $thisReport->QBDepositTicketParameters->subTotal->y, $grandTotalStr);
        $thisReport->pdf->printRightJustified($thisReport->QBDepositTicketParameters->topTotal->x, $thisReport->QBDepositTicketParameters->topTotal->y, $grandTotalStr);
        $numItemsString = sprintf('%d', ($this->getCountCash() > 0 ? 1 : 0) + $this->getCountChecks());
        $thisReport->pdf->printRightJustified($thisReport->QBDepositTicketParameters->numberOfItems->x, $thisReport->QBDepositTicketParameters->numberOfItems->y, $numItemsString);

        $thisReport->curY = $thisReport->QBDepositTicketParameters->perforationY;
        $thisReport->pdf->SetXY($thisReport->QBDepositTicketParameters->titleX, $thisReport->curY);
        $thisReport->pdf->SetFont('Courier', 'B', 20);
        $thisReport->pdf->Write(8, 'Deposit Summary ' . $this->getId());
        $thisReport->pdf->SetFont('Times', '', 10);
        $thisReport->pdf->SetXY($thisReport->QBDepositTicketParameters->date2X, $thisReport->curY);
        $thisReport->pdf->Write(8, $this->getDate()->format('Y-m-d'));

        $thisReport->curX = $thisReport->QBDepositTicketParameters->date1->x;
        $thisReport->curY += 2 * $thisReport->QBDepositTicketParameters->lineItemInterval->y;

        if (SystemConfig::getBooleanValue('bDisplayBillCounts')) {
            $this->generateCashDenominations($thisReport);
        }

        $thisReport->curX = $thisReport->QBDepositTicketParameters->date1->x + 125;

        $this->generateTotalsByCurrencyType($thisReport);
        $thisReport->curX = $thisReport->QBDepositTicketParameters->date1->x + 125;
        $thisReport->curY = $thisReport->QBDepositTicketParameters->perforationY + 30;
        $this->generateTotalsByFund($thisReport);

        $thisReport->curY += $thisReport->QBDepositTicketParameters->lineItemInterval->y;
        $thisReport->pdf->SetXY($thisReport->curX, $thisReport->curY);
        $thisReport->pdf->SetFont('Times', 'B', 10);
        $thisReport->pdf->Write(8, 'Deposit total');
        $grandTotalStr = sprintf('%.2f', $this->getTotalAmount());
        $thisReport->pdf->printRightJustified($thisReport->curX + 55, $thisReport->curY, $grandTotalStr);
        $thisReport->pdf->SetFont('Courier', '', 8);
    }

    private function generateDepositSummary(\stdClass $thisReport): void
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

        $thisReport->pdf->addPage();
        $thisReport->pdf->SetXY($thisReport->depositSummaryParameters->date->x, $thisReport->depositSummaryParameters->date->y);
        $thisReport->pdf->Write(8, $thisReport->deposit->dep_Date);

        $thisReport->pdf->SetXY($thisReport->depositSummaryParameters->title->x, $thisReport->depositSummaryParameters->title->y);
        $thisReport->pdf->SetFont('Courier', 'B', 20);
        $thisReport->pdf->Write(8, 'Deposit Summary ' . $this->getId());
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
        foreach ($this->getPledges() as $payment) {
            $thisReport->pdf->SetFont('Times', '', 10);

            // Format Data
            $checkNo = $payment->getCheckNo();
            $fundName = DonationFundQuery::create()->findOneById($payment->getFundId())->getName();
            $comment = $payment->getComment();
            //$family = FamilyQuery::create()->findOneById($payment->getFamId());
            $family = $payment->getFamily();
            if ($family !== null) {
                $familyName = $payment->getFamily()->getName();
            } else {
                $familyName = gettext('Anonymous');
            }

            if (strlen($checkNo) > 8) {
                $checkNo = '...' . mb_substr($checkNo, -8, 8);
            }
            if (strlen($fundName) > 20) {
                $fundName = mb_substr($fundName, 0, 20) . '...';
            }
            if (strlen($comment) > 40) {
                $comment = mb_substr($comment, 0, 38) . '...';
            }
            if (strlen($familyName) > 25) {
                $familyName = mb_substr($familyName, 0, 24) . '...';
            }

            $thisReport->pdf->printRightJustified($thisReport->curX + 2, $thisReport->curY, $checkNo);

            $thisReport->pdf->SetXY($thisReport->curX + $thisReport->depositSummaryParameters->summary->FundX, $thisReport->curY);
            $thisReport->pdf->Write(8, $fundName);

            $thisReport->pdf->SetXY($thisReport->curX + $thisReport->depositSummaryParameters->summary->MethodX, $thisReport->curY);
            $thisReport->pdf->Write(8, $payment->getMethod());

            $thisReport->pdf->SetXY($thisReport->curX + $thisReport->depositSummaryParameters->summary->FromX, $thisReport->curY);
            $thisReport->pdf->Write(8, $familyName);

            $thisReport->pdf->SetXY($thisReport->curX + $thisReport->depositSummaryParameters->summary->MemoX, $thisReport->curY);
            $thisReport->pdf->Write(8, $comment);

            $thisReport->pdf->SetFont('Courier', '', 8);

            $thisReport->pdf->printRightJustified($thisReport->curX + $thisReport->depositSummaryParameters->summary->AmountX, $thisReport->curY, $payment->getAmount());

            $thisReport->curY += $thisReport->depositSummaryParameters->summary->intervalY;

            if ($thisReport->curY >= 250) {
                $thisReport->pdf->addPage();
                $thisReport->curY = $thisReport->topY;
            }
        }

        $thisReport->curY += $thisReport->depositSummaryParameters->summary->intervalY;

        $thisReport->pdf->SetXY($thisReport->curX + $thisReport->depositSummaryParameters->summary->MemoX, $thisReport->curY);
        $thisReport->pdf->Write(8, 'Deposit total');

        $grandTotalStr = sprintf('%.2f', $this->getTotalAmount());
        $thisReport->pdf->printRightJustified($thisReport->curX + $thisReport->depositSummaryParameters->summary->AmountX, $thisReport->curY, $grandTotalStr);
        $thisReport->curY += $thisReport->depositSummaryParameters->summary->intervalY * 2;

        // Now print deposit totals by fund
        $thisReport->curY += 2 * $thisReport->depositSummaryParameters->summary->intervalY;
        if (SystemConfig::getBooleanValue('bDisplayBillCounts')) {
            $this->generateCashDenominations($thisReport);
        }

        // Now print deposit totals by fund
        $thisReport->curX = $thisReport->depositSummaryParameters->aggregateX;
        $this->generateTotalsByFund($thisReport);
        $thisReport->curY += $thisReport->depositSummaryParameters->summary->intervalY;

        $this->generateTotalsByCurrencyType($thisReport);
        $thisReport->curY += $thisReport->depositSummaryParameters->summary->intervalY * 2;

        if (!empty($this->getComment())) {
            $thisReport->pdf->SetXY($thisReport->curX, $thisReport->curY);
            $thisReport->pdf->MultiCell(0, $thisReport->depositSummaryParameters->summary->intervalY, gettext('Deposit Comment') . ': ' . $this->getComment(), 0, 'L');
        }
        $thisReport->curY += 130;
        $thisReport->curX = $thisReport->depositSummaryParameters->summary->x;

        $this->generateWitnessSignature($thisReport);
    }

    private function generateWitnessSignature(\stdClass $thisReport): void
    {
        $thisReport->curX = $thisReport->depositSummaryParameters->summary->x;
        $thisReport->curY = $thisReport->pdf->GetPageHeight() - 30;
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

    public function getPDF(): void
    {
        requireUserGroupMembership('bFinance');
        $Report = new \stdClass();
        if (count($this->getPledges()) === 0) {
            throw new \Exception('No Payments on this Deposit', 404);
        }

        $Report->pdf = new \ChurchCRM\Reports\PdfDepositReport();
        $Report->funds = DonationFundQuery::create()->find();

        //in 2.2.0, this setting will be part of the database, but to avoid 2.1.7 schema changes, I'm defining it in code.
        $sDepositSlipType = SystemConfig::getValue('sDepositSlipType');

        if ($sDepositSlipType === 'QBDT') {
            //Generate a QuickBooks Deposit Ticket.
            $this->generateQBDepositSlip($Report);
        } elseif ($sDepositSlipType === 'PTDT') {
            //placeholder for Peachtree Deposit Tickets.
        } elseif ($sDepositSlipType === 'GDT') {
            //placeholder for generic deposit ticket.
        }
        //$this->generateBankDepositSlip($Report);

        $this->generateDepositSummary($Report);

        // Export file
        $Report->pdf->Output('ChurchCRM-DepositReport-' . $this->getId() . '-' . date(SystemConfig::getValue('sDateFilenameFormat')) . '.pdf', 'D');
    }

    public function getTotalAmount()
    {
        return $this->getVirtualColumn('totalAmount');
    }

    public function getTotalChecks()
    {
        $totalCash = PledgeQuery::create()
            ->filterByDepId($this->getId())
            ->filterByMethod('CHECK')
            ->withColumn('SUM(Pledge.Amount)', 'sumAmount')
            ->find()
            ->getColumnValues('sumAmount')[0];

        return $totalCash;
    }

    public function getTotalCash()
    {
        $totalCash = PledgeQuery::create()
            ->filterByDepId($this->getId())
            ->filterByMethod('CASH')
            ->withColumn('SUM(Pledge.Amount)', 'sumAmount')
            ->find()
            ->getColumnValues('sumAmount')[0];

        return $totalCash;
    }

    public function getCountChecks()
    {
        $countCash = PledgeQuery::create()
            ->filterByDepId($this->getId())
            ->groupByGroupKey()
            ->filterByMethod('CHECK')
            ->find()
            ->count();

        return $countCash;
    }

    public function getCountCash()
    {
        $countCash = PledgeQuery::create()
            ->filterByDepId($this->getId())
            ->groupByGroupKey()
            ->filterByMethod('CASH')
            ->find()
            ->count();

        return $countCash;
    }

    public function getFundTotals()
    {
        $funds = PledgeQuery::create()
        ->filterByDepId($this->getId())
        ->groupByFundId()
        ->withColumn('SUM(' . PledgeTableMap::COL_PLG_AMOUNT . ')', 'Total')
        ->joinDonationFund()
        ->withColumn(DonationFundTableMap::COL_FUN_NAME, 'Name')
        ->orderBy(DonationFundTableMap::COL_FUN_NAME)
        ->select(['Name', 'Total'])
        ->find();

        return $funds;
    }

    public function getPledgesJoinAll(Criteria $criteria = null, ConnectionInterface $con = null, $joinBehavior = Criteria::LEFT_JOIN)
    {
        $query = ChildPledgeQuery::create(null, $criteria);
        $query->joinWith('Family', Criteria::RIGHT_JOIN);
        $query->joinWith('DonationFund', Criteria::RIGHT_JOIN);

        return $this->getPledges($query, $con);
    }
}
