<?php
/*******************************************************************************
*
*  filename    : Reports/AdvancedDeposit.php
*  last change : 2013-02-21
*  description : Creates a PDF customized Deposit Report .

******************************************************************************/

require '../Include/Config.php';
require '../Include/Functions.php';
require '../Include/ReportFunctions.php';

use ChurchCRM\dto\SystemConfig;
use ChurchCRM\Reports\ChurchInfoReport;
use ChurchCRM\Utils\InputUtils;
use ChurchCRM\Utils\RedirectUtils;
use ChurchCRM\ContribQuery;
use ChurchCRM\ContribSplitQuery;
use ChurchCRM\PersonQuery;
use ChurchCRM\Authentication\AuthenticationManager;

// Security
if (!AuthenticationManager::GetCurrentUser()->isFinanceEnabled()) {
    RedirectUtils::Redirect('Menu.php');
    exit;
}

// Filter values
$datetype = InputUtils::LegacyFilterInput($_POST['datetype']);
$output = InputUtils::LegacyFilterInput($_POST['output']);
$sDateStart = InputUtils::LegacyFilterInput($_POST['DateStart'], 'date');
$sDateEnd = InputUtils::LegacyFilterInput($_POST['DateEnd'], 'date');
$iDepID = InputUtils::LegacyFilterInput($_POST['deposit'], 'int');

// If CSVAdminOnly option is enabled and user is not admin, redirect to the menu.
if (!AuthenticationManager::GetCurrentUser()->isAdmin() && SystemConfig::getValue('bCSVAdminOnly') && $output != 'pdf') {
    RedirectUtils::Redirect('Menu.php');
    exit;
}

// Filter by Deposit
if (!empty($_POST['deposit'])) {
    $filterByDepId = true;
    $iDepID = InputUtils::LegacyFilterInput($_POST['deposit'], 'int');
}

// build query based on user selected options
$rsReport = ContribQuery::create()
    ->useDepositQuery()
        // ->withcolumn('Dep_Id','DepId')
        ->withColumn('dep_Date', 'DepDate')
        ->withColumn('dep_Type', 'Type')
        ->withcolumn('dep_Comment', 'DepComment')
    ->endUse()
    ->useContribSplitQuery()
        ->withColumn('SUM(contrib_split.spl_Amount)', 'totalAmount')
    ->endUse()
    ->usePersonQuery()
        ->withColumn("per_Id", "perId")
        ->withColumn("per_LastName", "FirstName")
        ->withColumn("per_MiddleName", "MiddleName")
        ->withColumn("per_LastName", "LastName")
        ->withColumn("per_Address1", "Address1")
    ->endUse()
    ->groupByConId()
    ->groupByDepId()
    ->orderByDepId();

    if ($filterByDepId) {
        $rsReport->useDepositQuery()->filterById($iDepID)->endUse();
    } else {
        $rsReport->useDepositQuery()->filterByDate(array("min" => $sDateStart . " 00:00:00", "max" => $sDateEnd . " 23:59:59"))->endUse();
    }

    $rsReport->find();

// Exit if no rows returned
$iCountRows = $rsReport->count();
if ($iCountRows < 1) {
    header('Location: ../FinancialReports.php?ReturnMessage=NoRows&ReportType=Advanced%20Deposit%20Report');
}

// Create PDF Report -- PDF
// ***************************

