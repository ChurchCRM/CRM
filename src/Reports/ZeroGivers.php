<?php

namespace ChurchCRM\Reports;

require_once __DIR__ . '/../Include/Config.php';
require_once __DIR__ . '/../Include/PageInit.php';

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\model\ChurchCRM\Family;
use ChurchCRM\Service\FinancialService;
use ChurchCRM\Utils\CsvExporter;
use ChurchCRM\Utils\DateTimeUtils;
use ChurchCRM\Utils\InputUtils;
use ChurchCRM\Utils\RedirectUtils;

// Security
AuthenticationManager::redirectHomeIfFalse(AuthenticationManager::getCurrentUser()->isFinanceEnabled(), 'Finance');

// Filter values
$output = InputUtils::legacyFilterInput($_POST['output']);
$sDateStart = InputUtils::legacyFilterInput($_POST['DateStart'], 'date');
$sDateEnd = InputUtils::legacyFilterInput($_POST['DateEnd'], 'date');

$letterhead = InputUtils::legacyFilterInput($_POST['letterhead']);
$remittance = InputUtils::legacyFilterInput($_POST['remittance']);

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
$familyObjects = $financialService->getZeroGiversReportData($sDateStart, $sDateEnd);

// Convert Propel objects to array format for backward compatibility with existing PDF/CSV code
$rsReport = [];
// These letters are mailed, so they are addressed to each family's mailing address.
// Kept out of $rsReport on purpose: its keys become the CSV export's column headers.
$famMailingParts = [];
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
    $famMailingParts[$row['fam_ID']] = $family['MailingAddress']
        ?? Family::primaryAddressPartsFromRow($row);
    $rsReport[] = $row;
}

// Exit if no rows returned
$iCountRows = count($rsReport);
if ($iCountRows < 1) {
    RedirectUtils::redirect('FinancialReports.php?ReturnMessage=NoRows&ReportType=Zero%20Givers');
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

        public function startNewPage($fam_ID, $fam_Name, array $mailingParts): float
        {
            global $letterhead, $sDateStart, $sDateEnd;
            $curY = $this->startLetterPageForParts($fam_ID, $fam_Name, $mailingParts, $letterhead);
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

        public function finishPage($curY): void
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
        $famMailing = $famMailingParts[$fam_ID] ?? Family::primaryAddressPartsFromRow($row);
        $curY = $pdf->startNewPage($fam_ID, $fam_Name, $famMailing);

        $pdf->finishPage($curY);
    }

    if (SystemConfig::getIntValue('iPDFOutputType') === 1) {
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
        RedirectUtils::redirect('FinancialReports.php?' . http_build_query($params));
    }
} else {
    echo '[' . $output . '] output selected, but is not known';
}
