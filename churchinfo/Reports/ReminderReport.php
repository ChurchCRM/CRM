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
require "../Include/ReportConfig.php";
require "../Include/ReportFunctions.php";

//Get the Fiscal Year ID out of the querystring
$iFYID = FilterInput($_GET["FYID"],'int');

// If CSVAdminOnly option is enabled and user is not admin, redirect to the menu.
if (!$_SESSION['bAdmin'] && $bCSVAdminOnly) {
	Redirect("Menu.php");
	exit;
}

// Load the FPDF library
LoadLib_FPDF();

class PDF_ReminderReport extends FPDF {

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
		$this->Write (8, $str);
	}

	function PrintRightJustifiedCell ($x, $y, $wid, $str) {
		$iLen = strlen ($str);
		$this->SetXY ($x, $y);
		$this->Cell ($wid, 4, $str, 1, 0, 'R');
	}

	// Constructor
	function PDF_ReminderReport() {
		global $paperFormat;
		parent::FPDF("P", "mm", $paperFormat);

		$this->_Font        = "Times";
		$this->SetMargins(0,0);
		$this->Open();
		$this->Set_Char_Size(10);
		$this->SetAutoPageBreak(false);
	}

	function WriteAt ($x, $y, $str) {
		$this->SetXY ($x, $y);
		$this->Write (8, $str);
	}

	function WriteAtCell ($x, $y, $wid, $str) {
		$this->SetXY ($x, $y);
		$this->Cell ($wid, 4, $str, 1);
	}

	function StartNewPage ($fam_ID, $fam_Name, $fam_Address1, $fam_Address2, $fam_City, $fam_State, $fam_Zip, $fam_Country, $iFYID) {
		$this->AddPage();
		
		$dateX = 180;
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

		$this->WriteAt ($leftX, $curY, $this->MakeSalutation ($fam_ID)); $curY += $incrementY;
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
		$blurb = "This letter shows our record of your pledge and payments for fiscal year " . (1995 + $iFYID) . "/" . (1995 + $iFYID + 1) . ".";
		$this->WriteAt ($leftX, $curY, $blurb);
		$curY += 2 * $incrementY;
		return ($curY);
	}

	function FinishPage ($curY) {
		$leftX = 20;
		$incrementY = 4;
		$curY += 2 * $incrementY;
		$this->WriteAt ($leftX, $curY, "Sincerely,");
		$curY += 4 * $incrementY;
		$this->WriteAt ($leftX, $curY, "<signed by>");
	}

	function MakeSalutation ($famID) {
		// Make it put the name if there is only one individual in the family
		// Make it put two first names and the last name when there are exactly two people in the family (e.g. "Nathaniel and Jeanette Brooks")
		// Make it put two whole names where there are exactly two people with different names (e.g. "Doug Philbrook and Karen Andrews")
		// When there are more than two people in the family I don't have any way to know which people are children, so I would have to just use the family name (e.g. "Grossman Family").
		$sSQL = "SELECT * FROM family_fam WHERE fam_ID=" . $famID;
		$rsFamInfo = RunQuery($sSQL);
		$aFam = mysql_fetch_array($rsFamInfo);
		extract ($aFam);

		$sSQL = "SELECT * FROM person_per WHERE per_fam_ID=" . $famID;
		$rsMembers = RunQuery($sSQL);
		$numMembers = mysql_num_rows ($rsMembers);

		if ($numMembers == 1) {
			$aMember = mysql_fetch_array($rsMembers);
			extract ($aMember);
			return ($per_FirstName . " " . $per_LastName);
		} else if ($numMembers == 2) {
			$firstMember = mysql_fetch_array($rsMembers);
			extract ($firstMember);
			$firstFirstName = $per_FirstName;
			$firstLastName = $per_LastName;
			$secondMember = mysql_fetch_array($rsMembers);
			extract ($secondMember);
			$secondFirstName = $per_FirstName;
			$secondLastName = $per_LastName;
			if ($firstLastName == $secondLastName) {
				return ($firstFirstName . " & " . $secondFirstName . " " . $firstLastName);
			} else {
				return ($firstFirstName . " " . $firstLastName . " & " . $secondFirstName . " " . $secondLastName);
			}
		} else {
			return ($fam_Name . " Family");
		}
	}
}

// Instantiate the directory class and build the report.
$pdf = new PDF_ReminderReport();

// Get all the families
$sSQL = "SELECT * FROM family_fam WHERE 1";
$rsFamilies = RunQuery($sSQL);

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
		$pdf->WriteAt ($summaryDateX, $curY, 'We have not received your pledge.');
		$curY += 2 * $summaryIntervalY;
	} else {

		$summaryDateX = 20;
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
			$pdf->SetFont('Times','', 10);

			$pdf->WriteAtCell ($summaryDateX, $curY, $summaryDateWid, $plg_date);
			$pdf->WriteAtCell ($summaryFundX, $curY, $summaryFundWid, $fundName);

			$pdf->SetFont('Courier','', 8);

			$pdf->PrintRightJustifiedCell ($summaryAmountX, $curY, $summaryAmountWid, $plg_amount);

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
		$pdf->WriteAt ($summaryDateX, $curY, 'We have not received any payments.');
		$curY += 2 * $summaryIntervalY;
	} else {
		$summaryDateX = 20;
		$summaryCheckNoX = 40;
		$summaryMethodX = 60;
		$summaryFundX = 85;
		$summaryMemoX = 110;
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
			$pdf->SetFont('Times','', 10);

			$pdf->WriteAtCell ($summaryDateX, $curY, $summaryDateWid, $plg_date);
			$pdf->PrintRightJustifiedCell ($summaryCheckNoX, $curY, $summaryCheckNoWid, $plg_CheckNo);
			$pdf->WriteAtCell ($summaryMethodX, $curY, $summaryMethodWid, $plg_method);
			$pdf->WriteAtCell ($summaryFundX, $curY, $summaryFundWid, $fundName);
			$pdf->WriteAtCell ($summaryMemoX, $curY, $summaryMemoWid, $plg_comment);

			$pdf->SetFont('Courier','', 8);

			$pdf->PrintRightJustifiedCell ($summaryAmountX, $curY, $summaryAmountWid, $plg_amount);

			$totalAmount += $plg_amount;
			$cnt += 1;

			$curY += $summaryIntervalY;
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

	$totalDue = $totalAmountPledges - $totalAmountPayments;
	if ($totalDue > 0) {
		$curY += $summaryIntervalY;
		$dueString = sprintf ("Remaining pledge due: %.2f", ($totalAmountPledges - $totalAmountPayments));
		$pdf->WriteAt ($summaryDateX, $curY, $dueString);
		$curY += $summaryIntervalY;
	}

	$pdf->FinishPage ($curY);
}

if ($iPDFOutputType == 1) {
	$pdf->Output("ReminderReport" . date("Ymd") . ".pdf", true);
} else {
	$pdf->Output();
}	
?>
