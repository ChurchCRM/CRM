<?php
/*******************************************************************************
*
*  filename    : Reports/PrintDeposit.php
*  last change : 2013-02-21
*  description : Creates a PDF of the current deposit slip
*
*  ChurchCRM is free software; you can redistribute it and/or modify
*  it under the terms of the GNU General Public License as published by
*  the Free Software Foundation; either version 2 of the License, or
*  (at your option) any later version.
*
******************************************************************************/

global $iChecksPerDepositForm;

require "../Include/Config.php";
require "../Include/Functions.php";
require "../Include/ReportFunctions.php";
require "../Include/ReportConfig.php";

// Security
//if (!$_SESSION['bFinance'] && !$_SESSION['bAdmin']) {
//	Redirect("Menu.php");
//	exit;
//}

$iBankSlip = 0;
if (array_key_exists ("BankSlip", $_GET))
	$iBankSlip = FilterInput($_GET["BankSlip"], 'int');
if (!$iBankSlip && array_key_exists ("report_type", $_POST))
	$iBankSlip = FilterInput($_POST["report_type"], 'int');

$output = "pdf";
if (array_key_exists ("output",$_POST))
	$output = FilterInput($_POST["output"]);


$iDepositSlipID = 0;
if (array_key_exists ("deposit", $_POST))
	$iDepositSlipID = FilterInput($_POST["deposit"],"int");
	
if (!$iDepositSlipID && array_key_exists ('iCurrentDeposit', $_SESSION))
	$iDepositSlipID = $_SESSION['iCurrentDeposit'];

// If CSVAdminOnly option is enabled and user is not admin, redirect to the menu.
// If no DepositSlipId, redirect to the menu
if ((!$_SESSION['bAdmin'] && $bCSVAdminOnly && $output != "pdf") || !$iDepositSlipID){
	Redirect("Menu.php");
	exit;
}

// SQL Statement
//Get the payments for this deposit slip
$sSQL = "SELECT plg_plgID, plg_date, SUM(plg_amount) as plg_sum, plg_CheckNo, plg_method, plg_comment, fun_Name, a.fam_Name AS FamilyName, a.fam_Address1, a.fam_Address2, a.fam_City, a.fam_State, a.fam_Zip FROM pledge_plg 
		LEFT JOIN family_fam a ON plg_FamID = a.fam_ID
		LEFT JOIN donationfund_fun b ON plg_fundID = b.fun_ID
		WHERE plg_PledgeOrPayment = 'Payment' AND plg_depID = " . $iDepositSlipID . " GROUP BY CONCAT('Fam',plg_FamID,'Ck',plg_CheckNo) ORDER BY pledge_plg.plg_method DESC, pledge_plg.plg_date";
$rsPledges = RunQuery($sSQL);

// Exit if no rows returned
$iCountRows = mysql_num_rows($rsPledges);
if ($iCountRows < 1){
	header("Location: ../FinancialReports.php?ReturnMessage=NoRows&ReportType=Individual%20Deposit%20Report"); 
}

	// Create PDF Report
	// *****************