if ($output == 'pdf') {
    // Set up bottom border value
    $bottom_border = 250;
    $summaryIntervalY = 4;
    $page = 1;

    class PDF_TaxReport extends ChurchInfoReport
    {
        // Constructor
        public function PDF_TaxReport()
        {
            parent::__construct('P', 'mm', $this->paperFormat);
            $this->SetFont('Times', '', 10);
            $this->SetMargins(20, 15);

            $this->SetAutoPageBreak(false);
        }

        public function PrintRightJustified($x, $y, $str)
        {
            $iLen = strlen($str);
            $nMoveBy = 2 * $iLen;
            $this->SetXY($x - $nMoveBy, $y);
            $this->Write(8, $str);
        }

        public function StartFirstPage()
        {
            global $sDateStart, $sDateEnd, $sort, $iDepID, $datetype;
            $this->AddPage();
            $curY = 20;
            $curX = 60;
            $this->SetFont('Times', 'B', 14);
            $this->WriteAt($curX, $curY, SystemConfig::getValue('sChurchName').' Deposit Report');
            $curY += SystemConfig::getValue('incrementY');
            $this->SetFont('Times', 'B', 10);
            $curX = SystemConfig::getValue('leftX');
            $this->SetX($curX);

            if (!$iDepID) {
                $this->Cell(0, $curY, "$datetype Dates: $sDateStart through $sDateEnd", 0, 0, 'C');
                $curY += SystemConfig::getValue('incrementY');
            }
            
            $curY += 2 * SystemConfig::getValue('incrementY');
            $this->WriteAt($curX, $curY, $heading);
            $curY += 2 * SystemConfig::getValue('incrementY');
            $this->SetFont('Times', '', 10);

            return $curY;
        }

        public function PageBreak($page)
        {
            // Finish footer of previous page if neccessary and add new page
            global $curY, $bottom_border;
            if ($curY > $bottom_border) {
                $this->FinishPage($page);
                $page++;
                $this->AddPage();
                $curY = 20;
                $curY = $this->Headings($curY);
            }

            return $page;
        }

        public function Headings($curY)
        {
            global $summaryIntervalY;
            
            // $curX = SystemConfig::getValue('leftX');
            $this->SetFont('Times', 'BU', 10);
            $this->SetXY(SystemConfig::getValue('leftX'), $curY);
            $this->Cell(10, $summaryIntervalY, 'Chk No.', 0, 0, 'R');
            $this->Cell(10, $summaryIntervalY); // space
            $this->Cell(70, $summaryIntervalY, 'Recieved From');
            $this->Cell(65, $summaryIntervalY, 'Memo');
            $this->Cell(20, $summaryIntervalY, 'Amount', 0, 0, 'R');
            $curY += 2 * $summaryIntervalY;
            
            return $curY;
        }

        public function FinishPage($page)
        {
            $footer = "Page $page   Generated on ".date(SystemConfig::getValue("sDateTimeFormat"));
            $this->SetFont('Times', 'I', 9);
            $this->WriteAt(80, 258, $footer);
        }
    }

    // Instantiate the directory class and build the report.
    $pdf = new PDF_TaxReport();

    $curY = $pdf->StartFirstPage();
    $curX = 0;

    $currentDepositID = 0;
    $currentFundID = 0;
    $totalAmount = 0;
    $totalFund = [];

    $countFund = 0;
    $countDeposit = 0;
    $countReport = 0;
    $currentFundAmount = 0;
    $currentDepositAmount = 0;
    $currentReportAmount = 0;

    $curY = $pdf->Headings($curY);

    foreach ($rsReport as $aRow) {
        $fam_ID = $aRow->getperId();
        $dep_Date = $aRow->getDepDate();
        $plg_depID = $aRow->getDepId();
        $plg_method = $aRow->getMethod();
        $plg_CheckNo = $aRow->getCheckNo();
        $plg_comment = $aRow->getComment();
        $fam_Name = PersonQuery::create()->filterById($fam_ID)->findOne()->getFormattedName(9);
        $fam_Address1 = $aRow->getAddress1();
        $plg_amount = $aRow->gettotalAmount();
        $dep_Comment = $aRow->getDepComment();

        // First Deposit Heading
        if (!$currentDepositID) {
            $pdf->SetXY(SystemConfig::getValue('leftX')-5, $curY - 15);
            $sDepositTitle = "Deposit #$plg_depID ($dep_Date)";
            $sDepositTitle .=  empty($dep_Comment) ? "" : " - $dep_Comment";
            $pdf->SetFont('Times', 'B', 10);
            $pdf->Cell(0, $summaryIntervalY, $sDepositTitle, 0, 0, 'L');
        }

        // Check for new deposit
        if ($currentDepositID != $plg_depID && $currentDepositID) {
            // New Deposit ID.  Print Previous Deposit Summary
            if ($countDeposit > 1) {
                $item = gettext('items');
            } else {
                $item = gettext('item');
            }
            $sDepositSummary = "Deposit #$currentDepositID Total - $countDeposit $item:   $".number_format($currentDepositAmount, 2, '.', ',');
            $pdf->SetXY(SystemConfig::getValue('leftX'), $curY);
            $pdf->SetFont('Times', 'B', 10);
            $pdf->Cell(175, $summaryIntervalY, $sDepositSummary, 0, 0, 'R');
            $curY += 2 * $summaryIntervalY;
            $pdf->line(40, $curY - 2, 195, $curY - 2);
            $page = $pdf->PageBreak($page);

            // New Deposit Title
            $pdf->SetXY(SystemConfig::getValue('leftX')-5, $curY);
            $sDepositTitle = "Deposit #$plg_depID ($dep_Date)";
            $sDepositTitle .=  empty($dep_Comment) ? "" : " - $dep_Comment";
            $pdf->SetFont('Times', 'B', 10);
            $pdf->Cell(0, $summaryIntervalY, $sDepositTitle);
            $curY += 1.5 * $summaryIntervalY;
            $countDeposit = 0;
            $currentDepositAmount = 0;

            // start new heading
            $curY = $pdf->Headings($curY);
        }

        // Print Deposit Detail
        if ($plg_method == 'CREDITCARD') {
            $plg_method = 'CREDIT';
        }
        if ($plg_method == 'BANKDRAFT') {
            $plg_method = 'DRAFT';
        }
        if ($plg_method != 'CHECK') {
            $plg_CheckNo = $plg_method;
        }
        if (strlen($plg_CheckNo) > 8) {
            $plg_CheckNo = '...'.mb_substr($plg_CheckNo, -8, 8);
        }
        if (strlen($plg_comment) > 29) {
            $plg_comment = mb_substr($plg_comment, 0, 28).'...';
        }
        $fam_Name = $fam_Name.' - '.$fam_Address1;
        if (strlen($fam_Name) > 51) {
            $fam_Name = mb_substr($fam_Name, 0, 50).'...';
        }

        // Print Data
        $pdf->SetFont('Times', '', 10);
        $pdf->SetXY(SystemConfig::getValue('leftX'), $curY);
        $pdf->Cell(10, $summaryIntervalY, $plg_CheckNo, 0, 0, 'R');
        $pdf->Cell(10, $summaryIntervalY);
        $pdf->Cell(70, $summaryIntervalY, $fam_Name);
        $pdf->Cell(65, $summaryIntervalY, $plg_comment);
        $pdf->SetFont('Courier', '', 9);
        $pdf->Cell(20, $summaryIntervalY, $plg_amount, 0, 0, 'R');
        $pdf->SetFont('Times', '', 10);
        $curY += $summaryIntervalY;
        $page = $pdf->PageBreak($page);

        // Update running totals
        $totalAmount += $plg_amount;
        if (array_key_exists($fun_Name, $totalFund)) {
            $totalFund[$fun_Name] += $plg_amount;
        } else {
            $totalFund[$fun_Name] = $plg_amount;
        }
        $countFund++;
        $countDeposit++;
        $countReport++;
        $currentFundAmount += $plg_amount;
        $currentDepositAmount += $plg_amount;
        $currentReportAmount += $plg_amount;
        $currentDepositID = $plg_depID;
        $currentFundName = $fun_Name;
        $currentDepositDate = $dep_Date;
    }

    // Print Deposit Summary
    if ($countDeposit > 1) {
        $item = gettext('items');
    } else {
        $item = gettext('item');
    }
    $sDepositSummary = "Deposit #$currentDepositID Total - $countDeposit $item:   $".number_format($currentDepositAmount, 2, '.', ',');
    $pdf->SetXY(20, $curY);
    $pdf->SetFont('Times', 'B', 10);
    $pdf->Cell(175, $summaryIntervalY, $sDepositSummary, 0, 0, 'R');
    $curY += 2 * $summaryIntervalY;
    $page = $pdf->PageBreak($page);
     

    // Print Report Summary
    if ($countReport > 1) {
        $item = gettext('items');
    } else {
        $item = gettext('item');
    }
    $sReportSummary = "Report Total ($countReport $item):   $".number_format($currentReportAmount, 2, '.', ',');
    $pdf->SetXY(20, $curY);
    $pdf->SetFont('Times', 'B', 10);
    $pdf->Cell(175, $summaryIntervalY, $sReportSummary, 0, 0, 'R');
    $pdf->line(40, $curY - 2, 195, $curY - 2);
    $curY += 2.5 * $summaryIntervalY;
    $page = $pdf->PageBreak($page);

    // Print Fund Totals
    $pdf->SetFont('Times', 'B', 10);
    $pdf->SetXY($curX, $curY);
    $pdf->WriteAt(20, $curY, 'Deposit totals by fund');
    $pdf->SetFont('Courier', '', 10);
    $curY += 1.5 * $summaryIntervalY;

    $fundList = ContribSplitQuery::create()
        ->withColumn('SUM(contrib_split.spl_Amount)', 'totalAmount')
        ->groupByFundId()
        ->useDonationFundQuery()
            ->withColumn('DonationFund.Name', 'Name')
        ->endUse()
        ->useContribQuery()
        ->endUse();

    if ($filterByDepId) {
        $fundList->useContribQuery()->useDepositQuery()->filterById($iDepID)->endUse()->endUse();
    } else {
        $fundList->useContribQuery()->useDepositQuery()->filterByDate(array("min" => $sDateStart . " 00:00:00", "max" => $sDateEnd . " 23:59:59"))->endUse()->endUse();
    }
    
    $fundList->find();

    foreach ($fundList as $fund) {
        $pdf->SetXY(22, $curY);
        $pdf->Cell(45, $summaryIntervalY, $fund->getName());
        $pdf->Cell(25, $summaryIntervalY, number_format($fund->gettotalAmount(), 2, '.', ','), 0, 0, 'R');
        $curY += $summaryIntervalY;
    }

    $page = $pdf->PageBreak($page);


    $pdf->FinishPage($page);
    $pdf->Output('DepositReport-'.date(SystemConfig::getValue("sDateFilenameFormat")).'.pdf', 'D');

// Output a text file
// ##################
} elseif ($output == 'csv') {

    // Settings
    $delimiter = ',';
    $eol = "\r\n";

    // Build headings row
    $headings = ['Deposit #','Deposit Date','Chk No.','Last Name', 'First Name', 'Middle Name', 'AddressLine1', 'Memo', 'Amount'];
    // eregi('SELECT (.*) FROM ', $sSQL, $result);
    // $headings = explode(',', $result[1]);
    $buffer = '';
    foreach ($headings as $heading) {
        $buffer .= trim($heading).$delimiter;
    }
    // Remove trailing delimiter and add eol
    $buffer = mb_substr($buffer, 0, -1).$eol;

    // Add data
    foreach ($rsReport as $row) {
        // quote data rather than removing ','
        $buffer .= $row->getDepId().$delimiter.$row->getDepDate().$delimiter.$row->getCheckNo().$delimiter.$row->getLastName().$delimiter.$row->getFirstName().$delimiter.$row->getMiddleName().$delimiter.$row->getAddress1().$delimiter;
        $buffer .= '"'.$row->getComment().'"'.$delimiter.$row->gettotalAmount().$eol;
    }


    // Export file
    header('Content-type: text/x-csv');
    header("Content-Disposition: attachment; filename='ChurchCRM".date(SystemConfig::getValue("sDateFilenameFormat")).'.csv');
    echo $buffer;
}
