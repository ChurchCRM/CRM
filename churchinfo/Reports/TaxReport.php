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
require "../Include/ReportConfig.php";
require "../Include/ReportFunctions.php";

//Get the Fiscal Year ID out of the querystring
$iYear = FilterInput($_GET["Year"],'int');

// If CSVAdminOnly option is enabled and user is not admin, redirect to the menu.
if (!$_SESSION['bAdmin'] && $bCSVAdminOnly) {
	Redirect("Menu.php");
	exit;
}

// Load the FPDF library
LoadLib_FPDF();

class PDF_TaxReport extends FPDF {

	// Private properties
	var $_Char_Size   = 10;        // Character size
	var $_Font        = "Times";

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
		$this->Write (4, $str);
	}

	// Constructor
	function PDF_TaxReport() {
		global $paperFormat;
		parent::FPDF("P", "mm", $paperFormat);

		$this->_Font        = "Times";
		$this->SetMargins(20,20);
		$this->Open();
		$this->Set_Char_Size(10);
		$this->SetAutoPageBreak(false);
	}

	function WriteAt ($x, $y, $str) {
		$this->SetXY ($x, $y);
		$this->Write (4, $str);
	}

	function StartNewPage ($fam_Name, $fam_Address1, $fam_Address2, $fam_City, $fam_State, $fam_Zip, $fam_Country, $iYear) {
		$this->AddPage();
		
		$dateX = 170;
		$dateY = 25;

		$this->WriteAt ($dateX, $dateY, date("m/d/Y"));

		$leftX = 20;
		$topY = 35;
		$incrementY = 4;

		$curY = $topY;

		$this->WriteAt ($leftX, $curY, "Unitarian-Universalist Church of Nashua"); $curY += $incrementY;
		$this->WriteAt ($leftX, $curY, "58 Lowell Street"); $curY += $incrementY;
		$this->WriteAt ($leftX, $curY, "Nashua, New Hampshire  03064"); $curY += $incrementY;
		$this->WriteAt ($leftX, $curY, "(603) 882-1092  office@uunashua.org"); $curY += 2 * $incrementY;

		$this->WriteAt ($leftX, $curY, ($fam_Name . " Family")); $curY += $incrementY;
		if ($fam_Address1 != "") {
			$this->WriteAt ($leftX, $curY, $fam_Address1); $curY += $incrementY;
		}
		if ($fam_Address2 != "") {
			$this->WriteAt ($leftX, $curY, $fam_Address2); $curY += $incrementY;
		}
		$this->WriteAt ($leftX, $curY, $fam_City . ", " . $fam_State . "  " . $fam_Zip); $curY += $incrementY;
		if ($fam_Country != "" && $fam_Country != "USA") {
			$this->WriteAt ($leftX, $curY, $fam_Country); $curY += $incrementY;
		}
		$curY += 2 * $incrementY;
		$blurb = "This letter shows our record of your payments for " . $iYear . ".";
		$this->WriteAt ($leftX, $curY, $blurb);
		$curY += 2 * $incrementY;
		return ($curY);
	}

	function FinishPage ($curY) {
		$leftX = 20;
		$incrementY = 4;
		$curY += 2 * $incrementY;
		$blurb = "Your only goods and services received, if any, were intangible religious benefits as defined under the code of the Internal Revenue Service.";
		$this->WriteAt ($leftX, $curY, $blurb);
		$curY += 3 * $incrementY;
		$blurb = "If you have any questions or corrections to make to this report, please contact the church at the above number during business hours, 9am to 4pm, M-F.";
		$this->WriteAt ($leftX, $curY, $blurb);
		$curY += 2 * $incrementY;

		$curY += 2 * $incrementY;
		$this->WriteAt ($leftX, $curY, "Sincerely,");
		$curY += 4 * $incrementY;
		$this->WriteAt ($leftX, $curY, "<signed by>");
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

	$curY = $pdf->StartNewPage ($fam_Name, $fam_Address1, $fam_Address2, $fam_City, $fam_State, $fam_Zip, $fam_Country, $iYear);

	$summaryDateX = 20;
	$summaryCheckNoX = 40;
	$summaryMethodX = 60;
	$summaryFundX = 85;
	$summaryMemoX = 110;
	$summaryAmountX = 160;
	$summaryIntervalY = 4;

	$curY += 2 * $summaryIntervalY;
	$pdf->SetFont('Times','', 12);
	$pdf->WriteAt ($summaryDateX, $curY, 'Payments: ');
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
	$pdf->SetFont('Times','', 10);
	if ($cnt > 1) {
		$pdf->WriteAt ($summaryMemoX, $curY, "Total payments");
		$pdf->PrintRightJustified ($summaryAmountX, $curY, $totalAmount);
		$curY += $summaryIntervalY;
	}

	$pdf->FinishPage ($curY);
}

if ($iPDFOutputType == 1)
	$pdf->Output("TaxReport" . date("Ymd") . ".pdf", true);
else
	$pdf->Output();	
?>
