<?php
/*******************************************************************************
*
*  filename    : Reports/TaxReportCanadian.php
*  Created by  : Troy Smith
*  Created on  : 2019-08-20
*  description : Creates a PDF with all the tax letters for a particular calendar year.

******************************************************************************/

require '../Include/Config.php';
require '../Include/Functions.php';
require '../Include/ReportFunctions.php';

use ChurchCRM\dto\SystemConfig;
use ChurchCRM\Reports\ChurchInfoReport;
use ChurchCRM\Utils\InputUtils;
use ChurchCRM\Utils\RedirectUtils;
use ChurchCRM\ContribSplitQuery;
use ChurchCRM\PersonQuery;
use ChurchCRM\Authentication\AuthenticationManager;

// Security 
if (!AuthenticationManager::GetCurrentUser()->isFinanceEnabled()) {
    RedirectUtils::Redirect('Menu.php');
    exit;
}

// Filter values
$letterhead = InputUtils::LegacyFilterInput($_POST['letterhead']);
$remittance = InputUtils::LegacyFilterInput($_POST['remittance']);
$output = InputUtils::LegacyFilterInput($_POST['output']);
$sReportType = InputUtils::LegacyFilterInput($_POST['ReportType']);
$sDateStart = InputUtils::LegacyFilterInput($_POST['DateStart'], 'date');
$sDateEnd = InputUtils::LegacyFilterInput($_POST['DateEnd'], 'date');
$iMinimum = InputUtils::LegacyFilterInput($_POST['minimum'], 'int');
$iSerialNum = InputUtils::LegacyFilterInput($_POST['serialnum'], 'int');
$Nondeductible = InputUtils::LegacyFilterInput($_POST['nondeductible'], 'int');

// If CSVAdminOnly option is enabled and user is not admin, redirect to the menu.
if (!AuthenticationManager::GetCurrentUser()->isAdmin() && SystemConfig::getValue('bCSVAdminOnly') && $output != 'pdf') {
    RedirectUtils::Redirect('Menu.php');
    exit;
}

// Filter by Classification
if (!empty($_POST['classList'])) {
    $filterByClsId = true;
    foreach ($_POST['classList'] as $class) {
        $classList[] = InputUtils::LegacyFilterInput($class, 'int');
    }
}

// Filter by Fund
if (!empty($_POST['funds'])) {
    $filterByFundId = true;
    foreach ($_POST['funds'] as $fund) {
        $fundID[] = InputUtils::LegacyFilterInput($fund, 'int');
    }
}

// Filter by Person
if (!empty($_POST['person'])) {
    $filterById = true;
    foreach ($_POST['person'] as $perID) {
        $per[] = InputUtils::LegacyFilterInput($perID, 'int');
    }
}

// filter by Non-deductible;
if (!empty($_POST['nondeductible'])) {
    $filterByNondeductible = true;
}

// get list of people id's with total contribution < amount specified
// Mariadb 10.2 supports OVER clause making it possible to combine with the next query
// this is a temopary work around
if (!empty($_POST['minimum'])) {
    $filterByAmount = true;
    $minimum = InputUtils::LegacyFilterInput($_POST['minimum'], 'int');
  
    // get list of people ID who's contribution total is < minimum
    $contribList = ContribSplitQuery::create()
            ->useContribQuery()
                ->filterByDate(array("min" => $sDateStart . " 00:00:00", "max" => $sDateEnd . " 23:59:59"))
            ->endUse()
        // ->select("contrib_split.spl_ConID", "ConId")
        // ->withColumn('SUM(contrib_split.spl_Amount)', 'totalAmount')
        ->filterByNonDeductible(false)
        ->having('SUM(contrib_split.spl_Amount) < ?',$minimum)
        ->groupByConId()
        ->find();

    $filter = [];
    foreach ($contribList as $con){
        $filter[] = $con->getConId();
    }
}
// echo print_r($min);
// build query based on user selected options
$contributions = ContribSplitQuery::create()
        ->useContribQuery()
            ->filterByDate(array("min" => $sDateStart . " 00:00:00", "max" => $sDateEnd . " 23:59:59"))
            ->withColumn("con_Date", "Date")
            ->withColumn("con_CheckNo", "CheckNo")
            ->withColumn("con_Method", "Method")
            ->withColumn("con_Comment", "Comment")
            ->withColumn("con_Date", "Date")
            ->usePersonQuery()
                ->withColumn("per_Id", "perId")
                ->withColumn("per_FirstName", "FirstName")
                ->withColumn("per_MiddleName", "MiddleName")
                ->withColumn("per_LastName", "LastName")
                ->withColumn("per_Address1", "Address1")
                ->withColumn("per_Address2", "Address2")
                ->withColumn("per_City", "City")
                ->withColumn("per_State", "State")
                ->withColumn("per_Zip", "Zip")
                ->withColumn("per_Country", "Country")
                ->withColumn("per_Envelope", "Envelope")
                ->groupById()
            ->endUse()
        ->enduse()
        ->joinDonationFund()->useDonationFundQuery()
            ->withColumn('DonationFund.Name', 'Name')
        ->endUse()
    ->groupByFundId()
    ->groupById()
    ->orderByLastName();
    
    // apply user defined filters
    if (!$filterByNondeductible){
        // filter out Non-deductible by default
        $contributions->filterByNonDeductible(false); 
    }
    if ($filterById) {
        $contributions->filterById([$per]);
    }
    if ($filterByClsId) {
        $contributions->useContribQuery()->usePersonQuery()->filterByClsId($classList)->endUse()->endUse();
    }
    if ($filterByFundId) {
        $contributions->joinDonationFund()->useDonationFundQuery()->filterById($fundID)->endUse();
    }
    if ($filterByAmount) {
        $contributions->where('contrib_split.spl_ConID NOT IN ?', $filter);
    }
    $contributions->find();


