<?php

namespace ChurchCRM\Reports;

require_once __DIR__ . '/../Include/Config.php';
require_once __DIR__ . '/../Include/Functions.php';
require_once __DIR__ . '/PdfTaxReport.php';

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\dto\ChurchMetaData;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\Emails\BaseEmail;
use ChurchCRM\model\ChurchCRM\FamilyQuery;
use ChurchCRM\Service\FinancialService;
use ChurchCRM\Utils\InputUtils;
use ChurchCRM\Utils\LoggerUtils;
use ChurchCRM\Utils\RedirectUtils;

// Security: Finance role required
AuthenticationManager::redirectHomeIfFalse(AuthenticationManager::getCurrentUser()->isFinanceEnabled(), 'Finance');

// Input: familyId and year are required
$familyId = (int) InputUtils::legacyFilterInput($_GET['familyId'] ?? '', 'int');
$year     = (int) InputUtils::legacyFilterInput($_GET['year'] ?? '', 'int');

if ($familyId <= 0 || $year <= 1990 || $year > (int) date('Y')) {
    RedirectUtils::redirect('FinancialReports.php');
}

$family = FamilyQuery::create()->findPk($familyId);
if (empty($family)) {
    RedirectUtils::redirect('v2/family/not-found?id=' . $familyId);
}

$emailList = $family->getEmails();
if (empty($emailList)) {
    RedirectUtils::redirect('v2/family/' . $familyId . '?TaxEmailError=NoEmail');
}

// Build date range for the calendar year
$sDateStart = $year . '-01-01';
$sDateEnd   = $year . '-12-31';

// Globals used by PdfTaxReport methods
$letterhead = 'address';
$remittance = 'no';
$iDepID     = 0;

// Get pledge data for this family + year
$financialService = new FinancialService();
$pledgeObjects = $financialService->getTaxReportData(
    $sDateStart,
    $sDateEnd,
    null,   // no deposit filter
    null,   // no minimum
    [],     // all funds
    [$familyId],
    []      // all classifications
);

if (empty($pledgeObjects)) {
    RedirectUtils::redirect('v2/family/' . $familyId . '?TaxEmailError=NoData');
}

// Convert to flat array (same format as TaxReport.php)
$rsReport = [];
foreach ($pledgeObjects as $pledge) {
    $rsReport[] = [
        'fam_ID'              => $pledge['FamId'],
        'fam_Name'            => $pledge['Family']['Name'] ?? 'Unassigned',
        'fam_Address1'        => $pledge['Family']['Address1'] ?? '',
        'fam_Address2'        => $pledge['Family']['Address2'] ?? '',
        'fam_City'            => $pledge['Family']['City'] ?? '',
        'fam_State'           => $pledge['Family']['State'] ?? '',
        'fam_Zip'             => $pledge['Family']['Zip'] ?? '',
        'fam_Country'         => $pledge['Family']['Country'] ?? '',
        'fam_envelope'        => $pledge['Family']['Envelope'] ?? '',
        'plg_date'            => $pledge['Date'],
        'plg_amount'          => $pledge['Amount'],
        'plg_method'          => $pledge['Method'],
        'plg_comment'         => $pledge['Comment'],
        'plg_CheckNo'         => $pledge['CheckNo'] ?? '',
        'fun_Name'            => $pledge['DonationFund']['Name'] ?? 'Undesignated',
        'plg_PledgeOrPayment' => $pledge['PledgeOrPayment'],
        'plg_NonDeductible'   => $pledge['Nondeductible'] ?? 0,
    ];
}

// ----- Build the PDF (same rendering logic as TaxReport.php) -----
$bottom_border1 = 200;
$bottom_border2 = 250;
$summaryIntervalY = 4;

$pdf = new PdfTaxReport();

$currentFamilyID = -1;
$totalAmount       = 0;
$totalNonDeductible = 0;
$cnt = 0;
$fam_ID = $fam_Name = $fam_Address1 = $fam_Address2 = $fam_City = $fam_State = $fam_Zip = $fam_Country = $fam_envelope = '';
$prev_fam_ID = $prev_fam_Name = $prev_fam_Address1 = $prev_fam_Address2 = $prev_fam_City = $prev_fam_State = $prev_fam_Zip = $prev_fam_Country = '';

