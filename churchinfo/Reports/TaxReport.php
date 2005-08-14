<?php
/*******************************************************************************
*
*  filename    : Reports/TaxReport.php
*  last change : 2005-03-26
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

// Security
if (!$_SESSION['bFinance'] && !$_SESSION['bAdmin']) {
	Redirect("Menu.php");
	exit;
}

// Filter values
$letterhead = FilterInput($_POST["letterhead"]);
$remittance = FilterInput($_POST["remittance"]);
$output = FilterInput($_POST["output"]);
$sReportType = FilterInput($_POST["ReportType"]);
$sDateStart = FilterInput($_POST["DateStart"],"date");
$sDateEnd = FilterInput($_POST["DateEnd"],"date");
$iDepID = FilterInput($_POST["deposit"],"int");
$iFYID = FilterInput($_POST["FYID"],"int");
$iMinimum = FilterInput($_POST["minimum"],"int");

// If CSVAdminOnly option is enabled and user is not admin, redirect to the menu.
if (!$_SESSION['bAdmin'] && $bCSVAdminOnly && $output != "pdf") {
	Redirect("Menu.php");
	exit;
}

// Build SQL Query
// Build SELECT SQL Portion
$sSQL = "SELECT fam_ID, fam_Name, fam_Address1, fam_Address2, fam_City, fam_State, fam_Zip, fam_Country, plg_date, plg_amount, plg_method, plg_comment, plg_CheckNo, fun_Name, plg_PledgeOrPayment, plg_NonDeductible FROM family_fam
	INNER JOIN pledge_plg ON fam_ID=plg_FamID
	LEFT JOIN donationfund_fun ON plg_fundID=fun_ID
	WHERE plg_PledgeOrPayment='Payment' ";

// Add  SQL criteria
// Report Dates OR Deposit ID
if ($iDepID > 0)
	$sSQL .= " AND plg_depID='$iDepID' ";
else {
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
	$sSQL .= " AND plg_date BETWEEN '$sDateStart' AND '$sDateEnd' ";
}

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
// Filter by Family
if (!empty($_POST["family"])) {
	$count = 0;
	foreach ($_POST["family"] as $famID) {
		$fam[$count++] = FilterInput($famID,'int');
	}
	if ($count == 1) {
		if ($fam[0])
			$sSQL .= " AND plg_FamID='$fam[0]' ";
	} else {
		$sSQL .= " AND (plg_FamID='$fam[0]'";
		for($i = 1; $i < $count; $i++) {
			$sSQL .= " OR plg_FamID='$fam[$i]'";
		}
		$sSQL .= ") ";
	}
}

// Get Criteria string
eregi("WHERE (plg_PledgeOrPayment.*)", $sSQL, $aSQLCriteria);

// Add SQL ORDER
$sSQL .= " ORDER BY plg_FamID, plg_date ";

//Execute SQL Statement
$rsReport = RunQuery($sSQL);

// Exit if no rows returned
$iCountRows = mysql_num_rows($rsReport);
if ($iCountRows < 1){
	header("Location: ../FinancialReports.php?ReturnMessage=NoRows&ReportType=Giving%20Report"); 
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
			global $letterhead, $sDateStart, $sDateEnd, $iDepID, $iFYID;
			$curY = $this->StartLetterPage ($fam_ID, $fam_Name, $fam_Address1, $fam_Address2, $fam_City, $fam_State, $fam_Zip, $fam_Country, $iYear, $letterhead);
			$curY += 2 * $this->incrementY;
			if ($iFYID > 0)
				$DateString = gettext("Fiscal Year ") . ($iFYID + 1995) . ".";
			else {
				if ($iDepID) {
					// Get Deposit Date
					$sSQL = "SELECT dep_Date, dep_Date FROM deposit_dep WHERE dep_ID='$iDepID'";
					$rsDep = RunQuery($sSQL);
					list($sDateStart, $sDateEnd) = mysql_fetch_row($rsDep);
				}
				if ($sDateStart == $sDateEnd)
					$DateString = date("F j, Y",strtotime($sDateStart));
				else
					$DateString = date("M j, Y",strtotime($sDateStart)) . " through " .  date("M j, Y",strtotime($sDateEnd));
			}
			$blurb = $this->sTaxReport1 . $DateString . ".";
			$this->WriteAt ($this->leftX, $curY, $blurb);
			$curY += 2 * $this->incrementY;
			return ($curY);
		}

		function FinishPage ($curY,$fam_ID,$fam_Name, $fam_Address1, $fam_Address2, $fam_City, $fam_State, $fam_Zip, $fam_Country) {
			global $remittance;
			$curY += 2 * $this->incrementY;
			$blurb = $this->sTaxReport2;
			$this->WriteAt ($this->leftX, $curY, $blurb);
			$curY += 3 * $this->incrementY;
			$blurb = $this->sTaxReport3;
			$this->WriteAt ($this->leftX, $curY, $blurb);
			$curY += 3 * $this->incrementY;
			$this->WriteAt ($this->leftX, $curY, "Sincerely,");
			$curY += 4 * $this->incrementY;
			$this->WriteAt ($this->leftX, $curY, $this->sTaxSigner);
			
			if ($remittance == "yes"){
				// Add remittance slip
				$curY = 194;
				$curX = 60;
				$this->WriteAt ($curX, $curY, gettext("Please detach this slip and mail with your next gift."));
				$curY += (1.5 * $this->incrementY);
				$church_mailing = gettext("Please mail you next gift to ") . $this->sChurchName . ", " 
					. $this->sChurchAddress . ", " . $this->sChurchCity . ", " . $this->sChurchState . "  " 
					. $this->sChurchZip . gettext(", Phone: ") . $this->sChurchPhone;
				$this->SetFont('Times','I', 10);
				$this->WriteAt ($this->leftX, $curY, $church_mailing);
				$this->SetFont('Times','', 10);
				$curY =215;
				$this->WriteAt ($this->leftX, $curY, $this->MakeSalutation ($fam_ID)); $curY += $this->incrementY;
				if ($fam_Address1 != "") {
					$this->WriteAt ($this->leftX, $curY, $fam_Address1); $curY += $this->incrementY;
				}
				if ($fam_Address2 != "") {
					$this->WriteAt ($this->leftX, $curY, $fam_Address2); $curY += $this->incrementY;
				}
				$this->WriteAt ($this->leftX, $curY, $fam_City . ", " . $fam_State . "  " . $fam_Zip); $curY += $this->incrementY;
				if ($fam_Country != "" && $fam_Country != "USA" && $fam_Country != "United States") {
					$this->WriteAt ($this->leftX, $curY, $fam_Country); $curY += $this->incrementY;
				}
				$curX = 30;
				$curY = 246;
				$this->WriteAt ($this->leftX+5, $curY, $this->sChurchName); $curY += $this->incrementY;
				if ($this->sChurchAddress != "") {
					$this->WriteAt ($this->leftX+5, $curY, $this->sChurchAddress); $curY += $this->incrementY;
				}
				$this->WriteAt ($this->leftX+5, $curY, $this->sChurchCity . ", " . $this->sChurchState . "  " . $this->sChurchZip); $curY += $this->incrementY;
				if ($fam_Country != "" && $fam_Country != "USA" && $fam_Country != "United States") {
					$this->WriteAt ($this->leftX+5, $curY, $fam_Country); $curY += $this->incrementY;
				}
				$curX = 100;
				$curY = 215;
				$this->WriteAt ($curX, $curY, gettext("Gift Amount:"));
				$this->WriteAt ($curX + 25, $curY, "_______________________________");
				$curY += (2 * $this->incrementY);
				$this->WriteAt ($curX, $curY, gettext("Gift Designation:"));
				$this->WriteAt ($curX + 25, $curY, "_______________________________");
				$curY = 200 + (11 * $this->incrementY);
			}
		}
	}

	// Instantiate the directory class and build the report.
	$pdf = new PDF_TaxReport();
	
	// Read in report settings from database
	$rsConfig = mysql_query("SELECT cfg_name, IFNULL(cfg_value, cfg_default) AS value FROM config_cfg WHERE cfg_section='ChurchInfoReport'");
   if ($rsConfig) {
		while (list($cfg_name, $cfg_value) = mysql_fetch_row($rsConfig)) {
			$pdf->$cfg_name = $cfg_value;
		}
   }

	// Loop through result array
	$currentFamilyID = 0;
	while ($row = mysql_fetch_array($rsReport)) {
		extract ($row);
		
		// Check for minimum amount
		if ($iMinimum > 0){
			$temp = "SELECT SUM(plg_amount) AS total_gifts FROM pledge_plg
				WHERE plg_FamID=$fam_ID AND $aSQLCriteria[1]";
			$rsMinimum = RunQuery($temp);
			list ($total_gifts) = mysql_fetch_row($rsMinimum);
			if ($iMinimum > $total_gifts)
				continue;
		}
		// Check for new family
		if ($fam_ID != $currentFamilyID && $currentFamilyID != 0) {
			//New Family. Finish Previous Family
			$pdf->SetFont('Times','B', 10);
			$pdf->Cell (20, $summaryIntervalY / 2, " ",0,1);
			$pdf->Cell (95, $summaryIntervalY, " ");
			$pdf->Cell (50, $summaryIntervalY, "Total Payments:");
			$totalAmountStr = "$" . number_format($totalAmount,2);
			$pdf->SetFont('Courier','', 9);
			$pdf->Cell (25, $summaryIntervalY, $totalAmountStr, 0,1,"R");
			$pdf->SetFont('Times','B', 10);
			$pdf->Cell (95, $summaryIntervalY, " ");
			$pdf->Cell (50, $summaryIntervalY, "Goods and Services Rendered:");
			$totalAmountStr = "$" . number_format($totalNonDeductible,2);
			$pdf->SetFont('Courier','', 9);
			$pdf->Cell (25, $summaryIntervalY, $totalAmountStr, 0,1,"R");
			$pdf->SetFont('Times','B', 10);
			$pdf->Cell (95, $summaryIntervalY, " ");
			$pdf->Cell (50, $summaryIntervalY, "Tax-Deductible Contribution:");
			$totalAmountStr = "$" . number_format($totalAmount-$totalNonDeductible,2);
			$pdf->SetFont('Courier','', 9);
			$pdf->Cell (25, $summaryIntervalY, $totalAmountStr, 0,1,"R");
			$curY = $pdf->GetY();
			$curY = $pdf->GetY();

			if ($curY > $bottom_border1){
				$pdf->AddPage ();
				if ($letterhead == "none") {
					// Leave blank space at top on all pages for pre-printed letterhead
					$curY = 20 + ($summaryIntervalY * 3) + 25;
					$pdf->SetY($curY);
				} else {	
					$curY = 20;
					$pdf->SetY(20);
				}
			}
			$pdf->SetFont('Times','', 10);
			$pdf->FinishPage ($curY,$prev_fam_ID,$prev_fam_Name, $prev_fam_Address1, $prev_fam_Address2, $prev_fam_City, $prev_fam_State, $prev_fam_Zip, $prev_fam_Country);
		}

		// Start Page for New Family
		if ($fam_ID != $currentFamilyID) {
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
			$pdf->SetXY($summaryDateX,$curY);
			$pdf->Cell (20, $summaryIntervalY, 'Date');
			$pdf->Cell (20, $summaryIntervalY, 'Chk No.',0,0,"C");
			$pdf->Cell (25, $summaryIntervalY, 'PmtMethod');
			$pdf->Cell (40, $summaryIntervalY, 'Fund');
			$pdf->Cell (40, $summaryIntervalY, 'Memo');
			$pdf->Cell (25, $summaryIntervalY, 'Amount',0,1,"R");
			//$curY = $pdf->GetY();
			$totalAmount = 0;
			$totalNonDeductible = 0;
			$cnt = 0;
			$currentFamilyID = $fam_ID;
		}
		// Format Data
		if (strlen($plg_CheckNo) > 8)
			$plg_CheckNo = "...".substr($plg_CheckNo,-8,8);
		else
			$plg_CheckNo .= "    ";
		if (strlen($fun_Name) > 25)
			$fun_Name = substr($fun_Name,0,25) . "...";
		if (strlen($plg_comment) > 25)
			$plg_comment = substr($plg_comment,0,25) . "...";
		// Print Gift Data
		$pdf->SetFont('Times','', 10);
		$pdf->Cell (20, $summaryIntervalY, $plg_date);
		$pdf->Cell (20, $summaryIntervalY, $plg_CheckNo,0,0,"R");
		$pdf->Cell (25, $summaryIntervalY, $plg_method);
		$pdf->Cell (40, $summaryIntervalY, $fun_Name);
		$pdf->Cell (40, $summaryIntervalY, $plg_comment);
		$pdf->SetFont('Courier','', 9);
		$pdf->Cell (25, $summaryIntervalY, $plg_amount,0,1,"R");
		$totalAmount += $plg_amount;
		$totalNonDeductible += $plg_NonDeductible;
		$cnt += 1;
		$curY = $pdf->GetY();

		if ($curY > $bottom_border2) {
			$pdf->AddPage ();
			if ($letterhead == "none") {
				// Leave blank space at top on all pages for pre-printed letterhead
				$curY = 20 + ($summaryIntervalY * 3) + 25;
				$pdf->SetY($curY);
			} else {	
				$curY = 20;
				$pdf->SetY(20);
			}
		}
		$prev_fam_ID = $fam_ID;
		$prev_fam_Name = $fam_Name;
		$prev_fam_Address1 = $fam_Address1;
		$prev_fam_Address2 = $fam_Address2;
		$prev_fam_City = $fam_City;
		$prev_fam_State = $fam_State;
		$prev_fam_Zip = $fam_Zip;
		$prev_fam_Country = $fam_Country;
	}

	// Finish Last Report
	$pdf->SetFont('Times','B', 10);
	$pdf->Cell (20, $summaryIntervalY / 2, " ",0,1);
	$pdf->Cell (95, $summaryIntervalY, " ");
	$pdf->Cell (50, $summaryIntervalY, "Total Payments:");
	$totalAmountStr = "$" . number_format($totalAmount,2);
	$pdf->SetFont('Courier','', 9);
	$pdf->Cell (25, $summaryIntervalY, $totalAmountStr, 0,1,"R");
	$pdf->SetFont('Times','B', 10);
	$pdf->Cell (95, $summaryIntervalY, " ");
	$pdf->Cell (50, $summaryIntervalY, "Goods and Services Rendered:");
	$totalAmountStr = "$" . number_format($totalNonDeductible,2);
	$pdf->SetFont('Courier','', 9);
	$pdf->Cell (25, $summaryIntervalY, $totalAmountStr, 0,1,"R");
	$pdf->SetFont('Times','B', 10);
	$pdf->Cell (95, $summaryIntervalY, " ");
	$pdf->Cell (50, $summaryIntervalY, "Tax-Deductible Contribution:");
	$totalAmountStr = "$" . number_format($totalAmount-$totalNonDeductible,2);
	$pdf->SetFont('Courier','', 9);
	$pdf->Cell (25, $summaryIntervalY, $totalAmountStr, 0,1,"R");
	$curY = $pdf->GetY();

	if ($cnt > 0) {
		if ($curY > $bottom_border1){
			$pdf->AddPage ();
			if ($letterhead == "none") {
				// Leave blank space at top on all pages for pre-printed letterhead
				$curY = 20 + ($summaryIntervalY * 3) + 25;
				$pdf->SetY($curY);
			} else {	
				$curY = 20;
				$pdf->SetY(20);
			}
		}
		$pdf->SetFont('Times','', 10);
		$pdf->FinishPage ($curY,$fam_ID,$fam_Name, $fam_Address1, $fam_Address2, $fam_City, $fam_State, $fam_Zip, $fam_Country);
	}

	if ($iPDFOutputType == 1)
		$pdf->Output("TaxReport" . date("Ymd") . ".pdf", true);
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
	header("Content-Disposition: attachment; filename=ChurchInfo-" . date("Ymd-Gis") . ".csv");
	echo $buffer;
}
	
?>