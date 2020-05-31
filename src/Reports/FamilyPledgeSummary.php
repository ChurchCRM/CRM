<?php
/*******************************************************************************
*
*  filename    : Reports/FamilyPledgeSummary.php
*  last change : 2005-03-26
*  description : Creates a PDF summary of pledge status by family
*  Copyright 2004-2009  Michael Wilt

******************************************************************************/

require '../Include/Config.php';
require '../Include/Functions.php';
require '../Include/ReportFunctions.php';

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
                if ($inClassList == '(') {
                    $inClassList .= $lst_OptionID;
                } else {
                    $inClassList .= ','.$lst_OptionID;
                }
            } else {
                if ($notInClassList == '(') {
                    $notInClassList .= $lst_OptionID;
                } else {
                    $notInClassList .= ','.$lst_OptionID;
                }
            }
        }
        $inClassList .= ')';
        $notInClassList .= ')';
    }
}

//Get the Fiscal Year ID out of the querystring
$iFYID = InputUtils::LegacyFilterInput($_POST['FYID'], 'int');
$_SESSION['idefaultFY'] = $iFYID; // Remember the chosen FYID
$output = InputUtils::LegacyFilterInput($_POST['output']);
$pledge_filter = '';
if (array_key_exists('pledge_filter', $_POST)) {
    $pledge_filter = InputUtils::LegacyFilterInput($_POST['pledge_filter']);
}
$only_owe = '';
if (array_key_exists('only_owe', $_POST)) {
    $only_owe = InputUtils::LegacyFilterInput($_POST['only_owe']);
}

// If CSVAdminOnly option is enabled and user is not admin, redirect to the menu.
if (!AuthenticationManager::GetCurrentUser()->isAdmin() && SystemConfig::getValue('bCSVAdminOnly')) {
    RedirectUtils::Redirect('Menu.php');
    exit;
}

// Get all the families
$sSQL = 'SELECT DISTINCT fam_ID, fam_Name FROM family_fam';

if ($classList[0]) {
    $sSQL .= ' LEFT JOIN person_per ON fam_ID=per_fam_ID';
}
$sSQL .= ' WHERE';

$criteria = '';
if ($classList[0]) {
    $q = ' per_cls_ID IN '.$inClassList.' AND per_fam_ID NOT IN (SELECT DISTINCT per_fam_ID FROM person_per WHERE per_cls_ID IN '.$notInClassList.')';
    if ($criteria) {
        $criteria .= ' AND'.$q;
    } else {
        $criteria = $q;
    }
}

if (!$criteria) {
    $criteria = ' 1';
}
$sSQL .= $criteria.' ORDER BY fam_Name';

// Filter by Family
if (!empty($_POST['family'])) {
    $count = 0;
    foreach ($_POST['family'] as $famID) {
        $fam[$count++] = InputUtils::LegacyFilterInput($famID, 'int');
    }
    if ($count == 1) {
        if ($fam[0]) {
            $sSQL .= " AND fam_ID='$fam[0]' ";
        }
    } else {
        $sSQL .= " AND (fam_ID='$fam[0]'";
        for ($i = 1; $i < $count; $i++) {
            $sSQL .= " OR fam_ID='$fam[$i]'";
        }
        $sSQL .= ') ';
    }
}
$rsFamilies = RunQuery($sSQL);

$sSQLFundCriteria = '';

// Build criteria string for funds
if (!empty($_POST['funds'])) {
    $fundCount = 0;
    foreach ($_POST['funds'] as $fundID) {
        $fund[$fundCount++] = InputUtils::LegacyFilterInput($fundID, 'int');
    }
    if ($fundCount == 1) {
        if ($fund[0]) {
            $sSQLFundCriteria .= " AND plg_fundID='$fund[0]' ";
        }
    } else {
        $sSQLFundCriteria .= " AND (plg_fundID ='$fund[0]'";
        for ($i = 1; $i < $fundCount; $i++) {
            $sSQLFundCriteria .= " OR plg_fundID='$fund[$i]'";
        }
        $sSQLFundCriteria .= ') ';
    }
}

