<?php
/*******************************************************************************
*
*  filename    : Reports/ReminderReport.php
*  last change : 2005-03-26
*  description : Creates a PDF of the current deposit slip
*  Copyright 2004-2005  Michael Wilt, Timothy Dearborn
*
*  LICENSE:
*  (C) Free Software Foundation, Inc.
*
*  ChurchCRM is free software; you can redistribute it and/or modify
*  it under the terms of the GNU General Public License as published by
*  the Free Software Foundation; either version 3 of the License, or
*  (at your option) any later version.
*
*  This program is distributed in the hope that it will be useful, but
*  WITHOUT ANY WARRANTY; without even the implied warranty of
*  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU
*  General Public License for more details.
*
*  http://www.gnu.org/licenses
*
******************************************************************************/

require "../Include/Config.php";
require "../Include/Functions.php";
require "../Include/ReportFunctions.php";
require "../Include/ReportConfig.php";

// Security
if (!$_SESSION['bFinance'] && !$_SESSION['bAdmin']) {
    Redirect("Menu.php");
    exit;
}

//Get the Fiscal Year ID out of the querystring
$iFYID = FilterInput($_POST["FYID"],'int');
$_SESSION['idefaultFY'] = $iFYID; // Remember the chosen FYID
$output = FilterInput($_POST["output"]);
$pledge_filter = FilterInput($_POST["pledge_filter"]);
$only_owe = FilterInput($_POST["only_owe"]);

// If CSVAdminOnly option is enabled and user is not admin, redirect to the menu.
if (!$_SESSION['bAdmin'] && $bCSVAdminOnly) {
    Redirect("Menu.php");
    exit;
}

if (!empty($_POST["classList"])) {
    $classList = $_POST["classList"];

    if ($classList[0]) {
        $sSQL = "SELECT * FROM list_lst WHERE lst_ID = 1 ORDER BY lst_OptionSequence";
        $rsClassifications = RunQuery($sSQL);

        $inClassList = "(";
        $notInClassList = "(";

        while ($aRow = mysql_fetch_array($rsClassifications)) {
            extract($aRow);
            if (in_array($lst_OptionID, $classList)) {
                if ($inClassList == "(") {
                    $inClassList .= $lst_OptionID;
                } else {
                    $inClassList .= "," . $lst_OptionID;
                }
            } else {
                if ($notInClassList == "(") {
                    $notInClassList .= $lst_OptionID;
                } else {
                    $notInClassList .= "," . $lst_OptionID;
                }
            }
        }
        $inClassList .= ")";
        $notInClassList .= ")";
    }

    // all classes were selected. this should behave as if no filter classes were specified
    if ($notInClassList == '()')
        unset($classList);
}


// Get all the families
$sSQL = "SELECT * FROM family_fam";

if ($classList[0]) {
    $sSQL .= " LEFT JOIN person_per ON fam_ID=per_fam_ID";
}
$sSQL .= " WHERE";

$criteria = "";

// Filter by Family
if (!empty($_POST["family"])) {
    $count = 0;
    foreach ($_POST["family"] as $famID) {
        $fam[$count++] = FilterInput($famID,'int');
    }
    if ($count == 1) {
        if ($fam[0]) {
            $q = " fam_ID='$fam[0]'";
            if ($criteria) {
                $criteria .= " AND" . $q;
            } else {
                $criteria = $q;
            }
        }
    } else {
        $q = " (fam_ID='$fam[0]'";
        if ($criteria) {
            $criteria .= " AND" . $q;
        } else {
            $criteria = $q;
        }
        for ($i = 1; $i < $count; $i++) {
            $criteria .= " OR fam_ID='$fam[$i]'";
        }
        $criteria .= ")";
    }
}

if ($classList[0]) {
    $q = " per_cls_ID IN " . $inClassList . " AND per_fam_ID NOT IN (SELECT DISTINCT per_fam_ID FROM person_per WHERE per_cls_ID IN " . $notInClassList . ")";
    if ($criteria) {
        $criteria .= " AND" . $q;
    } else {
        $criteria = $q;
    }
}

