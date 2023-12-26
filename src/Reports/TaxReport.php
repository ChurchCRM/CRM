<?php

/*******************************************************************************
*
*  filename    : Reports/TaxReport.php
*  last change : 2005-03-26
*  description : Creates a PDF with all the tax letters for a particular calendar year.

******************************************************************************/

namespace ChurchCRM\Reports;

require '../Include/Config.php';
require '../Include/Functions.php';

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\Utils\InputUtils;
use ChurchCRM\Utils\RedirectUtils;

// Security
if (!AuthenticationManager::getCurrentUser()->isFinanceEnabled()) {
    RedirectUtils::redirect('Menu.php');
    exit;
}

// Filter values
$letterhead = InputUtils::legacyFilterInput($_POST['letterhead']);
$remittance = InputUtils::legacyFilterInput($_POST['remittance']);
$output = InputUtils::legacyFilterInput($_POST['output']);
$sReportType = InputUtils::legacyFilterInput($_POST['ReportType']);
$sDateStart = InputUtils::legacyFilterInput($_POST['DateStart'], 'date');
$sDateEnd = InputUtils::legacyFilterInput($_POST['DateEnd'], 'date');
$iDepID = InputUtils::legacyFilterInput($_POST['deposit'], 'int');
$iMinimum = InputUtils::legacyFilterInput($_POST['minimum'], 'int');

// If CSVAdminOnly option is enabled and user is not admin, redirect to the menu.
if (!AuthenticationManager::getCurrentUser()->isAdmin() && SystemConfig::getValue('bCSVAdminOnly') && $output != 'pdf') {
    RedirectUtils::redirect('Menu.php');
    exit;
}

if (!empty($_POST['classList'])) {
    $classList = $_POST['classList'];

    if ($classList[0]) {
        $sSQL = 'SELECT * FROM list_lst WHERE lst_ID = 1 ORDER BY lst_OptionSequence';
        $rsClassifications = RunQuery($sSQL);

        $inClassList = '(';
        $notInClassList = '(';

        while ($aRow = mysqli_fetch_array($rsClassifications)) {
            extract($aRow);
            if (in_array($lst_OptionID, $classList)) {
                if ($inClassList === '(') {
                    $inClassList .= $lst_OptionID;
                } else {
                    $inClassList .= ',' . $lst_OptionID;
                }
            } else {
                if ($notInClassList === '(') {
                    $notInClassList .= $lst_OptionID;
                } else {
                    $notInClassList .= ',' . $lst_OptionID;
                }
            }
        }
        $inClassList .= ')';
        $notInClassList .= ')';
    }

    // all classes were selected. this should behave as if no filter classes were specified
    if ($notInClassList === '()') {
        unset($classList);
    }
}

// Build SQL Query
// Build SELECT SQL Portion
$sSQL = 'SELECT fam_ID, fam_Name, fam_Address1, fam_Address2, fam_City, fam_State, fam_Zip, fam_Country, fam_envelope, plg_date, plg_amount, plg_method, plg_comment, plg_CheckNo, fun_Name, plg_PledgeOrPayment, plg_NonDeductible FROM family_fam
	INNER JOIN pledge_plg ON fam_ID=plg_FamID
	LEFT JOIN donationfund_fun ON plg_fundID=fun_ID';

$sSQL .= " WHERE plg_PledgeOrPayment='Payment' ";

// Add  SQL criteria
// Report Dates OR Deposit ID
if ($iDepID > 0) {
    $sSQL .= " AND plg_depID='$iDepID' ";
} else {
    $today = date('Y-m-d');
    if (!$sDateEnd && $sDateStart) {
        $sDateEnd = $sDateStart;
    }
    if (!$sDateStart && $sDateEnd) {
        $sDateStart = $sDateEnd;
    }
    if (!$sDateStart && !$sDateEnd) {
        $sDateStart = $today;
        $sDateEnd = $today;
    }
    if ($sDateStart > $sDateEnd) {
        $temp = $sDateStart;
        $sDateStart = $sDateEnd;
        $sDateEnd = $temp;
    }
    $sSQL .= " AND plg_date BETWEEN '$sDateStart' AND '$sDateEnd' ";
}