// Exit if no rows returned
$iCountRows = $contributions->count();
if ($iCountRows < 1) {
    header('Location: ../FinancialReports.php?ReturnMessage=NoRows&ReportType=Canadian%20Tax%20Receipt');
}

// Create Canadian Tax Receipt -- PDF
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

    class PDF_TaxReport extends ChurchInfoReport
    {
        // Constructor
        public function __construct()
        {
            parent::__construct('P', 'mm', $this->paperFormat);
            $this->SetFont('Times', '', 10);
            $this->SetMargins(SystemConfig::getValue('leftX'), SystemConfig::getValue('leftX'));

            $this->SetAutoPageBreak(false);
        }

        public function StartNewPage($fam_ID, $fam_Name, $fam_Address1, $fam_Address2, $fam_City, $fam_State, $fam_Zip, $fam_Country, $fam_envelope)
        {
            global $letterhead, $sDateStart, $sDateEnd, $iDepID, $fam_envelope, $iSerialNum, $sReportType;
            $curY = $this->StartLetterPage($fam_ID, $fam_Name, $fam_Address1, $fam_Address2, $fam_City, $fam_State, $fam_Zip, $fam_Country, $letterhead, $sReportType, $iSerialNum++);
            // if (SystemConfig::getValue('bUseDonationEnvelopes')) {
            //     $this->WriteAt(SystemConfig::getValue('leftX'), $curY, gettext('Envelope:').$fam_envelope);
            // }
            $curY += 2 * SystemConfig::getValue('incrementY');
            if ($sDateStart == $sDateEnd) {
                $DateString = date('F j, Y', strtotime($sDateStart));
            } else {
                $DateString = date('M j, Y', strtotime($sDateStart)).' - '.date('M j, Y', strtotime($sDateEnd));
            }
            $blurb = SystemConfig::getValue('sTaxReport1').' '.$DateString.'.';
            $this->WriteAt(SystemConfig::getValue('leftX'), $curY, $blurb);
            $curY += 2 * SystemConfig::getValue('incrementY');
            
            return $curY;
        }

        public function FinishPage($curY, $fam_ID, $fam_Name, $fam_Address1, $fam_Address2, $fam_City, $fam_State, $fam_Zip, $fam_Country)
        {
            global $remittance;
            $curY += 2 * SystemConfig::getValue('incrementY');
            $blurb = SystemConfig::getValue('sTaxReport2');
            $this->WriteAt(SystemConfig::getValue('leftX'), $curY, $blurb);
            $curY += 3 * SystemConfig::getValue('incrementY');
            $blurb = SystemConfig::getValue('sTaxReport3');
            $this->WriteAt(SystemConfig::getValue('leftX'), $curY, $blurb);
            $curY += 3 * SystemConfig::getValue('incrementY');
            $this->WriteAt(SystemConfig::getValue('leftX'), $curY, SystemConfig::getValue('sConfirmSincerely').',');
            $curY += 4 * SystemConfig::getValue('incrementY');
            $this->WriteAt(SystemConfig::getValue('leftX'), $curY, SystemConfig::getValue('sTaxSigner'));
            if (is_readable(SystemConfig::getValue('sTaxSignerImg'))) {
                $this->Image(SystemConfig::getValue('sTaxSignerImg'), $this->GetX()+5, $curY-3, 50, 10);
            }

            // if ($remittance == 'yes') {
            //     // Add remittance slip
            //     $curY = 194;
            //     $curX = 60;
            //     $this->WriteAt($curX, $curY, gettext('Please detach this slip and mail with your next gift.'));
            //     $curY += (1.5 * SystemConfig::getValue('incrementY'));
            //     $church_mailing = gettext('Please mail you next gift to ').SystemConfig::getValue('sChurchName').', '
            //         .SystemConfig::getValue('sChurchAddress').', '.SystemConfig::getValue('sChurchCity').', '.SystemConfig::getValue('sChurchState').'  '
            //         .SystemConfig::getValue('sChurchZip').gettext(', Phone: ').SystemConfig::getValue('sChurchPhone');
            //     $this->SetFont('Times', 'I', 10);
            //     $this->WriteAt(SystemConfig::getValue('leftX'), $curY, $church_mailing);
            //     $this->SetFont('Times', '', 10);
            //     $curY = 215;
            //     $this->WriteAt(SystemConfig::getValue('leftX'), $curY, PersonQuery::create()->filterById($fam_ID)->findOne()->getFormattedName(9));
            //     $curY += SystemConfig::getValue('incrementY');
            //     if ($fam_Address1 != '') {
            //         $this->WriteAt(SystemConfig::getValue('leftX'), $curY, $fam_Address1);
            //         $curY += SystemConfig::getValue('incrementY');
            //     }
            //     if ($fam_Address2 != '') {
            //         $this->WriteAt(SystemConfig::getValue('leftX'), $curY, $fam_Address2);
            //         $curY += SystemConfig::getValue('incrementY');
            //     }
            //     $this->WriteAt(SystemConfig::getValue('leftX'), $curY, $fam_City.', '.$fam_State.'  '.$fam_Zip);
            //     $curY += SystemConfig::getValue('incrementY');
            //     if ($fam_Country != '' && $fam_Country != 'USA' && $fam_Country != 'United States') {
            //         $this->WriteAt(SystemConfig::getValue('leftX'), $curY, $fam_Country);
            //         $curY += SystemConfig::getValue('incrementY');
            //     }
            //     $curX = 30;
            //     $curY = 246;
            //     $this->WriteAt(SystemConfig::getValue('leftX') + 5, $curY, SystemConfig::getValue('sChurchName'));
            //     $curY += SystemConfig::getValue('incrementY');
            //     if (SystemConfig::getValue('sChurchAddress') != '') {
            //         $this->WriteAt(SystemConfig::getValue('leftX') + 5, $curY, SystemConfig::getValue('sChurchAddress'));
            //         $curY += SystemConfig::getValue('incrementY');
            //     }
            //     $this->WriteAt(SystemConfig::getValue('leftX') + 5, $curY, SystemConfig::getValue('sChurchCity').', '.SystemConfig::getValue('sChurchState').'  '.SystemConfig::getValue('sChurchZip'));
            //     $curY += SystemConfig::getValue('incrementY');
            //     if ($fam_Country != '' && $fam_Country != 'USA' && $fam_Country != 'United States') {
            //         $this->WriteAt(SystemConfig::getValue('leftX') + 5, $curY, $fam_Country);
            //         $curY += SystemConfig::getValue('incrementY');
            //     }

            //     $curX = 100;
            //     $curY = 215;
            //     $this->WriteAt($curX, $curY, gettext('Gift Amount:'));
            //     $this->WriteAt($curX + 25, $curY, '_______________________________');
            //     $curY += (2 * SystemConfig::getValue('incrementY'));
            //     $this->WriteAt($curX, $curY, gettext('Gift Designation:'));
            //     $this->WriteAt($curX + 25, $curY, '_______________________________');
            //     $curY = 200 + (11 * SystemConfig::getValue('incrementY'));
            // }
        }

        public function Footer()
        {
            global $sReportType;
            // Position at 1.5 cm from bottom
            $this->SetY(-15);
            // Arial italic 8
            $this->SetFont('Arial', 'I', 8);
            // line
            $this->Line(SystemConfig::getValue('leftX'), $this->GetPageHeight()-15, 210-SystemConfig::getValue('leftX'), $this->GetPageHeight()-15); // 20mm from each edge
            // report name
            $this->WriteAt(SystemConfig::getValue('leftX'), $this->GetPageHeight()-15, $sReportType);
            $this->WriteAt($this->GetPageWidth()-SystemConfig::getValue('leftX')*2-2, $this->GetPageHeight()-15, date(SystemConfig::getValue("sDatePickerFormat")));
            // Page number
            // $this->Cell(0,10,'Page '.$this->PageNo() ,0,0,'C');
        }
    }

    
    // Instantiate the directory class and build the report.
    $pdf = new PDF_TaxReport();

    // Loop through result array
    $sum=0;
    $currentFamilyID = 0;
    foreach ($contributions as $row) {
        $fam_ID = $row->getperId();
        $fam_Name = PersonQuery::create()->filterById($fam_ID)->findOne()->getFormattedName(9);
        $fam_Address1 = $row->getAddress1();
        $fam_Address2 = $row->getAddress2();
        $fam_City = $row->getCity();
        $fam_State = $row->getState();
        $fam_Zip = $row->getZip();
        $fam_Country = $row->getCountry();
        $fam_envelope = $row->getEnvelope();
        $plg_date = $row->getDate();
        $plg_CheckNo = $row->getCheckNo();
        $plg_method = $row->getMethod();
        $fun_Name = $row->getName();
        $plg_comment = $row->getComment();
        $plg_amount = $row->getAmount();
        $plg_NonDeductible = $row->getNonDeductible();

        if ($fam_ID != $currentFamilyID && $currentFamilyID != 0) {
            //New Family. Finish Previous Family
            
            $pdf->SetFont('Times', 'B', 10);
            $pdf->Cell(20, $summaryIntervalY / 2, ' ', 0, 1);
            $pdf->Cell(95, $summaryIntervalY, ' ');
            $pdf->Cell(50, $summaryIntervalY, 'Tax-Deductible Contribution:');
            $totalAmountStr = '$'.number_format($totalAmount, 2);
            // $pdf->SetFont('Courier', '', 9);
            $pdf->Cell(25, $summaryIntervalY, $totalAmountStr, 0, 1, 'R');

            // $pdf->SetFont('Times', 'B', 10);
            // $pdf->Cell(95, $summaryIntervalY, ' ');
            // $pdf->Cell(50, $summaryIntervalY, 'Goods and Services Rendered:');
            // $totalAmountStr = '$'.number_format($totalNonDeductible, 2);
            // $pdf->SetFont('Courier', '', 9);
            // $pdf->Cell(25, $summaryIntervalY, $totalAmountStr, 0, 1, 'R');

            // $pdf->SetFont('Times', 'B', 10);
            // $pdf->Cell(95, $summaryIntervalY, ' ');
            // $pdf->Cell(50, $summaryIntervalY, 'Tax-Deductible Contribution:');
            // $totalAmountStr = '$'.number_format($totalAmount - $totalNonDeductible, 2);
            // $pdf->SetFont('Courier', '', 9);
            // $pdf->Cell(25, $summaryIntervalY, $totalAmountStr, 0, 1, 'R');
            //$curY = $pdf->GetY();
            $curY = $pdf->GetY();
            
            if ($curY > $bottom_border1) {
                $pdf->AddPage();
                if ($letterhead == 'none') {
                    // Leave blank space at top on all pages for pre-printed letterhead
                    $curY = 20 + ($summaryIntervalY * 3) + 25;
                    $pdf->SetY($curY);
                } else {
                    $curY = 20;
                    $pdf->SetY(20);
                }
            }
            $pdf->SetFont('Times', '', 10);
            $pdf->FinishPage($curY, $prev_fam_ID, $prev_fam_Name, $prev_fam_Address1, $prev_fam_Address2, $prev_fam_City, $prev_fam_State, $prev_fam_Zip, $prev_fam_Country);
        }

        // Start Page for New Contributor
        if ($fam_ID != $currentFamilyID) {
            $curY = $pdf->StartNewPage($fam_ID, $fam_Name, $fam_Address1, $fam_Address2, $fam_City, $fam_State, $fam_Zip, $fam_Country, $fam_envelope);
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
            // $totalNonDeductible = 0;
            $cnt = 0;
            $currentFamilyID = $fam_ID;
        }
        // Format Data
        if (strlen($plg_CheckNo) > 8) {
            $plg_CheckNo = '...'.mb_substr($plg_CheckNo, -8, 8);
        } else {
            $plg_CheckNo .= '    ';
        }
        if (strlen($fun_Name) > 25) {
            $fun_Name = mb_substr($fun_Name, 0, 25).'...';
        }
        if (strlen($plg_comment) > 25) {
            $plg_comment = mb_substr($plg_comment, 0, 25).'...';
        }
        // fill every other row
        if ($cnt % 2 == 0) {
            $fill=true;
            $pdf->setFillColor(230, 230, 230);
        } else {
            $fill=false;
        }
        // identify non-deductible
        if ($plg_NonDeductible) {
            $pdf->SetTextColor(255,0,0);
        } else {
            $pdf->SetTextColor(0,0,0);
        }

        // Print Gift Data
        $pdf->SetFont('Times', '', 10);
        $pdf->Cell(20, $summaryIntervalY, $plg_date, 0, 0, '', $fill);
        $pdf->Cell(20, $summaryIntervalY, $plg_CheckNo, 0, 0, 'R', $fill);
        $pdf->Cell(25, $summaryIntervalY, $plg_method, 0, 0, '', $fill);
        $pdf->Cell(40, $summaryIntervalY, $fun_Name, 0, 0, '', $fill);
        $pdf->Cell(40, $summaryIntervalY, $plg_comment, 0, 0, '', $fill);
        // $pdf->SetFont('Courier', '', 9);
        $pdf->Cell(25, $summaryIntervalY, $plg_amount, 0, 1, 'R', $fill);

        $totalAmount += $plg_amount;
        // $totalNonDeductible += $plg_NonDeductible;
        $cnt += 1;
        $curY = $pdf->GetY();

        if ($curY > $bottom_border2) {
            $pdf->AddPage();
            if ($letterhead == 'none') {
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
        // insure text color
        $pdf->SetTextColor(0,0,0);
    }

    // Finish Last Report
    $pdf->SetFont('Times', 'B', 10);
    $pdf->Cell(20, $summaryIntervalY / 2, ' ', 0, 1);
    $pdf->Cell(95, $summaryIntervalY, ' ');
    $pdf->Cell(50, $summaryIntervalY, 'Tax-Deductible Contribution:');
    $totalAmountStr = '$'.number_format($totalAmount, 2);
    // $pdf->SetFont('Courier', '', 9);
    $pdf->Cell(25, $summaryIntervalY, $totalAmountStr, 0, 1, 'R');
    // $pdf->SetFont('Times', 'B', 10);
    // $pdf->Cell(95, $summaryIntervalY, ' ');
    // $pdf->Cell(50, $summaryIntervalY, 'Goods and Services Rendered:');
    // $totalAmountStr = '$'.number_format($totalNonDeductible, 2);
    // $pdf->SetFont('Courier', '', 9);
    // $pdf->Cell(25, $summaryIntervalY, $totalAmountStr, 0, 1, 'R');
    // $pdf->SetFont('Times', 'B', 10);
    // $pdf->Cell(95, $summaryIntervalY, ' ');
    // $pdf->Cell(50, $summaryIntervalY, 'Tax-Deductible Contribution:');
    // $totalAmountStr = '$'.number_format($totalAmount - $totalNonDeductible, 2);
    // $pdf->SetFont('Courier', '', 9);
    // $pdf->Cell(25, $summaryIntervalY, $totalAmountStr, 0, 1, 'R');
    $curY = $pdf->GetY();

    if ($cnt > 0) {
        if ($curY > $bottom_border1) {
            $pdf->AddPage();
            if ($letterhead == 'none') {
                // Leave blank space at top on all pages for pre-printed letterhead
                $curY = 20 + ($summaryIntervalY * 3) + 25;
                $pdf->SetY($curY);
            } else {
                $curY = 20;
                $pdf->SetY(20);
            }
        }
        $pdf->SetFont('Times', '', 10);
        $pdf->FinishPage($curY, $fam_ID, $fam_Name, $fam_Address1, $fam_Address2, $fam_City, $fam_State, $fam_Zip, $fam_Country);
    }

    header('Pragma: public');  // Needed for IE when using a shared SSL certificate
    ob_clean();
    if (SystemConfig::getValue('iPDFOutputType') == 1) {
        $pdf->Output('TaxReport'.date(SystemConfig::getValue("sDateFilenameFormat")).'.pdf', 'D');
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
    $headings = ['Id','Name', 'Fund', 'Amount'];

    $buffer = '';
    foreach ($headings as $heading) {
        $buffer .= trim($heading).$delimiter;
    }
    // Remove trailing delimiter and add eol
    $buffer = mb_substr($buffer, 0, -1).$eol;

    // Add data
    foreach ($contributions as $row) {
        $buffer .= $row->getperId().$delimiter.PersonQuery::create()->filterById($row->getperId())->findOne()->getFormattedName(9).$delimiter.$row->getName().$delimiter.$row->getAmount().$eol;
    }
    // Export file
    header('Content-type: text/x-csv');
    header('Content-Disposition: attachment; filename=ChurchCRM-'.date(SystemConfig::getValue("sDateFilenameFormat")).'.csv');
    echo $buffer;
}