if (!$criteria) {
    $criteria = " 1";
}
$sSQL .= $criteria;

//var_dump($sSQL);
$rsFamilies = RunQuery($sSQL);

$sSQLFundCriteria = "";

// Build criteria string for funds
if (!empty($_POST["funds"])) {
    $fundCount = 0;
    foreach ($_POST["funds"] as $fundID) {
        $fund[$fundCount++] = FilterInput($fundID,'int');
    }
    if ($fundCount == 1) {
        if ($fund[0])
            $sSQLFundCriteria .= " AND plg_fundID='$fund[0]' ";
    } else {
        $sSQLFundCriteria  .= " AND (plg_fundID ='$fund[0]'";
        for($i = 1; $i < $fundCount; $i++) {
            $sSQLFundCriteria  .= " OR plg_fundID='$fund[$i]'";
        }
        $sSQLFundCriteria .= ") ";
    }
}

// Make the string describing the fund filter
if ($fundCount > 0) {
    if ($fundCount == 1) {
        if ($fund[0] == gettext ("All Funds"))
            $fundOnlyString = gettext (" for all funds");
        else
            $fundOnlyString = gettext (" for fund ");
    } else
        $fundOnlyString = gettext ("for funds ");
    for ($i = 0; $i < $fundCount; $i++) {
        $sSQL = "SELECT fun_Name FROM donationfund_fun WHERE fun_ID=" . $fund[$i];
        $rsOneFund = RunQuery($sSQL);
        $aFundName = mysql_fetch_array($rsOneFund);
        $fundOnlyString .= $aFundName["fun_Name"];
        if ($i < $fundCount - 1)
            $fundOnlyString .= ", ";
    }
}

// Get the list of funds
$sSQL = "SELECT fun_ID,fun_Name,fun_Description,fun_Active FROM donationfund_fun";
$rsFunds = RunQuery($sSQL);

// Create PDF Report
// *****************
class PDF_ReminderReport extends ChurchInfoReport {

    // Constructor
    function PDF_ReminderReport() {
        parent::FPDF("P", "mm", $this->paperFormat);

        $this->SetFont('Times','', 10);
        $this->SetMargins(20,20);
        $this->Open();
        $this->SetAutoPageBreak(false);
    }

    function StartNewPage ($fam_ID, $fam_Name, $fam_Address1, $fam_Address2, $fam_City, $fam_State, $fam_Zip, $fam_Country, $fundOnlyString, $iFYID) {
        $curY = $this->StartLetterPage ($fam_ID, $fam_Name, $fam_Address1, $fam_Address2, $fam_City, $fam_State, $fam_Zip, $fam_Country);
        $curY += 2 * $this->incrementY;
        $blurb = $this->sReminder1 . MakeFYString ($iFYID) . $fundOnlyString . ".";
        $this->WriteAt ($this->leftX, $curY, $blurb);
        $curY += 2 * $this->incrementY;
        return ($curY);
    }

    function FinishPage ($curY) {
        $curY += 2 * $this->incrementY;
        $this->WriteAt ($this->leftX, $curY, "Sincerely,");
        $curY += 4 * $this->incrementY;
        $this->WriteAt ($this->leftX, $curY, $this->sReminderSigner);
    }
}

// Instantiate the directory class and build the report.
$pdf = new PDF_ReminderReport();

// Read in report settings from database
$rsConfig = mysql_query("SELECT cfg_name, IFNULL(cfg_value, cfg_default) AS value FROM config_cfg WHERE cfg_section='ChurchInfoReport'");
if ($rsConfig) {
    while (list($cfg_name, $cfg_value) = mysql_fetch_row($rsConfig)) {
        $pdf->$cfg_name = $cfg_value;
    }
}

