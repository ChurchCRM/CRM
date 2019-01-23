<?php

use ChurchCRM\Reports\ChurchInfoReport;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\Utils\MiscUtils;

class PDF_TaxReport_V2 extends ChurchInfoReport
{
  private $letterhead;
  private $reportStartDate;
  private $reportEndDate;
  private $curY;
  private $bottomBorder1;
  private $bottomBorder2;
  private $totalAmount;
  private $totalNonDeductible;
  // Constructor
  public function __construct($reportStartDate, $reportEndDate, $letterhead, $includeRemittanceSlip)
  {
      parent::__construct('P', 'mm', $this->paperFormat);
      $this->SetFont('Times', '', 10);
      $this->SetMargins(20, 20);
      $this->SetAutoPageBreak(false);
      $this->letterhead = $letterhead;
      $this->reportEndDate = $reportEndDate;
      $this->reportStartDate = $reportStartDate;
      if ($includeRemittanceSlip == 'yes') {
        $this->bottomBorder1 = 134;
        $this->bottomBorder2 = 180;
      } else {
        $this->bottomBorder1 = 200;
        $this->bottomBorder2 = 250;
      }
  }
    
  /**
   * 
   * @param ChurchCRM\Family $Family
   * @param ChurchCRM\Pledge[] $FamilyPayments
   */

  public function NewFamilyPaymentsPage($Family,$FamilyPayments)
  {       
    $this->InsertPageHeader($Family);
    $this->InsertPaymentsBlock($FamilyPayments);
    $this->InsertPaymentSummaryBlock();
    $this->InsertPageFooter();
  }

  private function InsertPaymentsBlock($FamilyPayments) {
    $summaryDateX = SystemConfig::getValue('leftX');
    $summaryCheckNoX = 40;
    $summaryMethodX = 60;
    $summaryFundX = 85;
    $summaryMemoX = 110;
    $summaryAmountX = 160;
    $summaryIntervalY = 4;
    $this->curY += 2 * $summaryIntervalY;
    $this->SetFont('Times', 'B', 10);
    $this->SetXY($summaryDateX, $this->curY);
    $this->Cell(20, $summaryIntervalY, 'Date');
    $this->Cell(20, $summaryIntervalY, 'Chk No.', 0, 0, 'C');
    $this->Cell(25, $summaryIntervalY, 'PmtMethod');
    $this->Cell(40, $summaryIntervalY, 'Fund');
    $this->Cell(40, $summaryIntervalY, 'Memo');
    $this->Cell(25, $summaryIntervalY, 'Amount', 0, 1, 'R');
    //$this->curY = $this->GetY();

    Foreach ($FamilyPayments as $FamilyPayment) {
      $this->InsertOnePayment($FamilyPayment);
      if ($this->curY > $this->bottomBorder2) {
        $this->AddPage();
        if ($letterhead == 'none') {
          // Leave blank space at top on all pages for pre-printed letterhead
          $this->curY = 20 + ($summaryIntervalY * 3) + 25;
          $this->SetY($this->curY);
        } else {
          $this->curY = 20;
          $this->SetY(20);
        }
      }
    }

  }
  
  private function InsertPaymentSummaryBlock() {
    $summaryIntervalY = 4;
    $this->SetFont('Times', 'B', 10);
    $this->Cell(20, $summaryIntervalY / 2, ' ', 0, 1);
    $this->Cell(95, $summaryIntervalY, ' ');
    $this->Cell(50, $summaryIntervalY, 'Total Payments:');
    $totalAmountStr = '$'.number_format($this->totalAmount, 2);
    $this->SetFont('Courier', '', 9);
    $this->Cell(25, $summaryIntervalY, $totalAmountStr, 0, 1, 'R');
    $this->SetFont('Times', 'B', 10);
    $this->Cell(95, $summaryIntervalY, ' ');
    $this->Cell(50, $summaryIntervalY, 'Goods and Services Rendered:');
    $totalAmountStr = '$'.number_format($this->totalNonDeductible, 2);
    $this->SetFont('Courier', '', 9);
    $this->Cell(25, $summaryIntervalY, $totalAmountStr, 0, 1, 'R');
    $this->SetFont('Times', 'B', 10);
    $this->Cell(95, $summaryIntervalY, ' ');
    $this->Cell(50, $summaryIntervalY, 'Tax-Deductible Contribution:');
    $totalAmountStr = '$'.number_format($this->totalAmount - $this->totalNonDeductible, 2);
    $this->SetFont('Courier', '', 9);
    $this->Cell(25, $summaryIntervalY, $totalAmountStr, 0, 1, 'R');
    $this->curY = $this->GetY();
  }

