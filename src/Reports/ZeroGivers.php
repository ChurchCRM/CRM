<?php
/*******************************************************************************
*
*  filename    : Reports/ZeroGivers.php
*  last change : 2005-03-26
*  description : Creates a PDF with all the tax letters for a particular calendar year.
*  Copyright 2012 Michael Wilt
*
*  ChurchCRM is free software; you can redistribute it and/or modify
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

// Filter values
$output = FilterInput($_POST["output"]);
$sDateStart = FilterInput($_POST["DateStart"],"date");
$sDateEnd = FilterInput($_POST["DateEnd"],"date");

$letterhead = FilterInput($_POST["letterhead"]);
$remittance = FilterInput($_POST["remittance"]);

// If CSVAdminOnly option is enabled and user is not admin, redirect to the menu.
if (!$_SESSION['bAdmin'] && $bCSVAdminOnly && $output != "pdf") {
	Redirect("Menu.php");
	exit;
}

$today = date("Y-m-d");	
if (!$sDateEnd && $sDateStart)
	$sDateEnd = $sDateStart;
if (!$sDateStart && $sDateEnd)
	$sDateStart = $sDateEnd;
if (!$sDateStart && !$sDateEnd){
	$sDateStart = $today;
	$sDateEnd = $today;
}
if ($sDateStart > $sDateEnd){
	$temp = $sDateStart;
	$sDateStart = $sDateEnd;
	$sDateEnd = $temp;
}

// Build SQL Query
// Build SELECT SQL Portion
$sSQL = "SELECT DISTINCT fam_ID, fam_Name, fam_Address1, fam_Address2, fam_City, fam_State, fam_Zip, fam_Country FROM family_fam LEFT OUTER JOIN person_per ON fam_ID = per_fam_ID WHERE per_cls_ID=1 AND fam_ID NOT IN (SELECT DISTINCT plg_FamID FROM pledge_plg WHERE plg_date BETWEEN '$sDateStart' AND '$sDateEnd' AND plg_PledgeOrPayment = 'Payment') ORDER BY fam_ID";

//Execute SQL Statement
$rsReport = RunQuery($sSQL);

// Exit if no rows returned
$iCountRows = mysql_num_rows($rsReport);
if ($iCountRows < 1){
	header("Location: ../FinancialReports.php?ReturnMessage=NoRows&ReportType=Zero%20Givers"); 
}

// Create Giving Report -- PDF
// ***************************

if ($output == "pdf") {

	// Set up bottom border values
	if ($remittance == "yes"){
		$bottom_border1 = 134;
		$bottom_border2 = 180;
	} else {
		$bottom_border1 = 200;
		$bottom_border2 = 250;
	}

	class PDF_ZeroGivers extends ChurchInfoReport {

		// Constructor
		function PDF_ZeroGivers() {
			parent::FPDF("P", "mm", $this->paperFormat);
			$this->SetFont("Times",'',10);
			$this->SetMargins(20,20);
			$this->Open();
			$this->SetAutoPageBreak(false);
		}

		function StartNewPage ($fam_ID, $fam_Name, $fam_Address1, $fam_Address2, $fam_City, $fam_State, $fam_Zip, $fam_Country) {
			global $letterhead, $sDateStart, $sDateEnd;
			$curY = $this->StartLetterPage ($fam_ID, $fam_Name, $fam_Address1, $fam_Address2, $fam_City, $fam_State, $fam_Zip, $fam_Country, $letterhead);
			$curY += 2 * $this->incrementY;
			if ($sDateStart == $sDateEnd)
				$DateString = date("F j, Y",strtotime($sDateStart));
			else
				$DateString = date("M j, Y",strtotime($sDateStart)) . " - " .  date("M j, Y",strtotime($sDateEnd));

			$blurb = $this->sTaxReport1 . " " . $DateString . " $this->sZeroGivers";
			$this->WriteAt ($this->leftX, $curY, $blurb);
			$curY += 30 * $this->incrementY;

			return ($curY);
		}

		function FinishPage ($curY,$fam_ID,$fam_Name, $fam_Address1, $fam_Address2, $fam_City, $fam_State, $fam_Zip, $fam_Country) {
			global $remittance;
			$curY += 2 * $this->incrementY;
			$blurb = $this->sZeroGivers2;
			$this->WriteAt ($this->leftX, $curY, $blurb);
			$curY += 3 * $this->incrementY;
			$blurb = $this->sZeroGivers3;
			$this->WriteAt ($this->leftX, $curY, $blurb);
			$curY += 3 * $this->incrementY;
			$this->WriteAt ($this->leftX, $curY, "Sincerely,");
			$curY += 4 * $this->incrementY;
			$this->WriteAt ($this->leftX, $curY, $this->sTaxSigner);
			
		}
	}

	// Instantiate the directory class and build the report.
	$pdf = new PDF_ZeroGivers();
	
	// Read in report settings from database
	$rsConfig = mysql_query("SELECT cfg_name, IFNULL(cfg_value, cfg_default) AS value FROM config_cfg WHERE cfg_section='ChurchInfoReport'");
   if ($rsConfig) {
		while (list($cfg_name, $cfg_value) = mysql_fetch_row($rsConfig)) {
			$pdf->$cfg_name = $cfg_value;
		}
   }

	// Loop through result array
	while ($row = mysql_fetch_array($rsReport)) {
		extract ($row);
		$curY = $pdf->StartNewPage ($fam_ID, $fam_Name, $fam_Address1, $fam_Address2, $fam_City, $fam_State, $fam_Zip, $fam_Country);

		$pdf->FinishPage ($curY,$fam_ID,$fam_Name, $fam_Address1, $fam_Address2, $fam_City, $fam_State, $fam_Zip, $fam_Country);
	}

	if ($iPDFOutputType == 1)
		$pdf->Output("ZeroGivers" . date("Ymd") . ".pdf", "D");
	else
		$pdf->Output();


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
	$buffer = substr($buffer,0,-1) . $eol;
	
	// Add data
	while ($row = mysql_fetch_row($rsReport)) {
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
