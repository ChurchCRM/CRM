<?php
/*******************************************************************************
*
*  filename    : Reports/ReminderReport.php
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

//Get the Fiscal Year ID out of the querystring
$iFYID = FilterInput($_GET["FYID"],'int');

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

// Get all the families
$sSQL = "SELECT * FROM family_fam WHERE 1";
$rsFamilies = RunQuery($sSQL);

// Get the list of funds
$sSQL = "SELECT fun_ID,fun_Name,fun_Description,fun_Active FROM donationfund_fun WHERE fun_Active = 'true'";
$rsFunds = RunQuery($sSQL);

// Get pledges and payments for this fiscal year
$sSQL = "SELECT *, b.fun_Name AS fundName FROM pledge_plg 
		 LEFT JOIN donationfund_fun b ON plg_fundID = b.fun_ID
		 WHERE plg_FYID = " . $iFYID;
$rsPledges = RunQuery($sSQL);

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
$pledgeX = 60;
$paymentX = 80;
$pledgeCountX = 100;
$paymentCountX = 120;
$curY = 20;

$pdf->WriteAt ($pdf->leftX, $curY, $pdf->sChurchName); $curY += $pdf->incrementY;
$pdf->WriteAt ($pdf->leftX, $curY, $pdf->sChurchAddress); $curY += $pdf->incrementY;
$pdf->WriteAt ($pdf->leftX, $curY, $pdf->sChurchCity . ", " . $pdf->sChurchState . "  " . $pdf->sChurchZip); $curY += $pdf->incrementY;
$pdf->WriteAt ($pdf->leftX, $curY, $pdf->sChurchPhone . "  " . $pdf->sChurchEmail); $curY += 2 * $pdf->incrementY;

$blurb = $pdf->sPledgeSummary1;
$blurb .= (1995 + $iFYID) . "/" . substr ((1995 + $iFYID + 1), 2, 2) . " ";
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

mysql_data_seek($rsFunds,0);
while ($row = mysql_fetch_array($rsFunds))
{
	$fun_name = $row["fun_Name"];
	if ($pledgeFundTotal[$fun_name] > 0 || $paymentFundTotal[$fun_name] > 0) {
   		$pdf->WriteAt ($nameX, $curY, $fun_name);
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
?>
