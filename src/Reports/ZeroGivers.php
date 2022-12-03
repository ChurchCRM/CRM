<?php
/*******************************************************************************
*
*  filename    : Reports/ZeroGivers.php
*  last change : 2005-03-26
*  description : Creates a PDF with all the tax letters for a particular calendar year.
*  Copyright 2012 Michael Wilt

******************************************************************************/

require '../Include/Config.php';
require '../Include/Functions.php';

use ChurchCRM\dto\SystemConfig;
use ChurchCRM\Reports\ChurchInfoReport;
use ChurchCRM\Utils\InputUtils;
use ChurchCRM\Utils\RedirectUtils;
use ChurchCRM\Authentication\AuthenticationManager;

// Security
if (!AuthenticationManager::GetCurrentUser()->isFinanceEnabled()) {
    RedirectUtils::Redirect('Menu.php');
    exit;
}

// Filter values
$output = InputUtils::LegacyFilterInput($_POST['output']);
$sDateStart = InputUtils::LegacyFilterInput($_POST['DateStart'], 'date');
$sDateEnd = InputUtils::LegacyFilterInput($_POST['DateEnd'], 'date');

$letterhead = InputUtils::LegacyFilterInput($_POST['letterhead']);
$remittance = InputUtils::LegacyFilterInput($_POST['remittance']);

// If CSVAdminOnly option is enabled and user is not admin, redirect to the menu.
if (!AuthenticationManager::GetCurrentUser()->isAdmin() && SystemConfig::getValue('bCSVAdminOnly') && $output != 'pdf') {
    RedirectUtils::Redirect('Menu.php');
    exit;
}

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

// Build SQL Query
// Build SELECT SQL Portion
$sSQL = "SELECT DISTINCT fam_ID, fam_Name, fam_Address1, fam_Address2, fam_City, fam_State, fam_Zip, fam_Country FROM family_fam LEFT OUTER JOIN person_per ON fam_ID = per_fam_ID WHERE per_cls_ID=1 AND fam_ID NOT IN (SELECT DISTINCT plg_FamID FROM pledge_plg WHERE plg_date BETWEEN '$sDateStart' AND '$sDateEnd' AND plg_PledgeOrPayment = 'Payment') ORDER BY fam_ID";

//Execute SQL Statement
$rsReport = RunQuery($sSQL);

// Exit if no rows returned
$iCountRows = mysqli_num_rows($rsReport);
if ($iCountRows < 1) {
    header('Location: ../FinancialReports.php?ReturnMessage=NoRows&ReportType=Zero%20Givers');
}

// Create Giving Report -- PDF
// ***************************

if ($output == 'pdf') {

    // Set up bottom border values
    if ($remittance == 'yes') {
        $bottom_border1 = 134;
        $bottom_border2 = 180;
    } else {
        $bottom_border1 = 200;
        $bottom_border2 = 250;
    }

    class PDF_ZeroGivers extends ChurchInfoReport
    {
        // Constructor
        public function __construct()
        {
            parent::__construct('P', 'mm', $this->paperFormat);
            $this->SetFont('Times', '', 10);
            $this->SetMargins(20, 20);

            $this->SetAutoPageBreak(false);
        }

        public function StartNewPage($fam_ID, $fam_Name, $fam_Address1, $fam_Address2, $fam_City, $fam_State, $fam_Zip, $fam_Country)
        {
            global $letterhead, $sDateStart, $sDateEnd;
            $curY = $this->StartLetterPage($fam_ID, $fam_Name, $fam_Address1, $fam_Address2, $fam_City, $fam_State, $fam_Zip, $fam_Country, $letterhead);
            $curY += 2 * SystemConfig::getValue('incrementY');
            if ($sDateStart == $sDateEnd) {
                $DateString = date('F j, Y', strtotime($sDateStart));
            } else {
                $DateString = date('M j, Y', strtotime($sDateStart)).' - '.date('M j, Y', strtotime($sDateEnd));
            }

            $blurb = SystemConfig::getValue('sTaxReport1').' '.$DateString.' '.SystemConfig::getValue('sZeroGivers');
            $this->WriteAt(SystemConfig::getValue('leftX'), $curY, $blurb);
            $curY += 30 * SystemConfig::getValue('incrementY');

            return $curY;
        }

        public function FinishPage($curY, $fam_ID, $fam_Name, $fam_Address1, $fam_Address2, $fam_City, $fam_State, $fam_Zip, $fam_Country)
        {
            global $remittance;
            $curY += 2 * SystemConfig::getValue('incrementY');
            $blurb = SystemConfig::getValue('sZeroGivers2');
            $this->WriteAt(SystemConfig::getValue('leftX'), $curY, $blurb);
            $curY += 3 * SystemConfig::getValue('incrementY');
            $blurb = SystemConfig::getValue('sZeroGivers3');
            $this->WriteAt(SystemConfig::getValue('leftX'), $curY, $blurb);
            $curY += 3 * SystemConfig::getValue('incrementY');
            $this->WriteAt(SystemConfig::getValue('leftX'), $curY, SystemConfig::getValue('sConfirmSincerely').',');
            $curY += 4 * SystemConfig::getValue('incrementY');
            $this->WriteAt(SystemConfig::getValue('leftX'), $curY, SystemConfig::getValue('sTaxSigner'));
        }
    }

    // Instantiate the directory class and build the report.
    $pdf = new PDF_ZeroGivers();

    // Loop through result array
    while ($row = mysqli_fetch_array($rsReport)) {
        extract($row);
        $curY = $pdf->StartNewPage($fam_ID, $fam_Name, $fam_Address1, $fam_Address2, $fam_City, $fam_State, $fam_Zip, $fam_Country);

        $pdf->FinishPage($curY, $fam_ID, $fam_Name, $fam_Address1, $fam_Address2, $fam_City, $fam_State, $fam_Zip, $fam_Country);
    }

    if (SystemConfig::getValue('iPDFOutputType') == 1) {
        $pdf->Output('ZeroGivers'.date(SystemConfig::getValue("sDateFilenameFormat")).'.pdf', 'D');
    } else {
        $pdf->Output();
    }

    // Output a text file
// ##################
} elseif ($output == 'csv') {

    // Settings
    $delimiter = ',';
    $eol = "\r\n";

    // Build headings row
    eregi('SELECT (.*) FROM ', $sSQL, $result);
    $headings = explode(',', $result[1]);
    $buffer = '';
    foreach ($headings as $heading) {
        $buffer .= trim($heading).$delimiter;
    }
    // Remove trailing delimiter and add eol
    $buffer = mb_substr($buffer, 0, -1).$eol;

    // Add data
    while ($row = mysqli_fetch_row($rsReport)) {
        foreach ($row as $field) {
            $field = str_replace($delimiter, ' ', $field);    // Remove any delimiters from data
            $buffer .= $field.$delimiter;
        }
        // Remove trailing delimiter and add eol
        $buffer = mb_substr($buffer, 0, -1).$eol;
    }

    // Export file
    header('Content-type: text/x-csv');
    header('Content-Disposition: attachment; filename=ChurchCRM-'.date(SystemConfig::getValue("sDateFilenameFormat")).'.csv');
    echo $buffer;
}
