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

// Filter Values
$output = FilterInput($_POST["output"]);
$iFYID = FilterInput($_POST["FYID"],"int");

// If CSVAdminOnly option is enabled and user is not admin, redirect to the menu.
if (!$_SESSION['bAdmin'] && $bCSVAdminOnly && $output != "pdf") {
	Redirect("Menu.php");
	exit;
}

// Get the list of funds
$sSQL = "SELECT fun_ID,fun_Name,fun_Description,fun_Active FROM donationfund_fun WHERE fun_Active = 'true'";
$rsFunds = RunQuery($sSQL);

// Get pledges and payments for this fiscal year
$sSQL = "SELECT plg_plgID, plg_FYID, plg_amount, plg_PledgeOrPayment, plg_fundID, b.fun_Name AS fundName, b.fun_Active AS fundActive FROM pledge_plg 
		 LEFT JOIN donationfund_fun b ON plg_fundID = b.fun_ID
		 WHERE plg_FYID = " . $iFYID;

 // Filter by Fund
 if (!empty($_POST["funds"])) {
 	$count = 0;
 	foreach ($_POST["funds"] as $fundID) {
 		$fund[$count++] = FilterInput($fundID,'int');
 	}
 	if ($count == 1) {
 		if ($fund[0])
 			$sSQL .= " AND plg_fundID='$fund[0]' ";
 	} else {
 		$sSQL .= " AND (plg_fundID ='$fund[0]'";
 		for($i = 1; $i < $count; $i++) {
 			$sSQL .= " OR plg_fundID='$fund[$i]'";
 		}
 		$sSQL .= ") ";
 	}
 }
// Order by Fund Active, Fund Name
$sSQL .= " ORDER BY fundActive, fundName";

// Run Query
$rsPledges = RunQuery($sSQL);