// Loop through families
while ($aFam = mysql_fetch_array($rsFamilies)) {
    extract ($aFam);

    // Check for pledges if filtering by pledges
    if ($pledge_filter == "pledge"){
        $temp = "SELECT plg_plgID FROM pledge_plg
            WHERE plg_FamID='$fam_ID' AND plg_PledgeOrPayment='Pledge' AND plg_FYID=$iFYID" . $sSQLFundCriteria;
        $rsPledgeCheck = RunQuery($temp);
        if (mysql_num_rows ($rsPledgeCheck) == 0)
            continue;
    }

    // Get pledges and payments for this family and this fiscal year
    $sSQL = "SELECT *, b.fun_Name AS fundName FROM pledge_plg
             LEFT JOIN donationfund_fun b ON plg_fundID = b.fun_ID
             WHERE plg_FamID = " . $fam_ID . " AND plg_FYID = " . $iFYID . $sSQLFundCriteria . " ORDER BY plg_date";

    $rsPledges = RunQuery($sSQL);

    // If there is no pledge or a payment go to next family
    if (mysql_num_rows ($rsPledges) == 0)
        continue;

    if ($only_owe == "yes") {
        // Run through pledges and payments for this family to see if there are any unpaid pledges
        $oweByFund = array ();
        $bOwe = 0;
        while ($aRow = mysql_fetch_array($rsPledges)) {
            extract ($aRow);
            if ($plg_PledgeOrPayment=='Pledge') {
            	if (array_key_exists ($plg_fundID, $oweByFund))
	                $oweByFund[$plg_fundID] -= $plg_amount;
	            else
	                $oweByFund[$plg_fundID] = -$plg_amount;
            } else {
            	if (array_key_exists ($plg_fundID, $oweByFund))
            		$oweByFund[$plg_fundID] += $plg_amount;
            	else
            		$oweByFund[$plg_fundID] = $plg_amount;
            }
        }
        foreach ($oweByFund as $oweRow)
            if ($oweRow < 0)
                $bOwe = 1;
        if (! $bOwe)
            continue;
    }

    // Add a page for this reminder report
    $curY = $pdf->StartNewPage ($fam_ID, $fam_Name, $fam_Address1, $fam_Address2, $fam_City, $fam_State, $fam_Zip, $fam_Country, $fundOnlyString, $iFYID);

    // Get pledges only
    $sSQL = "SELECT *, b.fun_Name AS fundName FROM pledge_plg
             LEFT JOIN donationfund_fun b ON plg_fundID = b.fun_ID
             WHERE plg_FamID = " . $fam_ID . " AND plg_FYID = " . $iFYID . $sSQLFundCriteria . " AND plg_PledgeOrPayment = 'Pledge' ORDER BY plg_date";
    $rsPledges = RunQuery($sSQL);

    $totalAmountPledges = 0;
    $fundPledgeTotal = array ();

    $summaryDateX = $pdf->leftX;
    $summaryFundX = 45;
    $summaryAmountX = 80;

    $summaryDateWid = $summaryFundX - $summaryDateX;
    $summaryFundWid = $summaryAmountX - $summaryFundX;
    $summaryAmountWid = 15;

    $summaryIntervalY = 4;

    if (mysql_num_rows ($rsPledges) == 0) {
        $curY += $summaryIntervalY;
        $noPledgeString = $pdf->sReminderNoPledge . "(" . $fundOnlyString . ")";
        $pdf->WriteAt ($summaryDateX, $curY, $noPledgeString);
        $curY += 2 * $summaryIntervalY;
    } else {

        $curY += $summaryIntervalY;
        $pdf->SetFont('Times','B', 10);
        $pdf->WriteAtCell ($summaryDateX, $curY, $summaryDateWid, 'Pledge');
        $curY += $summaryIntervalY;

        $pdf->SetFont('Times','B', 10);

        $pdf->WriteAtCell ($summaryDateX, $curY, $summaryDateWid, 'Date');
        $pdf->WriteAtCell ($summaryFundX, $curY, $summaryFundWid, 'Fund');
        $pdf->WriteAtCell ($summaryAmountX, $curY, $summaryAmountWid, 'Amount');

        $curY += $summaryIntervalY;

        $totalAmount = 0;
        $cnt = 0;

        while ($aRow = mysql_fetch_array($rsPledges)) {
            extract ($aRow);

            if (strlen($fundName) > 19)
                $fundName = substr($fundName,0,18) . "...";

            $pdf->SetFont('Times','', 10);

            $pdf->WriteAtCell ($summaryDateX, $curY, $summaryDateWid, $plg_date);
            $pdf->WriteAtCell ($summaryFundX, $curY, $summaryFundWid, $fundName);

            $pdf->SetFont('Courier','', 8);

            $pdf->PrintRightJustifiedCell ($summaryAmountX, $curY, $summaryAmountWid, $plg_amount);

            if (array_key_exists ($fundName, $fundPledgeTotal))
	            $fundPledgeTotal[$fundName] += $plg_amount;
	        else
	            $fundPledgeTotal[$fundName] = $plg_amount;
	        $totalAmount += $plg_amount;
            $cnt += 1;

            $curY += $summaryIntervalY;
        }
        $pdf->SetFont('Times','', 10);
        if ($cnt > 1) {
            $pdf->WriteAtCell ($summaryFundX, $curY, $summaryFundWid, "Total pledges");
            $pdf->SetFont('Courier','', 8);
            $totalAmountStr = sprintf ("%.2f", $totalAmount);
            $pdf->PrintRightJustifiedCell ($summaryAmountX, $curY, $summaryAmountWid, $totalAmountStr);
            $curY += $summaryIntervalY;
        }
        $totalAmountPledges = $totalAmount;
    }

    // Get payments only
    $sSQL = "SELECT *, b.fun_Name AS fundName FROM pledge_plg
             LEFT JOIN donationfund_fun b ON plg_fundID = b.fun_ID
             WHERE plg_FamID = " . $fam_ID . " AND plg_FYID = " . $iFYID . $sSQLFundCriteria . " AND plg_PledgeOrPayment = 'Payment' ORDER BY plg_date";
    $rsPledges = RunQuery($sSQL);

    $totalAmountPayments = 0;
    $fundPaymentTotal = array ();
    if (mysql_num_rows ($rsPledges) == 0) {
        $curY += $summaryIntervalY;
        $pdf->WriteAt ($summaryDateX, $curY, $pdf->sReminderNoPayments);
        $curY += 2 * $summaryIntervalY;
    } else {
        $summaryDateX = $pdf->leftX;
        $summaryCheckNoX = 40;
        $summaryMethodX = 60;
        $summaryFundX = 85;
        $summaryMemoX = 120;
        $summaryAmountX = 170;
        $summaryIntervalY = 4;

        $summaryDateWid = $summaryCheckNoX - $summaryDateX;
        $summaryCheckNoWid = $summaryMethodX - $summaryCheckNoX;
        $summaryMethodWid = $summaryFundX - $summaryMethodX;
        $summaryFundWid = $summaryMemoX - $summaryFundX;
        $summaryMemoWid = $summaryAmountX - $summaryMemoX;
        $summaryAmountWid = 15;

        $curY += $summaryIntervalY;
        $pdf->SetFont('Times','B', 10);
        $pdf->WriteAtCell ($summaryDateX, $curY, $summaryDateWid, 'Payments');
        $curY += $summaryIntervalY;

        $pdf->SetFont('Times','B', 10);

        $pdf->WriteAtCell ($summaryDateX, $curY, $summaryDateWid, 'Date');
        $pdf->WriteAtCell ($summaryCheckNoX, $curY, $summaryCheckNoWid, 'Chk No.');
        $pdf->WriteAtCell ($summaryMethodX, $curY, $summaryMethodWid, 'PmtMethod');
        $pdf->WriteAtCell ($summaryFundX, $curY, $summaryFundWid, 'Fund');
        $pdf->WriteAtCell ($summaryMemoX, $curY, $summaryMemoWid, 'Memo');
        $pdf->WriteAtCell ($summaryAmountX, $curY, $summaryAmountWid, 'Amount');

        $curY += $summaryIntervalY;

        $totalAmount = 0;
        $cnt = 0;
        while ($aRow = mysql_fetch_array($rsPledges)) {
            extract ($aRow);

            // Format Data
            if (strlen($plg_CheckNo) > 8)
                $plg_CheckNo = "...".substr($plg_CheckNo,-8,8);
            if (strlen($fundName) > 19)
                $fundName = substr($fundName,0,18) . "...";
            if (strlen($plg_comment) > 30)
                $plg_comment = substr($plg_comment,0,30) . "...";

            $pdf->SetFont('Times','', 10);

            $pdf->WriteAtCell ($summaryDateX, $curY, $summaryDateWid, $plg_date);
            $pdf->PrintRightJustifiedCell ($summaryCheckNoX, $curY, $summaryCheckNoWid, $plg_CheckNo);
            $pdf->WriteAtCell ($summaryMethodX, $curY, $summaryMethodWid, $plg_method);
            $pdf->WriteAtCell ($summaryFundX, $curY, $summaryFundWid, $fundName);
            $pdf->WriteAtCell ($summaryMemoX, $curY, $summaryMemoWid, $plg_comment);

            $pdf->SetFont('Courier','', 8);

            $pdf->PrintRightJustifiedCell ($summaryAmountX, $curY, $summaryAmountWid, $plg_amount);

            $totalAmount += $plg_amount;
            if (array_key_exists ($fundName, $fundPaymentTotal))
	            $fundPaymentTotal[$fundName] += $plg_amount;
	        else
	            $fundPaymentTotal[$fundName] = $plg_amount;
	        $cnt += 1;

            $curY += $summaryIntervalY;

            if ($curY > 220) {
                $pdf->AddPage ();
                $curY = 20;
            }

        }
        $pdf->SetFont('Times','', 10);
        if ($cnt > 1) {
            $pdf->WriteAtCell ($summaryMemoX, $curY, $summaryMemoWid, "Total payments");
            $pdf->SetFont('Courier','', 8);
            $totalAmountString = sprintf ("%.2f", $totalAmount);
            $pdf->PrintRightJustifiedCell ($summaryAmountX, $curY, $summaryAmountWid, $totalAmountString);
            $curY += $summaryIntervalY;
        }
        $pdf->SetFont('Times','', 10);
        $totalAmountPayments = $totalAmount;
    }

    $curY += $summaryIntervalY;

    if (mysql_num_rows ($rsFunds) > 0) {
        mysql_data_seek($rsFunds,0);
        while ($row = mysql_fetch_array($rsFunds))
        {
            $fun_name = $row["fun_Name"];
            if (array_key_exists ($fun_name, $fundPledgeTotal) && $fundPledgeTotal[$fun_name] > 0) {
            	if (array_key_exists ($fun_name, $fundPaymentTotal))
	                $amountDue = $fundPledgeTotal[$fun_name] - $fundPaymentTotal[$fun_name];
	            else
		            $amountDue = $fundPledgeTotal[$fun_name];
                if ($amountDue < 0)
                    $amountDue = 0;
                $amountStr = sprintf ("Amount due for %s: %.2f", $fun_name, $amountDue);
                $pdf->WriteAt ($pdf->leftX, $curY, $amountStr);
                $curY += $summaryIntervalY;
            }
        }
    }
    $pdf->FinishPage ($curY);
}

if ($iPDFOutputType == 1) {
    $pdf->Output("ReminderReport" . date("Ymd") . ".pdf", "D");
} else {
    $pdf->Output();
}

?>
