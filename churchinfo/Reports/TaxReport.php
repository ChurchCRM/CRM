<?php
/*******************************************************************************
*
*  filename    : Reports/TaxReport.php
*  last change : 2003-08-30
*  description : Creates a PDF with all the tax letters for a particular calendar year.
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
$iYear = FilterInput($_GET["Year"],'int');

// If CSVAdminOnly option is enabled and user is not admin, redirect to the menu.
if (!$_SESSION['bAdmin'] && $bCSVAdminOnly) {
	Redirect("Menu.php");
	exit;
}

class PDF_TaxReport extends ChurchInfoReport {

	// Constructor
	function PDF_TaxReport() {
		parent::FPDF("P", "mm", $this->paperFormat);

		$this->SetFont("Times",'',10);
		$this->SetMargins(20,20);
		$this->Open();
		$this->SetAutoPageBreak(false);
	}

	function StartNewPage ($fam_ID, $fam_Name, $fam_Address1, $fam_Address2, $fam_City, $fam_State, $fam_Zip, $fam_Country, $iYear) {
      $curY = $this->StartLetterPage ($fam_ID, $fam_Name, $fam_Address1, $fam_Address2, $fam_City, $fam_State, $fam_Zip, $fam_Country, $iYear);
		$curY += 2 * $this->incrementY;
		$blurb = $this->sTaxReport1 . $iYear . ".";
		$this->WriteAt ($this->leftX, $curY, $blurb);
		$curY += 2 * $this->incrementY;
		return ($curY);
	}

	function FinishPage ($curY) {
		$curY += 2 * $this->incrementY;
		$blurb = $this->sTaxReport2;
		$this->WriteAt ($this->leftX, $curY, $blurb);
		$curY += 3 * $this->incrementY;
		$blurb = $this->sTaxReport3;
		$this->WriteAt ($this->leftX, $curY, $blurb);

		$curY += 4 * $this->incrementY;

		$this->WriteAt ($this->leftX, $curY, "Sincerely,");
		$curY += 4 * $this->incrementY;
		$this->WriteAt ($this->leftX, $curY, $this->sTaxSigner);
	}
}

// Instantiate the directory class and build the report.
$pdf = new PDF_TaxReport();

// Get all the families
$sSQL = "SELECT * FROM family_fam WHERE 1";
$rsFamilies = RunQuery($sSQL);

// Loop through families
while ($aFam = mysql_fetch_array($rsFamilies)) {
	extract ($aFam);

	// Get payments only
	$sSQL = "SELECT *, b.fun_Name AS fundName FROM pledge_plg 
			 LEFT JOIN donationfund_fun b ON plg_fundID = b.fun_ID
			 WHERE plg_FamID = " . $fam_ID . " AND plg_PledgeOrPayment = 'Payment' ORDER BY plg_date";
	$rsPledges = RunQuery($sSQL);

   $paymentsThisYear = 0;
	while ($aRow = mysql_fetch_array($rsPledges)) {
		extract ($aRow);

      if (substr ($plg_date, 0, 4) == $iYear)
         $paymentsThisYear += 1;
   }

   if ($paymentsThisYear == 0)
      continue;

	// Get payments only
	$sSQL = "SELECT *, b.fun_Name AS fundName FROM pledge_plg 
			 LEFT JOIN donationfund_fun b ON plg_fundID = b.fun_ID
			 WHERE plg_FamID = " . $fam_ID . " AND plg_PledgeOrPayment = 'Payment' ORDER BY plg_date";
	$rsPledges = RunQuery($sSQL);

	$curY = $pdf->StartNewPage ($fam_ID, $fam_Name, $fam_Address1, $fam_Address2, $fam_City, $fam_State, $fam_Zip, $fam_Country, $iYear);

	$summaryDateX = $pdf->leftX;
	$summaryCheckNoX = 40;
	$summaryMethodX = 60;
	$summaryFundX = 85;
	$summaryMemoX = 110;
	$summaryAmountX = 160;
	$summaryIntervalY = 4;

	$curY += 2 * $summaryIntervalY;
	$pdf->SetFont('Times','B', 10);

	$pdf->WriteAt ($summaryDateX, $curY, 'Date');
	$pdf->WriteAt ($summaryCheckNoX, $curY, 'Chk No.');
	$pdf->WriteAt ($summaryMethodX, $curY, 'PmtMethod');
	$pdf->WriteAt ($summaryFundX, $curY, 'Fund');
	$pdf->WriteAt ($summaryMemoX, $curY, 'Memo');
	$pdf->WriteAt ($summaryAmountX - 5, $curY, 'Amount');

	$curY += $summaryIntervalY;

	$totalAmount = 0;
	$cnt = 0;
	while ($aRow = mysql_fetch_array($rsPledges)) {
		extract ($aRow);
		$pdf->SetFont('Times','', 10);

      if (substr ($plg_date, 0, 4) != $iYear) // Skip over payments not appropriate year
         continue;

		$pdf->WriteAt ($summaryDateX, $curY, $plg_date);
		$pdf->PrintRightJustified ($summaryCheckNoX, $curY, $plg_CheckNo);
		$pdf->WriteAt ($summaryMethodX, $curY, $plg_method);
		$pdf->WriteAt ($summaryFundX, $curY, $fundName);
		$pdf->WriteAt ($summaryMemoX, $curY, $plg_comment);

		$pdf->SetFont('Courier','', 8);

		$pdf->PrintRightJustified ($summaryAmountX, $curY, $plg_amount);

		$totalAmount += $plg_amount;
		$cnt += 1;

		$curY += $summaryIntervalY;
	}

	if ($cnt > 1) {
   	$pdf->SetFont('Times','', 10);
		$pdf->WriteAt ($summaryMemoX, $curY, "Total payments");
      $totalAmountStr = sprintf ("%.2f", $totalAmount);
   	$pdf->SetFont('Courier','', 8);
		$pdf->PrintRightJustified ($summaryAmountX, $curY, $totalAmountStr);
		$curY += $summaryIntervalY;
	}

	$pdf->SetFont('Times','', 10);
	$pdf->FinishPage ($curY);
}

if ($iPDFOutputType == 1)
	$pdf->Output("TaxReport" . date("Ymd") . ".pdf", true);
else
	$pdf->Output();	
?>