foreach ($rsReport as $row) {
    $fam_ID        = $row['fam_ID'];
    $fam_Name      = $row['fam_Name'];
    $fam_Address1  = $row['fam_Address1'];
    $fam_Address2  = $row['fam_Address2'];
    $fam_City      = $row['fam_City'];
    $fam_State     = $row['fam_State'];
    $fam_Zip       = $row['fam_Zip'];
    $fam_Country   = $row['fam_Country'];
    $fam_envelope  = $row['fam_envelope'];
    $plg_date      = $row['plg_date'];
    $plg_amount    = $row['plg_amount'];
    $plg_method    = $row['plg_method'];
    $plg_comment   = $row['plg_comment'];
    $plg_CheckNo   = $row['plg_CheckNo'];
    $fun_Name      = $row['fun_Name'];
    $plg_NonDeductible = $row['plg_NonDeductible'];

    if ($fam_ID != $currentFamilyID && $currentFamilyID != -1) {
        // Finish previous family
        $pdf->SetFont('Times', 'B', 10);
        $pdf->Cell(20, $summaryIntervalY / 2, ' ', 0, 1);
        $pdf->Cell(95, $summaryIntervalY, ' ');
        $pdf->Cell(50, $summaryIntervalY, 'Total Payments:');
        $pdf->SetFont('Courier', '', 9);
        $pdf->Cell(25, $summaryIntervalY, '$' . number_format($totalAmount, 2), 0, 1, 'R');
        $pdf->SetFont('Times', 'B', 10);
        $pdf->Cell(95, $summaryIntervalY, ' ');
        $pdf->Cell(50, $summaryIntervalY, 'Goods and Services Rendered:');
        $pdf->SetFont('Courier', '', 9);
        $pdf->Cell(25, $summaryIntervalY, '$' . number_format($totalNonDeductible, 2), 0, 1, 'R');
        $pdf->SetFont('Times', 'B', 10);
        $pdf->Cell(95, $summaryIntervalY, ' ');
        $pdf->Cell(50, $summaryIntervalY, 'Tax-Deductible Contribution:');
        $pdf->SetFont('Courier', '', 9);
        $pdf->Cell(25, $summaryIntervalY, '$' . number_format($totalAmount - $totalNonDeductible, 2), 0, 1, 'R');
        $curY = $pdf->GetY();
        if ($curY > $bottom_border1) {
            $pdf->addPage();
            $curY = 20;
            $pdf->SetY(20);
        }
        $pdf->SetFont('Times', '', 10);
        $pdf->finishPage($curY, $prev_fam_ID, $prev_fam_Name, $prev_fam_Address1, $prev_fam_Address2, $prev_fam_City, $prev_fam_State, $prev_fam_Zip, $prev_fam_Country);
    }

    if ($fam_ID != $currentFamilyID) {
        $curY = $pdf->startNewPage($fam_ID, $fam_Name, $fam_Address1, $fam_Address2, $fam_City, $fam_State, $fam_Zip, $fam_Country, $fam_envelope);
        $summaryDateX   = SystemConfig::getValue('leftX');
        $summaryIntervalY = 4;
        $curY += 2 * $summaryIntervalY;
        $pdf->SetFont('Times', 'B', 10);
        $pdf->SetXY($summaryDateX, $curY);
        $pdf->Cell(20, $summaryIntervalY, 'Date');
        $pdf->Cell(20, $summaryIntervalY, 'Chk No.', 0, 0, 'C');
        $pdf->Cell(25, $summaryIntervalY, 'PmtMethod');
        $pdf->Cell(40, $summaryIntervalY, 'Fund');
        $pdf->Cell(40, $summaryIntervalY, 'Memo');
        $pdf->Cell(25, $summaryIntervalY, 'Amount', 0, 1, 'R');
        $totalAmount        = 0;
        $totalNonDeductible = 0;
        $cnt = 0;
        $currentFamilyID = $fam_ID;
    }

    // Truncate long fields
    if (strlen((string) $plg_CheckNo) > 8) {
        $plg_CheckNo = '...' . mb_substr((string) $plg_CheckNo, -8, 8);
    } else {
        $plg_CheckNo .= '    ';
    }
    if (strlen($fun_Name) > 25) {
        $fun_Name = mb_substr($fun_Name, 0, 25) . '...';
    }
    if (strlen($plg_comment) > 25) {
        $plg_comment = mb_substr($plg_comment, 0, 25) . '...';
    }

    $pdf->SetFont('Times', '', 10);
    $pdf->Cell(20, $summaryIntervalY, $plg_date);
    $pdf->Cell(20, $summaryIntervalY, $plg_CheckNo, 0, 0, 'R');
    $pdf->Cell(25, $summaryIntervalY, $plg_method);
    $pdf->Cell(40, $summaryIntervalY, $fun_Name);
    $pdf->Cell(40, $summaryIntervalY, $plg_comment);
    $pdf->SetFont('Courier', '', 9);
    $pdf->Cell(25, $summaryIntervalY, $plg_amount, 0, 1, 'R');
    $totalAmount        += $plg_amount;
    $totalNonDeductible += $plg_NonDeductible;
    $cnt++;
    $curY = $pdf->GetY();

    if ($curY > $bottom_border2) {
        $pdf->addPage();
        $curY = 20;
        $pdf->SetY(20);
    }

    $prev_fam_ID       = $fam_ID;
    $prev_fam_Name     = $fam_Name;
    $prev_fam_Address1 = $fam_Address1;
    $prev_fam_Address2 = $fam_Address2;
    $prev_fam_City     = $fam_City;
    $prev_fam_State    = $fam_State;
    $prev_fam_Zip      = $fam_Zip;
    $prev_fam_Country  = $fam_Country;
}