// Make the string describing the fund filter
if ($fundCount > 0) {
    if ($fundCount == 1) {
        if ($fund[0] == gettext('All Funds')) {
            $fundOnlyString = gettext(' for all funds');
        } else {
            $fundOnlyString = gettext(' for fund ');
        }
    } else {
        $fundOnlyString = gettext('for funds ');
    }
    for ($i = 0; $i < $fundCount; $i++) {
        $sSQL = 'SELECT fun_Name FROM donationfund_fun WHERE fun_ID='.$fund[$i];
        $rsOneFund = RunQuery($sSQL);
        $aFundName = mysqli_fetch_array($rsOneFund);
        $fundOnlyString .= $aFundName['fun_Name'];
        if ($i < $fundCount - 1) {
            $fundOnlyString .= ', ';
        }
    }
}

// Get the list of funds
$sSQL = 'SELECT fun_ID,fun_Name,fun_Description,fun_Active FROM donationfund_fun';
$rsFunds = RunQuery($sSQL);

$fundPaymentTotal = [];
$fundPledgeTotal = [];
while ($row = mysqli_fetch_array($rsFunds)) {
    $fun_name = $row['fun_Name'];
    $fundPaymentTotal[$fun_name] = 0;
    $fundPledgeTotal[$fun_name] = 0;
}

// Create PDF Report
// *****************
class PDF_FamilyPledgeSummaryReport extends ChurchInfoReport
{
    // Constructor
    public function __construct()
    {
        parent::__construct('P', 'mm', $this->paperFormat);

        $this->SetFont('Times', '', 10);
        $this->SetMargins(20, 20);

        $this->SetAutoPageBreak(false);
        $this->incrementY = 0;
    }
}

// Instantiate the directory class and build the report.
$pdf = new PDF_FamilyPledgeSummaryReport();
$pdf->AddPage();

$leftX = 10;
$famNameX = 10;
$famMethodX = 90;
$famFundX = 120;
$famPledgeX = 150;
$famPayX = 170;
$famOweX = 190;

$famNameWid = $famMethodX - $famNameX;
$famMethodWid = $famFundX - $famMethodX;
$famFundWid = $famPledgeX - $famFundX;
$famPledgeWid = $famPayX - $famPledgeX;
$famPayWid = $famOweX - $famPayX;
$famOweWid = $famPayWid;

$pageTop = 10;
$y = $pageTop;
$lineInc = 4;

$pdf->WriteAt($leftX, $y, 'Pledge Summary By Family');
$y += $lineInc;

$pdf->WriteAtCell($famNameX, $y, $famNameWid, 'Name');
$pdf->WriteAtCell($famMethodX, $y, $famMethodWid, 'Method');
$pdf->WriteAtCell($famFundX, $y, $famFundWid, 'Fund');
$pdf->WriteAtCell($famPledgeX, $y, $famPledgeWid, 'Pledge');
$pdf->WriteAtCell($famPayX, $y, $famPayWid, 'Paid');
$pdf->WriteAtCell($famOweX, $y, $famOweWid, 'Owe');
$y += $lineInc;

