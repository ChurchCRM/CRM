<?php
/*******************************************************************************
*
*  filename    : Reports/ReminderReport.php
*  last change : 2005-03-26
*  description : Creates a PDF of the current deposit slip
*
*  InfoCentral is free software; you can redistribute it and/or modify
*  it under the terms of the GNU General Public License as published by
*  the Free Software Foundation; either version 2 of the License, or
*  (at your option) any later version.
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
$output = FilterInput($_POST["output"]);

// If CSVAdminOnly option is enabled and user is not admin, redirect to the menu.
if (!$_SESSION['bAdmin'] && $bCSVAdminOnly) {
	Redirect("Menu.php");
	exit;
}

// Get all the families
$sSQL = "SELECT * FROM family_fam WHERE 1";
// Filter by Family
if (!empty($_POST["family"])) {
	$count = 0;
	foreach ($_POST["family"] as $famID) {
		$fam[$count++] = FilterInput($famID,'int');
	}
	if ($count == 1) {
		if ($fam[0])
			$sSQL .= " AND fam_ID='$fam[0]' ";
	} else {
		$sSQL .= " AND (fam_ID='$fam[0]'";
		for($i = 1; $i < $count; $i++) {
			$sSQL .= " OR fam_ID='$fam[$i]'";
		}
		$sSQL .= ") ";
	}
}
$rsFamilies = RunQuery($sSQL);

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
		$this->SetMargins(0,0);
		$this->Open();
		$this->SetAutoPageBreak(false);
	}

	function StartNewPage ($fam_ID, $fam_Name, $fam_Address1, $fam_Address2, $fam_City, $fam_State, $fam_Zip, $fam_Country, $iFYID) {
		$curY = $this->StartLetterPage ($fam_ID, $fam_Name, $fam_Address1, $fam_Address2, $fam_City, $fam_State, $fam_Zip, $fam_Country, $iYear);
		$curY += 2 * $this->incrementY;
		$blurb = $this->sReminder1 . MakeFYString ($iFYID) . ".";
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


// Loop through families
while ($aFam = mysql_fetch_array($rsFamilies)) {
	extract ($aFam);

	// Get pledges and payments for this family and this fiscal year
	$sSQL = "SELECT *, b.fun_Name AS fundName FROM pledge_plg 
			 LEFT JOIN donationfund_fun b ON plg_fundID = b.fun_ID
			 WHERE plg_FamID = " . $fam_ID . " AND plg_FYID = " . $iFYID . " ORDER BY plg_date";
	$rsPledges = RunQuery($sSQL);

	// If there is either a pledge or a payment add a page for this reminder report

	if (mysql_num_rows ($rsPledges) == 0)
		continue;

	$curY = $pdf->StartNewPage ($fam_ID, $fam_Name, $fam_Address1, $fam_Address2, $fam_City, $fam_State, $fam_Zip, $fam_Country, $iFYID);

	// Get pledges only
	$sSQL = "SELECT *, b.fun_Name AS fundName FROM pledge_plg 
			 LEFT JOIN donationfund_fun b ON plg_fundID = b.fun_ID
			 WHERE plg_FamID = " . $fam_ID . " AND plg_FYID = " . $iFYID . " AND plg_PledgeOrPayment = 'Pledge' ORDER BY plg_date";
	$rsPledges = RunQuery($sSQL);

	$totalAmountPledges = 0;
	if (mysql_num_rows ($rsPledges) == 0) {
		$curY += $summaryIntervalY;
		$pdf->WriteAt ($summaryDateX, $curY, $pdf->sReminderNoPledge);
		$curY += 2 * $summaryIntervalY;
	} else {

		$summaryDateX = $pdf->leftX;
		$summaryFundX = 45;
		$summaryAmountX = 80;

		$summaryDateWid = $summaryFundX - $summaryDateX;
		$summaryFundWid = $summaryAmountX - $summaryFundX;
		$summaryAmountWid = 15;

		$summaryIntervalY = 4;

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

			$fundPledgeTotal[$fundName] += $plg_amount;
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
			 WHERE plg_FamID = " . $fam_ID . " AND plg_FYID = " . $iFYID . " AND plg_PledgeOrPayment = 'Payment' ORDER BY plg_date";
	$rsPledges = RunQuery($sSQL);

	$totalAmountPayments = 0;
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
			$fundPaymentTotal[$fundName] += $plg_amount;
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
			if ($fundPledgeTotal[$fun_name] > 0) {
				$amountDue = $fundPledgeTotal[$fun_name] - $fundPaymentTotal[$fun_name];
				if ($amountDue < 0)
					$amountDue = 0;
				$amountStr = sprintf ("Amount due for %s: %.2f", $fun_name, $amountDue);
				$pdf->WriteAt ($pdf->leftX, $curY, $amountStr);
				$curY += $summaryIntervalY;
			}
			$fundPledgeTotal[$fun_name] = 0; // Clear the array for the next person
			$fundPaymentTotal[$fun_name] = 0;
		}
	}
	$pdf->FinishPage ($curY);
}

if ($iPDFOutputType == 1) {
	$pdf->Output("ReminderReport" . date("Ymd") . ".pdf", true);
} else {
	$pdf->Output();
}
	
?>