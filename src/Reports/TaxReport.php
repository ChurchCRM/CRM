<?php

namespace ChurchCRM\Reports;

require_once __DIR__ . '/../Include/Config.php';
require_once __DIR__ . '/../Include/Functions.php';

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\Utils\CsvExporter;
use ChurchCRM\Utils\InputUtils;
use ChurchCRM\Utils\RedirectUtils;
use ChurchCRM\Service\FinancialService;

// Security
AuthenticationManager::redirectHomeIfFalse(AuthenticationManager::getCurrentUser()->isFinanceEnabled(), 'Finance');

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
    RedirectUtils::securityRedirect('Admin');
}

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
$criteriaStr = "plg_PledgeOrPayment='Payment'";
if ($iDepID > 0) {
    $criteriaStr .= " AND plg_depID='$iDepID'";
} else {
    $criteriaStr .= " AND plg_date BETWEEN '$sDateStart' AND '$sDateEnd'";
}
$aSQLCriteria[1] = $criteriaStr;

// Exit if no rows returned
$iCountRows = count($rsReport);
if ($iCountRows < 1) {
    header('Location: ../FinancialReports.php?ReturnMessage=NoRows&ReportType=Giving%20Report');
    exit();
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
                $this->writeAt(SystemConfig::getValue('leftX'), $curY, gettext('Envelope') . ': ' . $fam_envelope);
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

            return $curY + 2 * SystemConfig::getValue('incrementY');
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
                    . SystemConfig::getValue('sChurchZip') . ', ' . gettext('Phone') . ': ' . SystemConfig::getValue('sChurchPhone');
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
                $this->writeAt($curX, $curY, gettext('Gift Amount') . ':');
                $this->writeAt($curX + 25, $curY, '_______________________________');
                $curY += (2 * SystemConfig::getValue('incrementY'));
                $this->writeAt($curX, $curY, gettext('Gift Designation') . ':');
                $this->writeAt($curX + 25, $curY, '_______________________________');
                $curY = 200 + (11 * SystemConfig::getValue('incrementY'));
            }
        }
    }

    // Instantiate the directory class and build the report.
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

    if ((int) SystemConfig::getValue('iPDFOutputType') === 1) {
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
        header('Location: ../FinancialReports.php?' . http_build_query($params));
        exit();
    }
}
