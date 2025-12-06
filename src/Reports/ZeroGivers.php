<?php

namespace ChurchCRM\Reports;

require_once '../Include/Config.php';
require_once '../Include/Functions.php';

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\Utils\CsvExporter;
use ChurchCRM\Utils\InputUtils;
use ChurchCRM\Utils\RedirectUtils;
use ChurchCRM\Service\FinancialService;

// Security
AuthenticationManager::redirectHomeIfFalse(AuthenticationManager::getCurrentUser()->isFinanceEnabled(), 'Finance');

// Filter values
$output = InputUtils::legacyFilterInput($_POST['output']);
$sDateStart = InputUtils::legacyFilterInput($_POST['DateStart'], 'date');
$sDateEnd = InputUtils::legacyFilterInput($_POST['DateEnd'], 'date');

$letterhead = InputUtils::legacyFilterInput($_POST['letterhead']);
$remittance = InputUtils::legacyFilterInput($_POST['remittance']);

// If CSVAdminOnly option is enabled and user is not admin, redirect to the menu.
if (!AuthenticationManager::getCurrentUser()->isAdmin() && SystemConfig::getValue('bCSVAdminOnly') && $output != 'pdf') {
    RedirectUtils::securityRedirect('Admin');
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
$familyObjects = $financialService->getZeroGiversReportData($sDateStart, $sDateEnd);

// Convert Propel objects to array format for backward compatibility with existing PDF/CSV code
$rsReport = [];
foreach ($familyObjects as $family) {
    $row = [
        'fam_ID' => $family['Id'],
        'fam_Name' => $family['Name'] ?? '',
        'fam_Address1' => $family['Address1'] ?? '',
        'fam_Address2' => $family['Address2'] ?? '',
        'fam_City' => $family['City'] ?? '',
        'fam_State' => $family['State'] ?? '',
        'fam_Zip' => $family['Zip'] ?? '',
        'fam_Country' => $family['Country'] ?? '',
    ];
    $rsReport[] = $row;
}

// Exit if no rows returned
$iCountRows = count($rsReport);
if ($iCountRows < 1) {
    header('Location: ../FinancialReports.php?ReturnMessage=NoRows&ReportType=Zero%20Givers');
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

    class PdfZeroGivers extends ChurchInfoReport
    {
        // Constructor
        public function __construct()
        {
            parent::__construct('P', 'mm', $this->paperFormat);
            $this->SetFont('Times', '', 10);
            $this->SetMargins(20, 20);

            $this->SetAutoPageBreak(false);
        }

        public function startNewPage($fam_ID, $fam_Name, $fam_Address1, $fam_Address2, string $fam_City, string $fam_State, string $fam_Zip, $fam_Country): float
        {
            global $letterhead, $sDateStart, $sDateEnd;
            $curY = $this->startLetterPage($fam_ID, $fam_Name, $fam_Address1, $fam_Address2, $fam_City, $fam_State, $fam_Zip, $fam_Country, $letterhead);
            $curY += 2 * SystemConfig::getValue('incrementY');
            if ($sDateStart == $sDateEnd) {
                $DateString = date('F j, Y', strtotime($sDateStart));
            } else {
                $DateString = date('M j, Y', strtotime($sDateStart)) . ' - ' . date('M j, Y', strtotime($sDateEnd));
            }

            $blurb = SystemConfig::getValue('sTaxReport1') . ' ' . $DateString . ' ' . SystemConfig::getValue('sZeroGivers');
            $this->writeAt(SystemConfig::getValue('leftX'), $curY, $blurb);

            return $curY + 30 * SystemConfig::getValue('incrementY');
        }

        public function finishPage($curY, $fam_ID, $fam_Name, $fam_Address1, $fam_Address2, $fam_City, $fam_State, $fam_Zip, $fam_Country): void
        {
            global $remittance;
            $curY += 2 * SystemConfig::getValue('incrementY');
            $blurb = SystemConfig::getValue('sZeroGivers2');
            $this->writeAt(SystemConfig::getValue('leftX'), $curY, $blurb);
            $curY += 3 * SystemConfig::getValue('incrementY');
            $blurb = SystemConfig::getValue('sZeroGivers3');
            $this->writeAt(SystemConfig::getValue('leftX'), $curY, $blurb);
            $curY += 3 * SystemConfig::getValue('incrementY');
            $this->writeAt(SystemConfig::getValue('leftX'), $curY, SystemConfig::getValue('sConfirmSincerely') . ',');
            $curY += 4 * SystemConfig::getValue('incrementY');
            $this->writeAt(SystemConfig::getValue('leftX'), $curY, SystemConfig::getValue('sTaxSigner'));
        }
    }

    // Instantiate the directory class and build the report.
    $pdf = new PdfZeroGivers();

    // Loop through result array
    foreach ($rsReport as $row) {
        extract($row);
        $curY = $pdf->startNewPage($fam_ID, $fam_Name, $fam_Address1, $fam_Address2, $fam_City, $fam_State, $fam_Zip, $fam_Country);

        $pdf->finishPage($curY, $fam_ID, $fam_Name, $fam_Address1, $fam_Address2, $fam_City, $fam_State, $fam_Zip, $fam_Country);
    }

    if ((int) SystemConfig::getValue('iPDFOutputType') === 1) {
        $pdf->Output('ZeroGivers' . date(SystemConfig::getValue('sDateFilenameFormat')) . '.pdf', 'D');
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
        // basename: 'ZeroGivers', includeDateInFilename: true adds today's date, .csv is added automatically
        CsvExporter::create($headers, $rows, 'ZeroGivers', 'UTF-8', true);
    } else {
        $params = [
            'ReturnMessage' => 'NoRows',
            'ReportType' => 'Zero Givers',
            'DateStart' => $sDateStart,
            'DateEnd'   => $sDateEnd,
        ];
        header('Location: ../FinancialReports.php?' . http_build_query($params));
    }
} else {
    echo '[' . $output . '] output selected, but is not known';
}