// Loop through families
while ($aFam = mysqli_fetch_array($rsFamilies)) {
    extract($aFam);

    // Check for pledges if filtering by pledges
    if ($pledge_filter == 'pledge') {
        $temp = "SELECT plg_plgID FROM pledge_plg
			WHERE plg_FamID='$fam_ID' AND plg_PledgeOrPayment='Pledge' AND plg_FYID=$iFYID".$sSQLFundCriteria;
        $rsPledgeCheck = RunQuery($temp);
        if (mysqli_num_rows($rsPledgeCheck) == 0) {
            continue;
        }
    }

    // Get pledges and payments for this family and this fiscal year
    $sSQL = 'SELECT *, b.fun_Name AS fundName FROM pledge_plg 
			 LEFT JOIN donationfund_fun b ON plg_fundID = b.fun_ID
			 WHERE plg_FamID = '.$fam_ID.' AND plg_FYID = '.$iFYID.$sSQLFundCriteria.' ORDER BY plg_date';

    $rsPledges = RunQuery($sSQL);

    // If there is no pledge or a payment go to next family
    if (mysqli_num_rows($rsPledges) == 0) {
        continue;
    }

    if ($only_owe == 'yes') {
        // Run through pledges and payments for this family to see if there are any unpaid pledges
        $oweByFund = [];
        $bOwe = 0;
        while ($aRow = mysqli_fetch_array($rsPledges)) {
            extract($aRow);
            if ($plg_PledgeOrPayment == 'Pledge') {
                $oweByFund[$plg_fundID] -= $plg_amount;
            } else {
                $oweByFund[$plg_fundID] += $plg_amount;
            }
        }
        foreach ($oweByFund as $oweRow) {
            if ($oweRow < 0) {
                $bOwe = 1;
            }
        }
        if (!$bOwe) {
            continue;
        }
    }

    // Get pledges only
    $sSQL = 'SELECT *, b.fun_Name AS fundName FROM pledge_plg 
			 LEFT JOIN donationfund_fun b ON plg_fundID = b.fun_ID
			 WHERE plg_FamID = '.$fam_ID.' AND plg_FYID = '.$iFYID.$sSQLFundCriteria." AND plg_PledgeOrPayment = 'Pledge' ORDER BY plg_date";
    $rsPledges = RunQuery($sSQL);

    $totalAmountPledges = 0;

    if (mysqli_num_rows($rsPledges) == 0) {
    } else {
        $totalAmount = 0;
        $cnt = 0;
        while ($aRow = mysqli_fetch_array($rsPledges)) {
            extract($aRow);

            if (strlen($fundName) > 19) {
                $fundName = mb_substr($fundName, 0, 18).'...';
            }

            $fundPledgeTotal[$fundName] += $plg_amount;
            $fundPledgeMethod[$fundName] = $plg_method;
            $totalAmount += $plg_amount;
            $cnt += 1;
        }
        $pdf->SetFont('Times', '', 10);
        $totalAmountPledges = $totalAmount;
    }

    // Get payments only
    $sSQL = 'SELECT *, b.fun_Name AS fundName FROM pledge_plg 
			 LEFT JOIN donationfund_fun b ON plg_fundID = b.fun_ID
			 WHERE plg_FamID = '.$fam_ID.' AND plg_FYID = '.$iFYID.$sSQLFundCriteria." AND plg_PledgeOrPayment = 'Payment' ORDER BY plg_date";
    $rsPledges = RunQuery($sSQL);

    $totalAmountPayments = 0;
    if (mysqli_num_rows($rsPledges) == 0) {
    } else {
        $totalAmount = 0;
        $cnt = 0;
        while ($aRow = mysqli_fetch_array($rsPledges)) {
            extract($aRow);

            $totalAmount += $plg_amount;
            $fundPaymentTotal[$fundName] += $plg_amount;
            $cnt += 1;
        }
        $totalAmountPayments = $totalAmount;
    }

    if (mysqli_num_rows($rsFunds) > 0) {
        mysqli_data_seek($rsFunds, 0);
        while ($row = mysqli_fetch_array($rsFunds)) {
            $fun_name = $row['fun_Name'];
            if ($fundPledgeTotal[$fun_name] > 0) {
                $amountDue = $fundPledgeTotal[$fun_name] - $fundPaymentTotal[$fun_name];
                if ($amountDue < 0) {
                    $amountDue = 0;
                }

                $pdf->WriteAtCell($famNameX, $y, $famNameWid, $pdf->MakeSalutation($fam_ID));
                $pdf->WriteAtCell($famPledgeX, $y, $famPledgeWid, $fundPledgeTotal[$fun_name]);
                $pdf->WriteAtCell($famMethodX, $y, $famMethodWid, $fundPledgeMethod[$fun_name]);
                $pdf->WriteAtCell($famFundX, $y, $famFundWid, $fun_name);
                $pdf->WriteAtCell($famPayX, $y, $famPayWid, $fundPaymentTotal[$fun_name]);
                $pdf->WriteAtCell($famOweX, $y, $famOweWid, $amountDue);
                $y += $lineInc;
                if ($y > 250) {
                    $pdf->AddPage();
                    $y = $pageTop;
                }
            }
            $fundPledgeTotal[$fun_name] = 0; // Clear the array for the next person
            $fundPaymentTotal[$fun_name] = 0;
        }
    }
}

header('Pragma: public');  // Needed for IE when using a shared SSL certificate
if (SystemConfig::getValue('iPDFOutputType') == 1) {
    $pdf->Output('FamilyPledgeSummary'.date(SystemConfig::getValue("sDateFilenameFormat")).'.pdf', 'D');
} else {
    $pdf->Output();
}