if ($output == "pdf") {
	
	class PDF_AccessReport extends ChurchInfoReport {

		// Private properties
		var $_Char_Size   = 10;        // Character size
		var $_Font        = "Courier";

		// Sets the character size
		// This changes the line height too
		function Set_Char_Size($pt) {
			if ($pt > 3) {
				$this->_Char_Size = $pt;
				$this->SetFont($this->_Font,'',$this->_Char_Size);
			}
		}

		function PrintRightJustified ($x, $y, $str) {
			$iLen = strlen ($str);
			$nMoveBy = 10 - 2 * $iLen;
			$this->SetXY ($x + $nMoveBy, $y);
			$this->Write (8, $str);
		}

		// Constructor
		function PDF_AccessReport() {
			parent::FPDF("P", "mm", $this->paperFormat);

			$this->_Font        = "Courier";
			$this->SetMargins(0,0);
			$this->Open();
			$this->Set_Char_Size(10);
			$this->AddPage();
			$this->SetAutoPageBreak(false);
		}
	}

	$fundTotal = array ();
	
	// Instantiate the directory class and build the report.
	$pdf = new PDF_AccessReport();

	// Read in report settings from database
	$rsConfig = mysql_query("SELECT cfg_name, IFNULL(cfg_value, cfg_default) AS value FROM config_cfg WHERE cfg_section='ChurchInfoReport'");
	if ($rsConfig) {
		while (list($cfg_name, $cfg_value) = mysql_fetch_row($rsConfig)) {
			$pdf->$cfg_name = $cfg_value;
		}
	}

	// Get Deposit Information
	$sSQL = "SELECT * FROM deposit_dep WHERE dep_ID = " . $iDepositSlipID;
	$rsDepositSlip = RunQuery($sSQL);
	extract(mysql_fetch_array($rsDepositSlip));

	$date1X = 12;
	$date1Y = 35 + 7;

	$date2X = 185;
	$date2Y = 90 + 7;

	$titleX = 85;
	$titleY = 90 + 7;

	$summaryX = 12;
	$summaryY = 115 + 7;

	$leftX = 64;
	$topY = 0 + 7;
	$intervalX = 52 - 3;
	$intervalY = 7;
	$amountOffsetX = 35;
	$maxX = 200;

	$curX = $leftX + $intervalX;
	$curY = $topY;

	$numItemsX = 140 - 4;
	$numItemsY = 61 + 7;

	$subTotalX = 201 - 4;
	$subTotalY = 35 + 7;

	$topTotalX = 201 - 4;
	$topTotalY = 61 + 7;

	$totalCash = 0;
	$totalChecks = 0;
	$numItems = 0;

	if (! $iBankSlip) {
		$summaryY -= 100;
		$titleY -= 90;
		$date2Y -= 90;
	} else {

		// Print Deposit Slip portion of report
		while ($aRow = mysql_fetch_array($rsPledges))
		{
			$OutStr = "";
			extract($aRow);

			// List all the checks and total the cash
			if ($plg_method == 'CASH') {
				$totalCash += $plg_sum;
			} else if ($plg_method == 'CHECK') {
				$numItems += 1;
				$totalChecks += $plg_sum;

				$pdf->PrintRightJustified ($curX, $curY, $plg_CheckNo);
				$pdf->PrintRightJustified ($curX + $amountOffsetX, $curY, $plg_sum);

				$curX += $intervalX;
				if ($curX > $maxX) {
					$curX = $leftX;
					$curY += $intervalY;
				}
			}
		}

		$pdf->SetXY ($date1X, $date1Y);
		$pdf->Write (8, $dep_Date);

		if ($totalCash > 0) {
			$totalCashStr = sprintf ("%.2f", $totalCash);
			$pdf->PrintRightJustified ($leftX + $amountOffsetX, $topY, $totalCashStr);
			$numItems += 1;
		}

		$pdf->PrintRightJustified ($numItemsX, $numItemsY, $numItems);
		$pdf->PrintRightJustified ($numItemsX, $numItemsY, $numItems);
		$grandTotalStr = sprintf ("%.2f", $totalChecks + $totalCash);
		$pdf->PrintRightJustified ($subTotalX, $subTotalY, $grandTotalStr);
		$pdf->PrintRightJustified ($topTotalX, $topTotalY, $grandTotalStr);
	}

	$pdf->SetXY ($date2X, $date2Y);
	$pdf->Write (8, $dep_Date);

	$pdf->SetXY ($titleX, $titleY);
	$pdf->SetFont('Courier','B', 20);
	$pdf->Write (8, "Deposit Summary " . $iDepositSlipID);
	$pdf->SetFont('Times','B', 10);

	$curX = $summaryX;
	$curY = $summaryY;

	$summaryFundX = 15;
	$summaryMethodX = 55;
	$summaryFromX = 80;
	$summaryMemoX = 120;
	$summaryAmountX = 185;
	$summaryIntervalY = 4;

	// Get the list of funds
	$sSQL = "SELECT fun_ID,fun_Name,fun_Description,fun_Active FROM donationfund_fun WHERE fun_Active = 'true'";
	$rsFunds = RunQuery($sSQL);

	//Get the payments for this deposit slip
	$sSQL = "SELECT plg_plgID, plg_FYID, plg_date, plg_amount, plg_method, plg_CheckNo, 
	         plg_comment, a.fam_Name AS FamilyName, b.fun_Name AS fundName
			 FROM pledge_plg
			 LEFT JOIN family_fam a ON plg_FamID = a.fam_ID
			 LEFT JOIN donationfund_fun b ON plg_fundID = b.fun_ID
			 WHERE plg_PledgeOrPayment = 'Payment' AND plg_depID = " . $iDepositSlipID . " ORDER BY pledge_plg.plg_method DESC, pledge_plg.plg_date";
	$rsPledges = RunQuery($sSQL);

	$pdf->SetFont('Times','B', 10);
	$pdf->SetXY ($curX, $curY);
	$pdf->Write (8, 'Chk No.');

	$pdf->SetXY ($curX + $summaryFundX, $curY);
	$pdf->Write (8, 'Fund');

	$pdf->SetXY ($curX + $summaryMethodX, $curY);
	$pdf->Write (8, 'PmtMethod');

	$pdf->SetXY ($curX + $summaryFromX, $curY);
	$pdf->Write (8, 'Rcd From');

	$pdf->SetXY ($curX + $summaryMemoX, $curY);
	$pdf->Write (8, 'Memo');

	$pdf->SetXY ($curX + $summaryAmountX - 5, $curY);
	$pdf->Write (8, 'Amount');
	$curY += 2 * $summaryIntervalY;

	$totalAmount = 0;

	while ($aRow = mysql_fetch_array($rsPledges))
	{
		$pdf->SetFont('Times','', 10);

		$OutStr = "";
		extract($aRow);
		
		if (!$fundName)
			$fundTotal['UNDESIGNATED'] += $plg_amount;
		else {
			if (array_key_exists ($fundName, $fundTotal))
				$fundTotal[$fundName] += $plg_amount;
			else
				$fundTotal[$fundName] = $plg_amount;
		}
			
		// Format Data
		if (strlen($plg_CheckNo) > 8)
			$plg_CheckNo = "...".substr($plg_CheckNo,-8,8);
		if (strlen($fundName) > 20)
			$fundName = substr($fundName,0,20) . "...";
		if (strlen($plg_comment) > 40)
			$plg_comment = substr($plg_comment,0,38) . "...";
		if (strlen($FamilyName) > 25)
			$FamilyName = substr($FamilyName,0,24) . "...";

		$pdf->PrintRightJustified ($curX + 2, $curY, $plg_CheckNo);

		$pdf->SetXY ($curX + $summaryFundX, $curY);
		$pdf->Write (8, $fundName);

		$pdf->SetXY ($curX + $summaryMethodX, $curY);
		$pdf->Write (8, $plg_method);

		$pdf->SetXY ($curX + $summaryFromX, $curY);
		$pdf->Write (8, $FamilyName);

		$pdf->SetXY ($curX + $summaryMemoX, $curY);
		$pdf->Write (8, $plg_comment);

		$pdf->SetFont('Courier','', 8);

		$pdf->PrintRightJustified ($curX + $summaryAmountX, $curY, $plg_amount);

		$totalAmount += $plg_amount;

		$curY += $summaryIntervalY;

		if ($curY >= 250) {
		  $pdf->AddPage ();
		  $curY = $topY;
		}
	}

	$curY += $summaryIntervalY;

	$pdf->SetXY ($curX + $summaryMemoX, $curY);
	$pdf->Write (8, 'Deposit total');

	$grandTotalStr = sprintf ("%.2f", $totalAmount);
	$pdf->PrintRightJustified ($curX + $summaryAmountX, $curY, $grandTotalStr);

	// Now print deposit totals by fund
	$curY += 2 * $summaryIntervalY;

	$pdf->SetFont('Times','B', 10);
	$pdf->SetXY ($curX, $curY);
	$pdf->Write (8, 'Deposit totals by fund');
	$pdf->SetFont('Courier','', 8);

	$curY += $summaryIntervalY;

	if (mysql_num_rows ($rsFunds) > 0) {
		mysql_data_seek($rsFunds,0);
		while ($row = mysql_fetch_array($rsFunds))
		{
			$fun_name = $row["fun_Name"];
		   if (array_key_exists ($fun_name, $fundTotal) && $fundTotal[$fun_name] > 0) {
	   		$pdf->SetXY ($curX, $curY);
	   		$pdf->Write (8, $fun_name);
			  $amountStr = sprintf ("%.2f", $fundTotal[$fun_name]);
	   		$pdf->PrintRightJustified ($curX + $summaryMethodX, $curY, $amountStr);
			  $curY += $summaryIntervalY;
		   }
		}
		if (array_key_exists ('UNDESIGNATED', $fundTotal) && $fundTotal['UNDESIGNATED']) {
			$pdf->SetXY ($curX, $curY);
	   		$pdf->Write (8, gettext("UNDESIGNATED"));
			$amountStr = sprintf ("%.2f", $fundTotal['UNDESIGNATED']);
	   		$pdf->PrintRightJustified ($curX + $summaryMethodX, $curY, $amountStr);
			$curY += $summaryIntervalY;
		}	
	}

	header('Pragma: public');  // Needed for IE when using a shared SSL certificate
	if ($iPDFOutputType == 1)
		$pdf->Output("Deposit-" . $iDepositSlipID . ".pdf", "D");
	else
		$pdf->Output();


// Create CVS File
// ***************

} elseif ($output == "csv") {
	// Settings
	$delimiter = ",";
	$eol = "\r\n";
	
	// Build headings row
	preg_match ("/SELECT (.*) FROM /i", $sSQL, $result);
	$headings = explode(",",$result[1]);
	$buffer = "";
	foreach ($headings as $heading) {
		$buffer .= trim($heading) . $delimiter;
	}
	// Remove trailing delimiter and add eol
	$buffer = substr($buffer,0,-1) . $eol;
	
	// Add data
	while ($row = mysql_fetch_row($rsPledges)) {
		foreach ($row as $field) {
			$field = str_replace($delimiter, " ", $field);	// Remove any delimiters from data
			$buffer .= $field . $delimiter;
		}
		// Remove trailing delimiter and add eol
		$buffer = substr($buffer,0,-1) . $eol;
	}
	
	// Export file
	header("Content-type: text/x-csv");
	header("Content-Disposition: attachment; filename=ChurchCRM-" . date("Ymd-Gis") . ".csv");
	echo $buffer;
}
?>