// Filter by Fund
if (!empty($_POST['funds'])) {
    $count = 0;
    foreach ($_POST['funds'] as $fundID) {
        $fund[$count++] = InputUtils::legacyFilterInput($fundID, 'int');
    }
    if ($count == 1) {
        if ($fund[0]) {
            $sSQL .= " AND plg_fundID='$fund[0]' ";
        }
    } else {
        $sSQL .= " AND (plg_fundID ='$fund[0]'";
        for ($i = 1; $i < $count; $i++) {
            $sSQL .= " OR plg_fundID='$fund[$i]'";
        }
        $sSQL .= ') ';
    }
}
// Filter by Family
if (!empty($_POST['family'])) {
    $count = 0;
    foreach ($_POST['family'] as $famID) {
        $fam[$count++] = InputUtils::legacyFilterInput($famID, 'int');
    }
    if ($count == 1) {
        if ($fam[0]) {
            $sSQL .= " AND plg_FamID='$fam[0]' ";
        }
    } else {
        $sSQL .= " AND (plg_FamID='$fam[0]'";
        for ($i = 1; $i < $count; $i++) {
            $sSQL .= " OR plg_FamID='$fam[$i]'";
        }
        $sSQL .= ') ';
    }
}

if ($classList[0]) {
    $q = ' plg_famID IN (SELECT DISTINCT per_fam_ID FROM person_per WHERE per_cls_ID IN ' . $inClassList . ')';

    $sSQL .= ' AND' . $q;
}

// Get Criteria string
preg_match('/WHERE (plg_PledgeOrPayment.*)/i', $sSQL, $aSQLCriteria);

// Add SQL ORDER
$sSQL .= ' ORDER BY plg_FamID, plg_date ';

//Execute SQL Statement
$rsReport = RunQuery($sSQL);

// Exit if no rows returned
$iCountRows = mysqli_num_rows($rsReport);
if ($iCountRows < 1) {
    header('Location: ../FinancialReports.php?ReturnMessage=NoRows&ReportType=Giving%20Report');
}

// Create Giving Report -- PDF
// ***************************

