<?php
/*******************************************************************************
*
*  filename    : Reports/AdvancedDeposit.php
*  last change : 2005-03-29
*  description : Creates a PDF customized Deposit Report .
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
$sort = FilterInput($_POST["sort"]);
$detail_level = FilterInput($_POST["detail_level"]);
$datetype = FilterInput($_POST["datetype"]);
$output = FilterInput($_POST["output"]);
$sDateStart = FilterInput($_POST["DateStart"],"date");
$sDateEnd = FilterInput($_POST["DateEnd"],"date");
$iDepID = FilterInput($_POST["deposit"],"int");

if (!$sort)
	$sort = "deposit";
if (!$detail_level)
	$detail_level = "detail";
if (!$output)
	$output = "pdf"; 

// If CSVAdminOnly option is enabled and user is not admin, redirect to the menu.
if (!$_SESSION['bAdmin'] && $bCSVAdminOnly && $output != "pdf") {
	Redirect("Menu.php");
	exit;
}

// Build SQL Query
// Build SELECT SQL Portion
$sSQL = "SELECT fam_ID, fam_Name, fam_Address1, fam_Address2, fam_City, fam_State, fam_Zip, fam_Country, plg_date, plg_amount, plg_method, plg_comment, plg_depID, plg_CheckNo, fun_ID, fun_Name, dep_Date FROM pledge_plg
	LEFT JOIN family_fam ON plg_FamID=fam_ID
	INNER JOIN deposit_dep ON plg_depID = dep_ID
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
	if ($datetype == "Payment")
		$sSQL .= " AND plg_date BETWEEN '$sDateStart' AND '$sDateEnd' ";
	else
		$sSQL .= " AND dep_Date BETWEEN '$sDateStart' AND '$sDateEnd' ";
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

// Filter by Payment Method
if (!empty($_POST["method"])) {
	$count = 0;
	foreach ($_POST["method"] as $MethodItem) {
		$aMethod[$count++] = FilterInput($MethodItem);
	}
	if ($count == 1) {
		if ($aMethod[0])
			$sSQL .= " AND plg_method='$aMethod[0]' ";
	} else {
		$sSQL .= " AND (plg_method='$aMethod[0]' ";
		for($i = 1; $i < $count; $i++) {
			$sSQL .= " OR plg_method='$aMethod[$i]'";
		}
		$sSQL .= ") ";
	}
}

// Add SQL ORDER
if ($sort == "deposit")
	$sSQL .= " ORDER BY plg_depID, fun_Name, fam_Name, fam_ID";
elseif ($sort == "fund")
	$sSQL .= " ORDER BY fun_Name, fam_Name, fam_ID, plg_depID ";
elseif ($sort == "family")
	$sSQL .= " ORDER BY fam_Name, fam_ID, fun_Name, plg_depID";

//Execute SQL Statement
$rsReport = RunQuery($sSQL);

// Exit if no rows returned
$iCountRows = mysql_num_rows($rsReport);
if ($iCountRows < 1){
	header("Location: ../FinancialReports.php?ReturnMessage=NoRows&ReportType=Advanced%20Deposit%20Report"); 
}

// Create PDF Report -- PDF
// ***************************

if ($output == "pdf") {

	// Set up bottom border value
	$bottom_border = 250;
	$summaryIntervalY = 4;
	$page = 1;
	
	class PDF_TaxReport extends ChurchInfoReport {

		// Constructor
		function PDF_TaxReport() {
			parent::FPDF("P", "mm", $this->paperFormat);
			$this->SetFont("Times",'',10);
			$this->SetMargins(20,15);
			$this->Open();
			$this->SetAutoPageBreak(false);
		}
		
		function PrintRightJustified ($x, $y, $str) {
			$iLen = strlen ($str);
			$nMoveBy = 2 * $iLen;
			$this->SetXY ($x - $nMoveBy, $y);
			$this->Write (8, $str);
		}

		function StartFirstPage () {
			global $sDateStart, $sDateEnd, $sort, $iDepID, $datetype;
			$this->AddPage();
			$curY = 20;
			$curX = 60;
			$this->SetFont('Times','B', 14);
			$this->WriteAt ($curX, $curY, $this->sChurchName . " Deposit Report");
			$curY += 2 * $this->incrementY;
			$this->SetFont('Times','B', 10);
			$curX = $this->leftX;
			$this->WriteAt ($curX, $curY, "Data sorted by " . ucwords($sort));
			$curY += $this->incrementY;
			if (!$iDepID) {
				$this->WriteAt ($curX, $curY, "$datetype Dates: $sDateStart through $sDateEnd");
				$curY += $this->incrementY;
			}
			if ($iDepID || $_POST['family'][0] || $_POST['funds'][0] || $_POST['method'][0]){
				$heading = "Filtered by ";
				if ($iDepID)
					$heading .= "Deposit #$iDepID, ";
				if ($_POST['family'][0])
					$heading .= "Selected Families, ";
				if ($_POST['funds'][0])
					$heading .= "Selected Funds, ";
				if ($_POST['method'][0])
					$heading .= "Selected Payment Methods, ";
				$heading = substr($heading,0,-2);
			} else {
				$heading = "Showing all records for report dates.";
			}
			$this->WriteAt ($curX, $curY, $heading);
			$curY += 2 * $this->incrementY;
			$this->SetFont("Times",'',10);
			return ($curY);
		}
		
		function PageBreak ($page) {
			// Finish footer of previous page if neccessary and add new page
			global $curY, $bottom_border, $detail_level;
			if ($curY > $bottom_border){
				$this->FinishPage($page);
				$page++;
				$this->AddPage();
				$curY = 20;
				if ($detail_level == "detail")
					$curY = $this->Headings($curY);
			}
			return $page;
		}
		
		function Headings ($curY) {
			global $sort, $summaryIntervalY;
			if ($sort == "deposit"){
				$curX = $this->leftX;
				$this->SetFont('Times','BU', 10);
				$this->WriteAt ($curX, $curY, "Chk No.");
				$this->WriteAt (40, $curY, "Fund");
				$this->WriteAt (80, $curY, "Recieved From");
				$this->WriteAt (135, $curY, "Memo");
				$this->WriteAt (181, $curY, "Amount");
				$curY += 2 * $summaryIntervalY;
			} elseif ($sort == "fund") {
				$curX = $this->leftX;
				$this->SetFont('Times','BU', 10);
				$this->WriteAt ($curX, $curY, "Chk No.");
				$this->WriteAt (40, $curY, "Deposit No./ Date");
				$this->WriteAt (80, $curY, "Recieved From");
				$this->WriteAt (135, $curY, "Memo");
				$this->WriteAt (181, $curY, "Amount");
				$curY += 2 * $summaryIntervalY;
			} elseif ($sort == "family") {
				$curX = $this->leftX;
				$this->SetFont('Times','BU', 10);
				$this->WriteAt ($curX, $curY, "Chk No.");
				$this->WriteAt (40, $curY, "Deposit No./Date");
				$this->WriteAt (80, $curY, "Fund");
				$this->WriteAt (135, $curY, "Memo");
				$this->WriteAt (181, $curY, "Amount");
				$curY += 2 * $summaryIntervalY;			
			}
			return ($curY);
		}

		function FinishPage ($page) {
			$footer = "Page $page   Generated on " . date("Y-m-d H:i:s");
			$this->SetFont("Times",'I',9);
			$this->WriteAt (80, 258, $footer);
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
	
	$curY = $pdf->StartFirstPage ();
	
	// **********************
	// Sort by Deposit Report
	// **********************
	if ($sort == "deposit") {
		if ($detail_level == "detail")
			$curY = $pdf->Headings($curY);
		
		while ($aRow = mysql_fetch_array($rsReport)) {
			extract ($aRow);
			if (!$fun_ID){
				$fun_ID = -1;
				$fun_Name = "Undesignated";
			}
			if (!$fam_ID) {
				$fam_ID = -1;
				$fam_Name = "Unassigned";
			}
			// First Deposit Heading
			if (!$currentDepositID && $detail_level != "summary"){
				$sDepositTitle = "Deposit #$plg_depID ($dep_Date)";
				$pdf->SetFont("Times",'B',10);
				$pdf->WriteAt(20,$curY, $sDepositTitle);
				$curY += 1.5 * $summaryIntervalY;
			}
			// Check for new fund
			if (($currentFundID != $fun_ID || $currentDepositID != $plg_depID) && $currentFundID && $detail_level != "summary") {
				// New Fund. Print Previous Fund Summary
				if ($countFund > 1)
					$item = gettext("items");
				else
					$item = gettext("item");
				$sFundSummary = "$currentFundName Total - $countFund $item:   $" . number_format($currentFundAmount, 2, '.', ','); 
				$curY +=2;
				$pdf->SetXY(20,$curY);
				$pdf->SetFont("Times",'I',10);
				$pdf->Cell (176, $summaryIntervalY, $sFundSummary,0,0,"R");
				$curY += 1.75 * $summaryIntervalY;
				$countFund = 0;
				$currentFundAmount = 0;
				$page = $pdf->PageBreak($page);
			}
			// Check for new deposit
			if ($currentDepositID != $plg_depID && $currentDepositID) {
				// New Deposit ID.  Print Previous Deposit Summary
				if ($countDeposit > 1)
					$item = gettext("items");
				else
					$item = gettext("item");
				$sDepositSummary = "Deposit #$currentDepositID Total - $countDeposit $item:   $" . number_format($currentDepositAmount, 2, '.', ',');
				$pdf->SetXY(20,$curY);
				$pdf->SetFont("Times",'B',10);
				$pdf->Cell (176, $summaryIntervalY, $sDepositSummary,0,0,"R");
				$curY += 2 * $summaryIntervalY;
				if ($detail_level != "summary")
					$pdf->line(40,$curY-2,195,$curY-2);
				$page = $pdf->PageBreak($page);

				// New Deposit Title
				if  ($detail_level != "summary") {
					$sDepositTitle = "Deposit #$plg_depID ($dep_Date)";
					$pdf->SetFont("Times",'B',10);
					$pdf->WriteAt(20,$curY, $sDepositTitle);
					$curY += 1.5 * $summaryIntervalY;
				}
				$countDeposit = 0;
				$currentDepositAmount = 0;
			}
			
			// Print Deposit Detail
			if ($detail_level == "detail"){
				// Format Data
				if ($plg_method == "CREDITCARD")
					$plg_method = "CREDIT";
				if ($plg_method == "BANKDRAFT")
					$plg_method = "DRAFT";
				if ($plg_method != "CHECK")
					$plg_CheckNo = $plg_method;
				if (strlen($plg_CheckNo) > 8)
					$plg_CheckNo = "...".substr($plg_CheckNo,-8,8);
				if (strlen($fun_Name) > 22)
					$sfun_Name = substr($fun_Name,0,21) . "...";
				else
					$sfun_Name = $fun_Name;
				if (strlen($plg_comment) > 29)
					$plg_comment = substr($plg_comment,0,28) . "...";
				$fam_Name = $fam_Name . " - " . $fam_Address1;
				if (strlen($fam_Name) > 31)
					$fam_Name = substr($fam_Name,0,30) . "...";
				
				// Print Data
				$pdf->SetFont('Times','', 10);
				$pdf->SetXY($pdf->leftX,$curY);
				$pdf->Cell (16, $summaryIntervalY, $plg_CheckNo,0,0,"R");
				$pdf->Cell (40, $summaryIntervalY, $sfun_Name);
				$pdf->Cell (55, $summaryIntervalY, $fam_Name);
				$pdf->Cell (40, $summaryIntervalY, $plg_comment);
				$pdf->SetFont('Courier','', 9);
				$pdf->Cell (25, $summaryIntervalY, $plg_amount,0,0,"R");
				$pdf->SetFont('Times','', 10);
				$curY += $summaryIntervalY;
				$page = $pdf->PageBreak($page);
			}
			// Update running totals
			$totalAmount += $plg_amount;
			$totalFund[$fun_Name] += $plg_amount;
			$countFund ++;
			$countDeposit ++;
			$countReport ++;
			$currentFundAmount += $plg_amount;
			$currentDepositAmount += $plg_amount;
			$currentReportAmount += $plg_amount;
			$currentDepositID = $plg_depID;
			$currentFundID = $fun_ID;
			$currentFundName = $fun_Name;
			$currentDepositDate = $dep_Date;
		}
		
		// Print Final Summary	
		// Print Fund Summary
		if ($detail_level != "summary") {
			if ($countFund > 1)
				$item = gettext("items");
			else
				$item = gettext("item");
			$sFundSummary = "$fun_Name Total - $countFund $item:   $" . number_format($currentFundAmount, 2, '.', ',');
			$curY += 2;
			$pdf->SetXY(20,$curY);
			$pdf->SetFont("Times",'I',10);
			$pdf->Cell (176, $summaryIntervalY, $sFundSummary,0,0,"R");
			$curY += 1.75 * $summaryIntervalY;
			$page = $pdf->PageBreak($page);
		}
		// Print Deposit Summary
		if ($countDeposit > 1)
			$item = gettext("items");
		else
			$item = gettext("item");
		$sDepositSummary = "Deposit #$currentDepositID Total - $countDeposit $item:   $" . number_format($currentDepositAmount, 2, '.', ',');
		$pdf->SetXY(20,$curY);
		$pdf->SetFont("Times",'B',10);
		$pdf->Cell (176, $summaryIntervalY, $sDepositSummary,0,0,"R");
		$curY += 2 * $summaryIntervalY;
		$page = $pdf->PageBreak($page);

		
	} elseif ($sort == "fund") {
	
		// **********************
		// Sort by Fund  Report
		// **********************
		
		if ($detail_level == "detail")
			$curY = $pdf->Headings($curY);
		
		while ($aRow = mysql_fetch_array($rsReport)) {
			extract ($aRow);
			if (!$fun_ID){
				$fun_ID = -1;
				$fun_Name = "Undesignated";
			}
			if (!$fam_ID) {
				$fam_ID = -1;
				$fam_Name = "Unassigned";
			}
			// First Fund Heading
			if (!$currentFundName && $detail_level != "summary"){
				$sFundTitle = "Fund: $fun_Name";
				$pdf->SetFont("Times",'B',10);
				$pdf->WriteAt(20,$curY, $sFundTitle);
				$curY += 1.5 * $summaryIntervalY;
			}
			// Check for new Family
			if (($currentFamilyID != $fam_ID || $currentFundID != $fun_ID) && $currentFamilyID && $detail_level != "summary") {
				// New Family. Print Previous Family Summary
				if ($countFamily > 1)
					$item = gettext("items");
				else
					$item = gettext("item");
				$sFamilySummary = "$currentFamilyName - $currentFamilyAddress - $countFamily $item:   $" . number_format($currentFamilyAmount, 2, '.', ','); 
				$curY +=2;
				$pdf->SetXY(20,$curY);
				$pdf->SetFont("Times",'I',10);
				$pdf->Cell (176, $summaryIntervalY, $sFamilySummary,0,0,"R");
				$curY += 1.75 * $summaryIntervalY;
				$countFamily = 0;
				$currentFamilyAmount = 0;
				$page = $pdf->PageBreak($page);
			}
			// Check for new Fund
			if ($currentFundID != $fun_ID && $currentFundID) {
				// New Fund ID.  Print Previous Fund Summary
				if ($countFund > 1)
					$item = gettext("items");
				else
					$item = gettext("item");
				$sFundSummary = "$currentFundName Total - $countFund $item:   $" . number_format($currentFundAmount, 2, '.', ',');
				$pdf->SetXY(20,$curY);
				$pdf->SetFont("Times",'B',10);
				$pdf->Cell (176, $summaryIntervalY, $sFundSummary,0,0,"R");
				$curY += 2 * $summaryIntervalY;
				if ($detail_level != "summary")
					$pdf->line(40,$curY-2,195,$curY-2);
				$page = $pdf->PageBreak($page);

				// New Fund Title
				if  ($detail_level != "summary") {
					$sFundTitle = "Fund: $fun_Name";
					$pdf->SetFont("Times",'B',10);
					$pdf->WriteAt(20,$curY, $sFundTitle);
					$curY += 1.5 * $summaryIntervalY;
				}
				$countFund = 0;
				$currentFundAmount = 0;
			}
			
			// Print Deposit Detail
			if ($detail_level == "detail"){
				// Format Data
				if ($plg_method == "CREDITCARD")
					$plg_method = "CREDIT";
				if ($plg_method == "BANKDRAFT")
					$plg_method = "DRAFT";
				if ($plg_method != "CHECK")
					$plg_CheckNo = $plg_method;
				if (strlen($plg_CheckNo) > 8)
					$plg_CheckNo = "...".substr($plg_CheckNo,-8,8);
				$sDeposit = "Dep #$plg_depID $dep_Date";
				if (strlen($sDeposit) > 22)
					$sDeposit = substr($sDeposit,0,21) . "...";
				if (strlen($plg_comment) > 29)
					$plg_comment = substr($plg_comment,0,28) . "...";
				$fam_Name = $fam_Name . " - " . $fam_Address1;
				if (strlen($fam_Name) > 31)
					$fam_Name = substr($fam_Name,0,30) . "...";
				
				// Print Data
				$pdf->SetFont('Times','', 10);
				$pdf->SetXY($pdf->leftX,$curY);
				$pdf->Cell (16, $summaryIntervalY, $plg_CheckNo,0,0,"R");
				$pdf->Cell (40, $summaryIntervalY, $sDeposit);
				$pdf->Cell (55, $summaryIntervalY, $fam_Name);
				$pdf->Cell (40, $summaryIntervalY, $plg_comment);
				$pdf->SetFont('Courier','', 9);
				$pdf->Cell (25, $summaryIntervalY, $plg_amount,0,0,"R");
				$pdf->SetFont('Times','', 10);
				$curY += $summaryIntervalY;
				$page = $pdf->PageBreak($page);
			}
			// Update running totals
			$totalAmount += $plg_amount;
			$totalFund[$fun_Name] += $plg_amount;
			$countFund ++;
			$countFamily ++;
			$countReport ++;
			$currentFundAmount += $plg_amount;
			$currentFamilyAmount += $plg_amount;
			$currentReportAmount += $plg_amount;
			$currentFamilyID = $fam_ID;
			$currentFamilyName = $fam_Name;
			$currentFundID = $fun_ID;
			$currentFundName = $fun_Name;
			$currentFamilyAddress = $fam_Address1;
		}
		
		// Print Final Summary	
		// Print Family Summary
		if ($detail_level != "summary") {
			if ($countFamily > 1)
				$item = gettext("items");
			else
				$item = gettext("item");
			$sFamilySummary = "$currentFamilyName - $currentFamilyAddress - $countFamily $item:   $" . number_format($currentFamilyAmount, 2, '.', ','); 
			$curY +=2;
			$pdf->SetXY(20,$curY);
			$pdf->SetFont("Times",'I',10);
			$pdf->Cell (176, $summaryIntervalY, $sFamilySummary,0,0,"R");
			$curY += 1.75 * $summaryIntervalY;
			$page = $pdf->PageBreak($page);
		}
		// Print Fund Summary
		if ($countFund > 1)
			$item = gettext("items");
		else
			$item = gettext("item");
		$sFundSummary = "$currentFundName Total - $countFund $item:   $" . number_format($currentFundAmount, 2, '.', ',');
		$pdf->SetXY(20,$curY);
		$pdf->SetFont("Times",'B',10);
		$pdf->Cell (176, $summaryIntervalY, $sFundSummary,0,0,"R");
		$curY += 2 * $summaryIntervalY;
		if ($detail_level != "summary")
			$pdf->line(40,$curY-2,195,$curY-2);
		$page = $pdf->PageBreak($page);
		
	
	} elseif ($sort == "family") {

		// **********************
		// Sort by Family  Report
		// **********************

		while ($aRow = mysql_fetch_array($rsReport)) {
			extract ($aRow);
			if (!$fun_ID){
				$fun_ID = -1;
				$fun_Name = "Undesignated";
			}
			if (!$fam_ID) {
				$fam_ID = -1;
				$fam_Name = "Unassigned";
				$fam_Address1 = "";
			}
			// First Family Heading
			if (!$currentFamilyID && $detail_level != "summary"){
				$sFamilyTitle = "$fam_Name - $fam_Address1";
				$pdf->SetFont("Times",'B',10);
				$pdf->WriteAt(20,$curY, $sFamilyTitle);
				$curY += 1.5 * $summaryIntervalY;
			}
			// Check for new Fund
			if (($currentFundID != $fun_ID || $currentFamilyID != $fam_ID) && $currentFundID && $detail_level != "summary") {
				// New Fund. Print Previous Fund Summary
				if ($countFund > 1)
					$item = gettext("items");
				else
					$item = gettext("item");
				$sFundSummary = "$currentFundName - $countFund $item:   $" . number_format($currentFundAmount, 2, '.', ','); 
				$curY +=2;
				$pdf->SetXY(20,$curY);
				$pdf->SetFont("Times",'I',10);
				$pdf->Cell (176, $summaryIntervalY, $sFundSummary,0,0,"R");
				$curY += 1.75 * $summaryIntervalY;
				$countFund = 0;
				$currentFundAmount = 0;
				$page = $pdf->PageBreak($page);
			}
			// Check for new Family
			if ($currentFamilyID != $fam_ID && $currentFamilyID) {
				// New Family.  Print Previous Family Summary
				if ($countFamily > 1)
					$item = gettext("items");
				else
					$item = gettext("item");
				$sFamilySummary = "$currentFamilyName - $currentFamilyAddress - $countFamily $item:   $" . number_format($currentFamilyAmount, 2, '.', ',');
				$pdf->SetXY(20,$curY);
				$pdf->SetFont("Times",'B',10);
				$pdf->Cell (176, $summaryIntervalY, $sFamilySummary,0,0,"R");
				$curY += 2 * $summaryIntervalY;
				if ($detail_level != "summary")
					$pdf->line(40,$curY-2,195,$curY-2);
				$page = $pdf->PageBreak($page);

				// New Family Title
				if  ($detail_level != "summary") {
					$sFamilyTitle = "$fam_Name - $fam_Address1";
					$pdf->SetFont("Times",'B',10);
					$pdf->WriteAt(20,$curY, $sFamilyTitle);
					$curY += 1.5 * $summaryIntervalY;
				}
				$countFamily = 0;
				$currentFamilyAmount = 0;
			}
			
			// Print Deposit Detail
			if ($detail_level == "detail"){
				// Format Data
				if ($plg_method == "CREDITCARD")
					$plg_method = "CREDIT";
				if ($plg_method == "BANKDRAFT")
					$plg_method = "DRAFT";
				if ($plg_method != "CHECK")
					$plg_CheckNo = $plg_method;
				if (strlen($plg_CheckNo) > 8)
					$plg_CheckNo = "...".substr($plg_CheckNo,-8,8);
				$sDeposit = "Dep #$plg_depID $dep_Date";
				if (strlen($sDeposit) > 22)
					$sDeposit = substr($sDeposit,0,21) . "...";
				if (strlen($plg_comment) > 29)
					$plg_comment = substr($plg_comment,0,28) . "...";
				$sFundName = $fun_Name;
				if (strlen($sFundName) > 31)
					$sFundName = substr($sFundName,0,30) . "...";
				
				// Print Data
				$pdf->SetFont('Times','', 10);
				$pdf->SetXY($pdf->leftX,$curY);
				$pdf->Cell (16, $summaryIntervalY, $plg_CheckNo,0,0,"R");
				$pdf->Cell (40, $summaryIntervalY, $sDeposit);
				$pdf->Cell (55, $summaryIntervalY, $sFundName);
				$pdf->Cell (40, $summaryIntervalY, $plg_comment);
				$pdf->SetFont('Courier','', 9);
				$pdf->Cell (25, $summaryIntervalY, $plg_amount,0,0,"R");
				$pdf->SetFont('Times','', 10);
				$curY += $summaryIntervalY;
				$page = $pdf->PageBreak($page);
			}
			// Update running totals
			$totalAmount += $plg_amount;
			$totalFund[$fun_Name] += $plg_amount;
			$countFund ++;
			$countFamily ++;
			$countReport ++;
			$currentFundAmount += $plg_amount;
			$currentFamilyAmount += $plg_amount;
			$currentReportAmount += $plg_amount;
			$currentFamilyID = $fam_ID;
			$currentFamilyName = $fam_Name;
			$currentFundID = $fun_ID;
			$currentFundName = $fun_Name;
			$currentFamilyAddress = $fam_Address1;
		}

		// Print Final Summary	
		// Print Fund Summary
		if ($detail_level != "summary") {
			if ($countFund > 1)
				$item = gettext("items");
			else
				$item = gettext("item");
			$sFundSummary = "$currentFundName - $countFund $item:   $" . number_format($currentFundAmount, 2, '.', ','); 
			$curY +=2;
			$pdf->SetXY(20,$curY);
			$pdf->SetFont("Times",'I',10);
			$pdf->Cell (176, $summaryIntervalY, $sFundSummary,0,0,"R");
			$curY += 1.75 * $summaryIntervalY;
			$page = $pdf->PageBreak($page);
		}
		// Print Family Summary
		if ($countFamily > 1)
			$item = gettext("items");
		else
			$item = gettext("item");
		$sFamilySummary = "$currentFamilyName - $currentFamilyAddress - $countFamily $item:   $" . number_format($currentFamilyAmount, 2, '.', ',');
		$pdf->SetXY(20,$curY);
		$pdf->SetFont("Times",'B',10);
		$pdf->Cell (176, $summaryIntervalY, $sFamilySummary,0,0,"R");
		$curY += 2 * $summaryIntervalY;
		if ($detail_level != "summary")
			$pdf->line(40,$curY-2,195,$curY-2);
		$page = $pdf->PageBreak($page);
	}
	
	// Print Report Summary
	if ($countReport > 1)
		$item = gettext("items");
	else
		$item = gettext("item");
	$sReportSummary = "Report Total ($countReport $item):   $" . number_format($currentReportAmount, 2, '.', ',');
	$pdf->SetXY(20,$curY);
	$pdf->SetFont("Times",'B',10);
	$pdf->Cell (176, $summaryIntervalY, $sReportSummary,0,0,"R");
	$pdf->line(40,$curY-2,195,$curY-2);
	$curY += 2.5 * $summaryIntervalY;
	$page = $pdf->PageBreak($page);
	
	// Print Fund Totals
	$pdf->SetFont('Times','B', 10);
	$pdf->SetXY ($curX, $curY);
	$pdf->WriteAt (20, $curY, 'Deposit totals by fund');
	$pdf->SetFont('Courier','', 10);
	$curY += 1.5 * $summaryIntervalY;
	ksort ($totalFund);
	reset ($totalFund);
	while ($FundTotal = current($totalFund)){
		if (strlen(key($totalFund) > 22))
			$sfun_Name = substr(key($totalFund),0,21) . "...";
		else
			$sfun_Name = key($totalFund);
		$pdf->SetXY(20,$curY);
		$pdf->Cell(45,$summaryIntervalY, $sfun_Name);
		$pdf->Cell (25, $summaryIntervalY, number_format($FundTotal, 2, '.', ','),0,0,"R");
		$curY += $summaryIntervalY;
		$page = $pdf->PageBreak($page);
		next($totalFund);
	}
	
	$pdf->FinishPage($page);
	$pdf->Output("DepositReport-" . date("Ymd-Gis") . ".pdf", true);

	
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
	header("Content-Disposition: attachment; filename='ChurchInfo" . date("Ymd-Gis") . ".csv");
	echo $buffer;
}
?>