  /** 
   * 
   * @param ChurchCRM\Pledge $Payment
   */
  private function InsertOnePayment($Payment) {
    // Format Data
    $summaryIntervalY = 4;
    // Print Gift Data
    $this->SetFont('Times', '', 10);
    $this->Cell(20, $summaryIntervalY, $Payment->getDate()->format(SystemConfig::getValue("sDateFormatLong")));
    $this->Cell(20, $summaryIntervalY, MiscUtils::StringLengthTruncate($Payment->getCheckno(), 8), 0, 0, 'R');
    $this->Cell(25, $summaryIntervalY, $Payment->getMethod());
    $this->Cell(40, $summaryIntervalY, MiscUtils::StringLengthTruncate($Payment->getDonationFund()->getName(), 25));
    $this->Cell(40, $summaryIntervalY, MiscUtils::StringLengthTruncate($Payment->getComment(), 25));
    $this->SetFont('Courier', '', 9);
    $this->Cell(25, $summaryIntervalY, $Payment->getAmount(), 0, 1, 'R');
    $this->curY = $this->GetY();
    $this->totalAmount += $Payment->getAmount();
    $this->totalNonDeductible += $Payment->getNondeductible();
  }

  private function InsertPageHeader($Family) {
    $this->curY = $this->StartLetterPage(
            $Family->getId(), 
            $Family->getName(), 
            $Family->getAddress1(), 
            $Family->getAddress2(), 
            $Family->getCity(), 
            $Family->getState(), 
            $Family->getZip(), 
            $Family->getCountry(), 
            $this->letterhead);

     if (SystemConfig::getValue('bUseDonationEnvelopes')) {
          $this->WriteAt(SystemConfig::getValue('leftX'), $curY, gettext('Envelope:').$Family->getEnvelope());
          $this->curY += SystemConfig::getValue('incrementY');
      }
      $this->curY += 2 * SystemConfig::getValue('incrementY');
      /*if ($iDepID) {
          // Get Deposit Date
          $sSQL = "SELECT dep_Date, dep_Date FROM deposit_dep WHERE dep_ID='$iDepID'";
          $rsDep = RunQuery($sSQL);
          list($sDateStart, $sDateEnd) = mysqli_fetch_row($rsDep);
      }*/
      if ($this->reportStartDate == $this->reportEndDate) {
          $DateString = date('F j, Y', strtotime($this->reportStartDate));
      } else {
          $DateString = date('M j, Y', strtotime($this->reportStartDate)).' - '.date('M j, Y', strtotime($this->reportEndDate));
      }
      $blurb = SystemConfig::getValue('sTaxReport1').' '.$DateString.'.';
      $this->WriteAt(SystemConfig::getValue('leftX'), $this->curY, $blurb);
      $this->curY += 2 * SystemConfig::getValue('incrementY');

      return $this->curY;

  }

  private function InsertPageFooter() {
      $this->curY += 2 * SystemConfig::getValue('incrementY');
      $blurb = SystemConfig::getValue('sTaxReport2');
      $this->WriteAt(SystemConfig::getValue('leftX'), $this->curY, $blurb);
      $this->curY += 3 * SystemConfig::getValue('incrementY');
      $blurb = SystemConfig::getValue('sTaxReport3');
      $this->WriteAt(SystemConfig::getValue('leftX'), $this->curY, $blurb);
      $this->curY += 3 * SystemConfig::getValue('incrementY');
      $this->WriteAt(SystemConfig::getValue('leftX'), $this->curY, SystemConfig::getValue('sConfirmSincerely').',');
      $this->curY += 4 * SystemConfig::getValue('incrementY');
      $this->WriteAt(SystemConfig::getValue('leftX'), $this->curY, SystemConfig::getValue('sTaxSigner'));
    }
    
}