// Create PDF Report
// *****************
if ($output == "pdf") {

	class PDF_PledgeSummaryReport extends ChurchInfoReport {

		// Constructor
		function PDF_PledgeSummaryReport() {
			parent::FPDF("P", "mm", $this->paperFormat);

			$this->SetFont('Times','', 10);
			$this->SetMargins(0,0);
			$this->Open();
			$this->SetAutoPageBreak(false);
			$this->AddPage ();
		}
	}

	// Instantiate the directory class and build the report.
	$pdf = new PDF_PledgeSummaryReport();


	while ($aRow = mysql_fetch_array($rsPledges)) {
		extract ($aRow);

	   if ($fundName == "") {
	      $fundName = "Unassigned";
	   }

	   if ($plg_PledgeOrPayment == "Pledge") {
	      $pledgeFundTotal[$fundName] += $plg_amount;
		  $pledgeCnt[$fundName] += 1;
	   } else if ($plg_PledgeOrPayment == "Payment") {
	      $paymentFundTotal[$fundName] += $plg_amount;
		  $paymentCnt[$fundName] += 1;
	   }
	}


	$nameX = 20;
	$pledgeX = 80;
	$paymentX = 120;
	$pledgeCountX = 140;
	$paymentCountX = 160;
	$curY = 20;

	$pdf->WriteAt ($pdf->leftX, $curY, $pdf->sChurchName); $curY += $pdf->incrementY;
	$pdf->WriteAt ($pdf->leftX, $curY, $pdf->sChurchAddress); $curY += $pdf->incrementY;
	$pdf->WriteAt ($pdf->leftX, $curY, $pdf->sChurchCity . ", " . $pdf->sChurchState . "  " . $pdf->sChurchZip); $curY += $pdf->incrementY;
	$pdf->WriteAt ($pdf->leftX, $curY, $pdf->sChurchPhone . "  " . $pdf->sChurchEmail); $curY += 2 * $pdf->incrementY;

	$blurb = $pdf->sPledgeSummary1;
	$blurb .= MakeFYString ($iFYID);
	$blurb .= $pdf->sPledgeSummary2 . date ("Y-m-d") . ".";
	$pdf->WriteAt ($nameX, $curY, $blurb);

	$curY += 3 * $pdf->incrementY;

	$pdf->SetFont('Times','B', 10);
	$pdf->WriteAt ($nameX, $curY, "Fund");
	$pdf->PrintRightJustified ($pledgeX, $curY, "Pledges");
	$pdf->PrintRightJustified ($paymentX, $curY, "Payments");
	$pdf->PrintRightJustified ($pledgeCountX, $curY, "# Pledges");
	$pdf->PrintRightJustified ($paymentCountX, $curY, "# Payments");
	$pdf->SetFont('Times','', 10);
	$curY += $pdf->incrementY;

	mysql_data_seek($rsFunds,0); // Change this to print out funds in active / alpha order.
	while ($row = mysql_fetch_array($rsFunds))
	{
		$fun_name = $row["fun_Name"];
		if ($pledgeFundTotal[$fun_name] > 0 || $paymentFundTotal[$fun_name] > 0) {
			if (strlen($fun_name) > 30)
				$short_fun_name = substr($fun_name,0,30) . "...";
			else
				$short_fun_name = $fun_name;	
	   		$pdf->WriteAt ($nameX, $curY, $short_fun_name);
			$amountStr = sprintf ("%.2f", $pledgeFundTotal[$fun_name]);
	   		$pdf->PrintRightJustified ($pledgeX, $curY, $amountStr);
			$amountStr = sprintf ("%.2f", $paymentFundTotal[$fun_name]);
	   		$pdf->PrintRightJustified ($paymentX, $curY, $amountStr);
	   		$pdf->PrintRightJustified ($pledgeCountX, $curY, $pledgeCnt[$fun_name]);
	   		$pdf->PrintRightJustified ($paymentCountX, $curY, $paymentCnt[$fun_name]);
			$curY += $pdf->incrementY;
		}
	}

	if ($pledgeFundTotal["Unassigned"] > 0 || $paymentFundTotal["Unassigned"] > 0) {
	   $pdf->WriteAt ($nameX, $curY, "Unassigned");
	   $amountStr = sprintf ("%.2f", $pledgeFundTotal["Unassigned"]);
	   $pdf->PrintRightJustified ($pledgeX, $curY, $amountStr);
	   $amountStr = sprintf ("%.2f", $paymentFundTotal["Unassigned"]);
	   $pdf->PrintRightJustified ($paymentX, $curY, $amountStr);
	   	$pdf->PrintRightJustified ($pledgeCountX, $curY, $pledgeCnt["Unassigned"]);
	   	$pdf->PrintRightJustified ($paymentCountX, $curY, $paymentCnt["Unassigned"]);
	   $curY += $pdf->incrementY;
	}

	if ($iPDFOutputType == 1) {
		$pdf->Output("PledgeSummaryReport" . date("Ymd") . ".pdf", true);
	} else {
		$pdf->Output();
	}

// Output a text file
// ##################
} elseif ($output == "csv") {

	// Settings
	$delimiter = ",";
	$eol = "\r\n";

	// Build headings row
	eregi ("SELECT (.*) FROM ", $sSQL, $result);
	$headings = explode(",",$result[1]);
	$buffer = "";
	foreach ($headings as $heading) {
		$buffer .= trim($heading) . $delimiter;
	}
	// Remove trailing delimiter and add eol
	$buffer .= substr($buffer,-1) . $eol;

	// Add data
	while ($row = mysql_fetch_row($rsPledges)) {
		foreach ($row as $field) {
			$field = str_replace($delimiter, " ", $field);	// Remove any delimiters from data
			$buffer .= $field . $delimiter;
		}
		// Remove trailing delimiter and add eol
		$buffer .= substr($buffer,-1) . $eol;
	}

	// Export file
	header("Content-type: text/x-csv");
	header("Content-Disposition: attachment; filename=ChurchInfo-" . date("Ymd-Gis") . ".csv");
	echo $buffer;
}

		
?>