if ($output === 'pdf') {
    // Set up bottom border values
    if ($remittance === 'yes') {
        $bottom_border1 = 134;
        $bottom_border2 = 180;
    } else {
        $bottom_border1 = 200;
        $bottom_border2 = 250;
    }

    class PdfTaxReport extends ChurchInfoReport
    {
        // Constructor
        public function __construct()
        {
            parent::__construct('P', 'mm', $this->paperFormat);
            $this->SetFont('Times', '', 10);
            $this->SetMargins(20, 20);

            $this->SetAutoPageBreak(false);
        }

        public function startNewPage($fam_ID, $fam_Name, $fam_Address1, $fam_Address2, string $fam_City, string $fam_State, string $fam_Zip, $fam_Country, string $fam_envelope): float
        {
            global $letterhead, $sDateStart, $sDateEnd, $iDepID;
            $curY = $this->startLetterPage($fam_ID, $fam_Name, $fam_Address1, $fam_Address2, $fam_City, $fam_State, $fam_Zip, $fam_Country, $letterhead);
            if (SystemConfig::getValue('bUseDonationEnvelopes')) {
                $this->writeAt(SystemConfig::getValue('leftX'), $curY, gettext('Envelope:') . $fam_envelope);
                $curY += SystemConfig::getValue('incrementY');
            }
            $curY += 2 * SystemConfig::getValue('incrementY');
            if ($iDepID) {
                // Get Deposit Date
                $sSQL = "SELECT dep_Date, dep_Date FROM deposit_dep WHERE dep_ID='$iDepID'";
                $rsDep = RunQuery($sSQL);
                [$sDateStart, $sDateEnd] = mysqli_fetch_row($rsDep);
            }
            if ($sDateStart == $sDateEnd) {
                $DateString = date('F j, Y', strtotime($sDateStart));
            } else {
                $DateString = date('M j, Y', strtotime($sDateStart)) . ' - ' . date('M j, Y', strtotime($sDateEnd));
            }
            $blurb = SystemConfig::getValue('sTaxReport1') . ' ' . $DateString . '.';
            $this->writeAt(SystemConfig::getValue('leftX'), $curY, $blurb);
            $curY += 2 * SystemConfig::getValue('incrementY');

            return $curY;
        }

        public function finishPage($curY, $fam_ID, $fam_Name, $fam_Address1, $fam_Address2, string $fam_City, string $fam_State, string $fam_Zip, $fam_Country): void
        {
            global $remittance;
            $curY += 2 * SystemConfig::getValue('incrementY');
            $blurb = SystemConfig::getValue('sTaxReport2');
            $this->writeAt(SystemConfig::getValue('leftX'), $curY, $blurb);
            $curY += 3 * SystemConfig::getValue('incrementY');
            $blurb = SystemConfig::getValue('sTaxReport3');
            $this->writeAt(SystemConfig::getValue('leftX'), $curY, $blurb);
            $curY += 3 * SystemConfig::getValue('incrementY');
            $this->writeAt(SystemConfig::getValue('leftX'), $curY, SystemConfig::getValue('sConfirmSincerely') . ',');
            $curY += 4 * SystemConfig::getValue('incrementY');
            $this->writeAt(SystemConfig::getValue('leftX'), $curY, SystemConfig::getValue('sTaxSigner'));

            if ($remittance === 'yes') {
                // Add remittance slip
                $curY = 194;
                $curX = 60;
                $this->writeAt($curX, $curY, gettext('Please detach this slip and mail with your next gift.'));
                $curY += (1.5 * SystemConfig::getValue('incrementY'));
                $church_mailing = gettext('Please mail you next gift to ') . SystemConfig::getValue('sChurchName') . ', '
                    . SystemConfig::getValue('sChurchAddress') . ', ' . SystemConfig::getValue('sChurchCity') . ', ' . SystemConfig::getValue('sChurchState') . '  '
                    . SystemConfig::getValue('sChurchZip') . gettext(', Phone: ') . SystemConfig::getValue('sChurchPhone');
                $this->SetFont('Times', 'I', 10);
                $this->writeAt(SystemConfig::getValue('leftX'), $curY, $church_mailing);
                $this->SetFont('Times', '', 10);
                $curY = 215;
                $this->writeAt(SystemConfig::getValue('leftX'), $curY, $this->makeSalutation($fam_ID));
                $curY += SystemConfig::getValue('incrementY');
                if ($fam_Address1 != '') {
                    $this->writeAt(SystemConfig::getValue('leftX'), $curY, $fam_Address1);
                    $curY += SystemConfig::getValue('incrementY');
                }
                if ($fam_Address2 != '') {
                    $this->writeAt(SystemConfig::getValue('leftX'), $curY, $fam_Address2);
                    $curY += SystemConfig::getValue('incrementY');
                }
                $this->writeAt(SystemConfig::getValue('leftX'), $curY, $fam_City . ', ' . $fam_State . '  ' . $fam_Zip);
                $curY += SystemConfig::getValue('incrementY');
                if ($fam_Country != '' && $fam_Country != 'USA' && $fam_Country != 'United States') {
                    $this->writeAt(SystemConfig::getValue('leftX'), $curY, $fam_Country);
                    $curY += SystemConfig::getValue('incrementY');
                }
                $curX = 30;
                $curY = 246;
                $this->writeAt(SystemConfig::getValue('leftX') + 5, $curY, SystemConfig::getValue('sChurchName'));
                $curY += SystemConfig::getValue('incrementY');
                if (SystemConfig::getValue('sChurchAddress') != '') {
                    $this->writeAt(SystemConfig::getValue('leftX') + 5, $curY, SystemConfig::getValue('sChurchAddress'));
                    $curY += SystemConfig::getValue('incrementY');
                }
                $this->writeAt(SystemConfig::getValue('leftX') + 5, $curY, SystemConfig::getValue('sChurchCity') . ', ' . SystemConfig::getValue('sChurchState') . '  ' . SystemConfig::getValue('sChurchZip'));
                $curY += SystemConfig::getValue('incrementY');
                if ($fam_Country != '' && $fam_Country != 'USA' && $fam_Country != 'United States') {
                    $this->writeAt(SystemConfig::getValue('leftX') + 5, $curY, $fam_Country);
                    $curY += SystemConfig::getValue('incrementY');
                }
                $curX = 100;
                $curY = 215;
                $this->writeAt($curX, $curY, gettext('Gift Amount:'));
                $this->writeAt($curX + 25, $curY, '_______________________________');
                $curY += (2 * SystemConfig::getValue('incrementY'));
                $this->writeAt($curX, $curY, gettext('Gift Designation:'));
                $this->writeAt($curX + 25, $curY, '_______________________________');
                $curY = 200 + (11 * SystemConfig::getValue('incrementY'));
            }
        }
    }

    // Instantiate the directory class and build the report.
    $pdf = new PdfTaxReport();

    // Loop through result array
    $currentFamilyID = 0;
    while ($row = mysqli_fetch_array($rsReport)) {
        extract($row);

        // Check for minimum amount
        if ($iMinimum > 0) {
            $temp = "SELECT SUM(plg_amount) AS total_gifts FROM pledge_plg
				WHERE plg_FamID=$fam_ID AND $aSQLCriteria[1]";
            $rsMinimum = RunQuery($temp);
            [$total_gifts] = mysqli_fetch_row($rsMinimum);
            if ($iMinimum > $total_gifts) {
                continue;
            }
        }
        // Check for new family
        if ($fam_ID != $currentFamilyID && $currentFamilyID != 0) {
            //New Family. Finish Previous Family
            $pdf->SetFont('Times', 'B', 10);
            $pdf->Cell(20, $summaryIntervalY / 2, ' ', 0, 1);
            $pdf->Cell(95, $summaryIntervalY, ' ');
            $pdf->Cell(50, $summaryIntervalY, 'Total Payments:');
            $totalAmountStr = '$' . number_format($totalAmount, 2);
            $pdf->SetFont('Courier', '', 9);
            $pdf->Cell(25, $summaryIntervalY, $totalAmountStr, 0, 1, 'R');
            $pdf->SetFont('Times', 'B', 10);
            $pdf->Cell(95, $summaryIntervalY, ' ');
            $pdf->Cell(50, $summaryIntervalY, 'Goods and Services Rendered:');
            $totalAmountStr = '$' . number_format($totalNonDeductible, 2);
            $pdf->SetFont('Courier', '', 9);
            $pdf->Cell(25, $summaryIntervalY, $totalAmountStr, 0, 1, 'R');
            $pdf->SetFont('Times', 'B', 10);
            $pdf->Cell(95, $summaryIntervalY, ' ');
            $pdf->Cell(50, $summaryIntervalY, 'Tax-Deductible Contribution:');
            $totalAmountStr = '$' . number_format($totalAmount - $totalNonDeductible, 2);
            $pdf->SetFont('Courier', '', 9);
            $pdf->Cell(25, $summaryIntervalY, $totalAmountStr, 0, 1, 'R');
            $curY = $pdf->GetY();

            if ($curY > $bottom_border1) {
                $pdf->addPage();
                if ($letterhead === 'none') {
                    // Leave blank space at top on all pages for pre-printed letterhead
                    $curY = 20 + ($summaryIntervalY * 3) + 25;
                    $pdf->SetY($curY);
                } else {
                    $curY = 20;
                    $pdf->SetY(20);
                }
            }
            $pdf->SetFont('Times', '', 10);
            $pdf->finishPage(
                $curY,
                $fam_ID,
                $fam_Name,
                $fam_Address1,
                $fam_Address2,
                $fam_City,
                $fam_State,
                $fam_Zip,
                $fam_Country
            );
        }

        // Start Page for New Family
        $cnt = 0;
        if ($fam_ID != $currentFamilyID) {
            $curY = $pdf->startNewPage($fam_ID, $fam_Name, $fam_Address1, $fam_Address2, $fam_City, $fam_State, $fam_Zip, $fam_Country, $fam_envelope);
            $summaryDateX = SystemConfig::getValue('leftX');
            $summaryCheckNoX = 40;
            $summaryMethodX = 60;
            $summaryFundX = 85;
            $summaryMemoX = 110;
            $summaryAmountX = 160;
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
            //$curY = $pdf->GetY();
            $totalAmount = 0;
            $totalNonDeductible = 0;
            $currentFamilyID = $fam_ID;
        }
        // Format Data
        if (strlen($plg_CheckNo) > 8) {
            $plg_CheckNo = '...' . mb_substr($plg_CheckNo, -8, 8);
        } else {
            $plg_CheckNo .= '    ';
        }
        if (strlen($fun_Name) > 25) {
            $fun_Name = mb_substr($fun_Name, 0, 25) . '...';
        }
        if (strlen($plg_comment) > 25) {
            $plg_comment = mb_substr($plg_comment, 0, 25) . '...';
        }
        // Print Gift Data
        $pdf->SetFont('Times', '', 10);
        $pdf->Cell(20, $summaryIntervalY, $plg_date);
        $pdf->Cell(20, $summaryIntervalY, $plg_CheckNo, 0, 0, 'R');
        $pdf->Cell(25, $summaryIntervalY, $plg_method);
        $pdf->Cell(40, $summaryIntervalY, $fun_Name);
        $pdf->Cell(40, $summaryIntervalY, $plg_comment);
        $pdf->SetFont('Courier', '', 9);
        $pdf->Cell(25, $summaryIntervalY, $plg_amount, 0, 1, 'R');
        $totalAmount += $plg_amount;
        $totalNonDeductible += $plg_NonDeductible;
        $cnt += 1;
        $curY = $pdf->GetY();

        if ($curY > $bottom_border2) {
            $pdf->addPage();
            if ($letterhead === 'none') {
                // Leave blank space at top on all pages for pre-printed letterhead
                $curY = 20 + ($summaryIntervalY * 3) + 25;
                $pdf->SetY($curY);
            } else {
                $curY = 20;
                $pdf->SetY(20);
            }
        }
        $prev_fam_ID = $fam_ID;
        $prev_fam_Name = $fam_Name;
        $prev_fam_Address1 = $fam_Address1;
        $prev_fam_Address2 = $fam_Address2;
        $prev_fam_City = $fam_City;
        $prev_fam_State = $fam_State;
        $prev_fam_Zip = $fam_Zip;
        $prev_fam_Country = $fam_Country;
    }

    // Finish Last Report
    $pdf->SetFont('Times', 'B', 10);
    $pdf->addPage();
    $pdf->Cell(20, $summaryIntervalY / 2, ' ', 0, 1);
    $pdf->Cell(95, $summaryIntervalY, ' ');
    $pdf->Cell(50, $summaryIntervalY, 'Total Payments:');
    $totalAmountStr = '$' . number_format($totalAmount, 2);
    $pdf->SetFont('Courier', '', 9);
    $pdf->Cell(25, $summaryIntervalY, $totalAmountStr, 0, 1, 'R');
    $pdf->SetFont('Times', 'B', 10);
    $pdf->Cell(95, $summaryIntervalY, ' ');
    $pdf->Cell(50, $summaryIntervalY, 'Goods and Services Rendered:');
    $totalAmountStr = '$' . number_format($totalNonDeductible, 2);
    $pdf->SetFont('Courier', '', 9);
    $pdf->Cell(25, $summaryIntervalY, $totalAmountStr, 0, 1, 'R');
    $pdf->SetFont('Times', 'B', 10);
    $pdf->Cell(95, $summaryIntervalY, ' ');
    $pdf->Cell(50, $summaryIntervalY, 'Tax-Deductible Contribution:');
    $totalAmountStr = '$' . number_format($totalAmount - $totalNonDeductible, 2);
    $pdf->SetFont('Courier', '', 9);
    $pdf->Cell(25, $summaryIntervalY, $totalAmountStr, 0, 1, 'R');
    $curY = $pdf->GetY();

    if ($cnt > 0) {
        if ($curY > $bottom_border1) {
            $pdf->addPage();
            if ($letterhead === 'none') {
                // Leave blank space at top on all pages for pre-printed letterhead
                $curY = 20 + ($summaryIntervalY * 3) + 25;
                $pdf->SetY($curY);
            } else {
                $curY = 20;
                $pdf->SetY(20);
            }
        }
        $pdf->SetFont('Times', '', 10);
        $pdf->finishPage(
            $curY,
            $fam_ID,
            $fam_Name,
            $fam_Address1,
            $fam_Address2,
            $fam_City,
            $fam_State,
            $fam_Zip,
            $fam_Country
        );
    }

    header('Pragma: public');  // Needed for IE when using a shared SSL certificate
    if (SystemConfig::getValue('iPDFOutputType') == 1) {
        $pdf->Output('TaxReport' . date(SystemConfig::getValue('sDateFilenameFormat')) . '.pdf', 'D');
    } else {
        $pdf->Output();
    }

// Output a text file
// ##################
} elseif ($output === 'csv') {
    // Settings
    $delimiter = ',';
    $eol = "\r\n";

    // Build headings row
    preg_match('/SELECT (.*) FROM /i', $sSQL, $result);
    $headings = explode(',', $result[1]);
    $buffer = '';
    foreach ($headings as $heading) {
        $buffer .= trim($heading) . $delimiter;
    }
    // Remove trailing delimiter and add eol
    $buffer = mb_substr($buffer, 0, -1) . $eol;

    // Add data
    while ($row = mysqli_fetch_row($rsReport)) {
        foreach ($row as $field) {
            $field = str_replace($delimiter, ' ', $field);    // Remove any delimiters from data
            $buffer .= $field . $delimiter;
        }
        // Remove trailing delimiter and add eol
        $buffer = mb_substr($buffer, 0, -1) . $eol;
    }

    // Export file
    header('Content-type: text/x-csv');
    header('Content-Disposition: attachment; filename=ChurchCRM-' . date(SystemConfig::getValue('sDateFilenameFormat')) . '.csv');
    echo $buffer;
}