// Finish last family
$pdf->SetFont('Times', 'B', 10);
$pdf->addPage();
$pdf->Cell(20, $summaryIntervalY / 2, ' ', 0, 1);
$pdf->Cell(95, $summaryIntervalY, ' ');
$pdf->Cell(50, $summaryIntervalY, 'Total Payments:');
$pdf->SetFont('Courier', '', 9);
$pdf->Cell(25, $summaryIntervalY, '$' . number_format($totalAmount, 2), 0, 1, 'R');
$pdf->SetFont('Times', 'B', 10);
$pdf->Cell(95, $summaryIntervalY, ' ');
$pdf->Cell(50, $summaryIntervalY, 'Goods and Services Rendered:');
$pdf->SetFont('Courier', '', 9);
$pdf->Cell(25, $summaryIntervalY, '$' . number_format($totalNonDeductible, 2), 0, 1, 'R');
$pdf->SetFont('Times', 'B', 10);
$pdf->Cell(95, $summaryIntervalY, ' ');
$pdf->Cell(50, $summaryIntervalY, 'Tax-Deductible Contribution:');
$pdf->SetFont('Courier', '', 9);
$pdf->Cell(25, $summaryIntervalY, '$' . number_format($totalAmount - $totalNonDeductible, 2), 0, 1, 'R');
$curY = $pdf->GetY();

if ($cnt > 0) {
    if ($curY > $bottom_border1) {
        $pdf->addPage();
        $curY = 20;
        $pdf->SetY(20);
    }
    $pdf->SetFont('Times', '', 10);
    $pdf->finishPage($curY, $fam_ID, $fam_Name, $fam_Address1, $fam_Address2, $fam_City, $fam_State, $fam_Zip, $fam_Country);
}

// Capture PDF as string and email
$filename = 'TaxStatement-' . $family->getName() . '-' . $year . '.pdf';
$pdfString = $pdf->Output($filename, 'S');

// Simple email delivery using PHPMailer via BaseEmail pattern
$mail = new class($emailList) extends BaseEmail {
    public function __construct(array $emails)
    {
        parent::__construct($emails);
    }
};
$mail->addStringAttachment($pdfString, $filename);
$mail->mail->Subject = sprintf(
    gettext('%s %d Giving Statement'),
    ChurchMetaData::getChurchName(),
    $year
);
$mail->mail->isHTML(false);
$mail->mail->Body = sprintf(
    gettext("Dear %s Family,\n\nPlease find your %d giving statement from %s attached to this email.\n\nIf you have any questions, please contact the church office.\n\nThank you for your generosity."),
    $family->getName(),
    $year,
    ChurchMetaData::getChurchName()
);

if ($mail->send()) {
    RedirectUtils::redirect('v2/family/' . $familyId . '?TaxEmailSent=' . $year);
} else {
    LoggerUtils::getAppLogger()->error('FamilyTaxReportEmail failed to send for family ' . $familyId . ' year ' . $year);
    RedirectUtils::redirect('v2/family/' . $familyId . '?TaxEmailError=SendFailed');
}
