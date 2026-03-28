<?php

namespace ChurchCRM\Reports;

require_once __DIR__ . '/../Include/Config.php';
require_once __DIR__ . '/../Include/Functions.php';

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\Service\FinancialService;
use ChurchCRM\Utils\CsvExporter;
use ChurchCRM\Utils\DateTimeUtils;
use ChurchCRM\Utils\InputUtils;
use ChurchCRM\Utils\RedirectUtils;

require_once __DIR__ . '/PdfTaxReport.php';

// Security
AuthenticationManager::redirectHomeIfFalse(AuthenticationManager::getCurrentUser()->isFinanceEnabled(), 'Finance');

// Support direct GET links from family profile: ?familyId=X&year=YYYY
if (isset($_GET['familyId']) && isset($_GET['year'])) {
    $gFamId = (int) InputUtils::legacyFilterInput($_GET['familyId'], 'int');
    $gYear  = (int) InputUtils::legacyFilterInput($_GET['year'], 'int');
    if ($gFamId > 0 && $gYear > 1990 && $gYear <= (int) date('Y')) {
        $_POST['family']     = [$gFamId];
        $_POST['DateStart']  = $gYear . '-01-01';
        $_POST['DateEnd']    = $gYear . '-12-31';
        $_POST['output']     = 'pdf';
        $_POST['letterhead'] = 'address';
        $_POST['remittance'] = 'no';
    }
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

// Prepare filter arrays
$classList = [];
$fundIds = [];
$familyIds = [];

if (!empty($_POST['classList'])) {
    $classList = array_map('intval', $_POST['classList']);
}

if (!empty($_POST['funds'])) {
    foreach ($_POST['funds'] as $fundID) {
        $fundIds[] = InputUtils::legacyFilterInput($fundID, 'int');
    }
}

if (!empty($_POST['family'])) {
    foreach ($_POST['family'] as $famID) {
        $familyIds[] = InputUtils::legacyFilterInput($famID, 'int');
    }
}

// Normalize date range
$today = DateTimeUtils::getTodayDate();
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

// Use FinancialService to get data using ORM instead of raw SQL
$financialService = new FinancialService();
$pledgeObjects = $financialService->getTaxReportData(
    $sDateStart,
    $sDateEnd,
    $iDepID > 0 ? $iDepID : null,
    $iMinimum > 0 ? $iMinimum : null,
    $fundIds,
    $familyIds,
    $classList
);

// Convert Propel objects to array format for backward compatibility with existing PDF/CSV code
$rsReport = [];
foreach ($pledgeObjects as $pledge) {
    $row = [
        'fam_ID' => $pledge['FamId'],
        'fam_Name' => $pledge['Family']['Name'] ?? 'Unassigned',
        'fam_Address1' => $pledge['Family']['Address1'] ?? '',
        'fam_Address2' => $pledge['Family']['Address2'] ?? '',
        'fam_City' => $pledge['Family']['City'] ?? '',
        'fam_State' => $pledge['Family']['State'] ?? '',
        'fam_Zip' => $pledge['Family']['Zip'] ?? '',
        'fam_Country' => $pledge['Family']['Country'] ?? '',
        'fam_envelope' => $pledge['Family']['Envelope'] ?? '',
        'plg_date' => $pledge['Date'],
        'plg_amount' => $pledge['Amount'],
        'plg_method' => $pledge['Method'],
        'plg_comment' => $pledge['Comment'],
        'plg_CheckNo' => $pledge['CheckNo'] ?? '',
        'fun_Name' => $pledge['DonationFund']['Name'] ?? 'Undesignated',
        'plg_PledgeOrPayment' => $pledge['PledgeOrPayment'],
        'plg_NonDeductible' => $pledge['Nondeductible'] ?? 0,
    ];
    $rsReport[] = $row;
}

// Store criteria string for PDF report
$aSQLCriteria = [];
$criteriaStr ="plg_PledgeOrPayment='Payment'";
if ($iDepID > 0) {
    $criteriaStr .=" AND plg_depID='$iDepID'";
} else {
    $criteriaStr .=" AND plg_date BETWEEN '$sDateStart' AND '$sDateEnd'";
}
$aSQLCriteria[1] = $criteriaStr;

// Exit if no rows returned
$iCountRows = count($rsReport);
if ($iCountRows < 1) {
    RedirectUtils::redirect('FinancialReports.php?ReturnMessage=NoRows&ReportType=Giving%20Report');
}

// Create Giving Report -- PDF
if ($output === 'pdf') {
    // Set up bottom border values
    if ($remittance === 'yes') {
        $bottom_border1 = 134;
        $bottom_border2 = 180;
    } else {
        $bottom_border1 = 200;
        $bottom_border2 = 250;
    }

    // Instantiate the report class and build the report.
    $pdf = new PdfTaxReport();

    // Loop through result array
    $currentFamilyID = -1;  // Initialize to -1 so first record (even with fam_ID=0) creates a new page
    foreach ($rsReport as $row) {
        extract($row);

        // Minimum amount filtering is now handled in FinancialService
        // No need to re-query for minimum amount check
        // Check for new family
        if ($fam_ID != $currentFamilyID && $currentFamilyID != -1) {
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

    if (SystemConfig::getIntValue('iPDFOutputType') === 1) {
        $pdf->Output('TaxReport' . date(SystemConfig::getValue('sDateFilenameFormat')) . '.pdf', 'D');
    } else {
        $pdf->Output();
    }

    // Output a text file
} elseif ($output === 'csv') {
    // Use already fetched data from ORM (rsReport is already an array)
    
    // Build headers array from first row keys
    $headers = [];
    if (!empty($rsReport)) {
        $headers = array_keys($rsReport[0]);
    }

    // Convert associative array to 2D indexed array for CsvExporter
    $rows = [];
    foreach ($rsReport as $row) {
        $rows[] = array_values($row);
    }

    // Only export if we have headers and rows
    if (!empty($headers) && !empty($rows)) {
        // Export using CsvExporter
        // basename: 'TaxReport', includeDateInFilename: true adds today's date, .csv is added automatically
        CsvExporter::create($headers, $rows, 'TaxReport', 'UTF-8', true);
    } else {
        $params = [
            'ReturnMessage' => 'NoRows',
            'ReportType' => 'Giving Report',
            'DateStart' => $sDateStart,
            'DateEnd'   => $sDateEnd,
        ];
        RedirectUtils::redirect('FinancialReports.php?' . http_build_query($params));
    }
}
