<?php
/*******************************************************************************
*
*  filename    : Reports/PrintDeposit.php
*  last change : 2003-08-30
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

$iBankSlip = FilterInput($_GET["BankSlip"]);

// If CSVAdminOnly option is enabled and user is not admin, redirect to the menu.
if (!$_SESSION['bAdmin'] && $bCSVAdminOnly) {
	Redirect("Menu.php");
	exit;
}

$iDepositSlipID = $_SESSION['iCurrentDeposit'];

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

// Instantiate the directory class and build the report.
$pdf = new PDF_AccessReport();

//Get the payments for this deposit slip
$sSQL = "SELECT plg_plgID, plg_date, plg_amount, plg_CheckNo, plg_method, plg_comment, a.fam_Name AS FamilyName
		 FROM pledge_plg 
		 LEFT JOIN family_fam a ON plg_FamID = a.fam_ID
		 WHERE plg_depID = " . $iDepositSlipID . " ORDER BY pledge_plg.plg_date";
$rsPledges = RunQuery($sSQL);

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

	while ($aRow = mysql_fetch_array($rsPledges))
	{
		$OutStr = "";
		extract($aRow);

		// List all the checks and total the cash
		if ($plg_method == 'CASH') {
			$totalCash += $plg_amount;
		} else if ($plg_method == 'CHECK') {
			$numItems += 1;
			$totalChecks += $plg_amount;

			$pdf->PrintRightJustified ($curX, $curY, $plg_CheckNo);
			$pdf->PrintRightJustified ($curX + $amountOffsetX, $curY, $plg_amount);

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

$summaryFundX = 20;
$summaryMethodX = 50;
$summaryFromX = 75;
$summaryMemoX = 115;
$summaryAmountX = 185;
$summaryIntervalY = 4;

// Get the list of funds
$sSQL = "SELECT fun_ID,fun_Name,fun_Description,fun_Active FROM donationfund_fun";
if ($editorMode == 0) $sSQL .= " WHERE fun_Active = 'true'"; // New donations should show only active funds.
$rsFunds = RunQuery($sSQL);

//Get the payments for this deposit slip
$sSQL = "SELECT plg_plgID, plg_FYID, plg_date, plg_amount, plg_method, plg_CheckNo, 
         plg_comment, a.fam_Name AS FamilyName, b.fun_Name AS fundName
		 FROM pledge_plg
		 LEFT JOIN family_fam a ON plg_FamID = a.fam_ID
		 LEFT JOIN donationfund_fun b ON plg_fundID = b.fun_ID
		 WHERE plg_depID = " . $iDepositSlipID . " ORDER BY pledge_plg.plg_method DESC, pledge_plg.plg_date";
$rsPledges = RunQuery($sSQL);

//Get the payments for this deposit slip
//$sSQL = "SELECT plg_plgID, plg_date, plg_amount, plg_CheckNo, plg_method, plg_comment, a.fam_Name AS FamilyName
//		 FROM pledge_plg 
//		 LEFT JOIN family_fam a ON plg_FamID = a.fam_ID
//		 WHERE plg_depID = " . $iDepositSlipID . " ORDER BY pledge_plg.plg_method DESC, pledge_plg.plg_date";
//$rsPledges = RunQuery($sSQL);

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

   $fundTotal[$fundName] += $plg_amount;

	$pdf->PrintRightJustified ($curX, $curY, $plg_CheckNo);

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
	   if ($fundTotal[$fun_name] > 0) {
   		$pdf->SetXY ($curX, $curY);
   		$pdf->Write (8, $fun_name);
		  $amountStr = sprintf ("%.2f", $fundTotal[$fun_name]);
   		$pdf->PrintRightJustified ($curX + $summaryMethodX, $curY, $amountStr);
		  $curY += $summaryIntervalY;
	   }
	}
}

if ($iPDFOutputType == 1)
	$pdf->Output("Deposit-" . $iDepositSlipID . ".pdf", true);
else
	$pdf->Output();	
?>
