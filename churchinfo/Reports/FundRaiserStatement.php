<?php
/*******************************************************************************
*
*  filename    : Reports/FundRaiserStatement.php
*  last change : 2009-04-17
*  description : Creates a PDF with one or more fund raiser statements
*  copyright   : Copyright 2009 Michael Wilt
*
*  ChurchInfo is free software; you can redistribute it and/or modify
*  it under the terms of the GNU General Public License as published by
*  the Free Software Foundation; either version 2 of the License, or
*  (at your option) any later version.
*
******************************************************************************/

require "../Include/Config.php";
require "../Include/Functions.php";
require "../Include/ReportFunctions.php";
require "../Include/ReportConfig.php";

$iPaddleNumID = FilterInput($_GET["PaddleNumID"],'int');
$iFundRaiserID = $_SESSION['iCurrentFundraiser'];

//Get the paddlenum records for this fundraiser
if ($iPaddleNumID > 0) {
	$selectOneCrit = " AND pn_ID=" . $iPaddleNumID . " ";
} else {
	$selectOneCrit = 0;
}

$sSQL = "SELECT pn_ID, pn_fr_ID, pn_Num, pn_per_ID,
                a.per_FirstName as buyerFirstName, a.per_LastName as buyerLastName,
				b.fam_ID, b.fam_Name, b.fam_Address1, b.fam_Address2, b.fam_City, b.fam_State, b.fam_Zip, b.fam_Country                
         FROM PaddleNum_pn
         LEFT JOIN person_per a ON pn_per_ID=a.per_ID
         LEFT JOIN family_fam b ON fam_ID = a.per_fam_ID 
         WHERE pn_FR_ID = '" . $iFundRaiserID . $selectOneCrit . "' ORDER BY pn_Num"; 
$rsPaddleNums = RunQuery($sSQL);

class PDF_FundRaiserStatement extends ChurchInfoReport {

	// Constructor
	function PDF_FundRaiserStatement() {
		parent::FPDF("P", "mm", $this->paperFormat);
		$this->SetFont("Times",'',10);
		$this->SetMargins(20,20);
		$this->Open();
		$this->SetAutoPageBreak(false);
	}

	function StartNewPage ($fam_ID, $fam_Name, $fam_Address1, $fam_Address2, $fam_City, $fam_State, $fam_Zip, $fam_Country, $iYear) {
		global $letterhead;
		$curY = $this->StartLetterPage ($fam_ID, $fam_Name, $fam_Address1, $fam_Address2, $fam_City, $fam_State, $fam_Zip, $fam_Country, $iYear, $letterhead);
		return ($curY);
	}

	function FinishPage ($curY,$fam_ID,$fam_Name, $fam_Address1, $fam_Address2, $fam_City, $fam_State, $fam_Zip, $fam_Country) {
	}
}

// Instantiate the directory class and build the report.
$pdf = new PDF_FundRaiserStatement();

// Read in report settings from database
$rsConfig = mysql_query("SELECT cfg_name, IFNULL(cfg_value, cfg_default) AS value FROM config_cfg WHERE cfg_section='ChurchInfoReport'");
   if ($rsConfig) {
	while (list($cfg_name, $cfg_value) = mysql_fetch_row($rsConfig)) {
		$pdf->$cfg_name = $cfg_value;
	}
   }

// Loop through result array
while ($row = mysql_fetch_array($rsPaddleNums)) {
	extract ($row);
	
	// Start page for this paddle number
	$curY = $pdf->StartNewPage ($fam_ID, $fam_Name, $fam_Address1, $fam_Address2, $fam_City, $fam_State, $fam_Zip, $fam_Country, $iYear);

	$this->WriteAt ($this->leftX, $curY, gettext ("Donated Items:"));
	$curY += 2 * $this->incrementY;
	
	$summaryDateX = $pdf->leftX;
	$summaryCheckNoX = 40;
	$summaryMethodX = 60;
	$summaryFundX = 85;
	$summaryMemoX = 110;
	$summaryAmountX = 160;
	$summaryIntervalY = 4;
	$curY += 2 * $summaryIntervalY;
	$pdf->SetFont('Times','B', 10);
	
	// Get donated items and make the table
	$sSQL = "SELECT di_item, di_title, di_buyer_id, di_sellprice,
	                a.per_FirstName as buyerFirstName,
	                a.per_LastName as buyerLastName
	                FROM DonatedItem_di LEFT JOIN person_per a on a.per_ID = di.di_buyer_id 
	                WHERE di_donor_id = " . $pn_per_ID;
	$rsDonatedItems = RunQuery($sSQL);
	
	$pdf->SetXY($summaryDateX,$curY);
	$pdf->Cell (20, $summaryIntervalY, 'Item');
	$pdf->Cell (20, $summaryIntervalY, 'Name');
	$pdf->Cell (25, $summaryIntervalY, 'Buyer');
	$pdf->Cell (25, $summaryIntervalY, 'Amount',0,1,"R");
	while ($itemRow = mysql_fetch_array($rsDonatedItems)) {
		extract ($itemRow);

		$pdf->SetFont('Times','', 10);
		$pdf->Cell (20, $summaryIntervalY, $di_item);
		$pdf->Cell (20, $summaryIntervalY, $di_title);
		$pdf->Cell (25, $summaryIntervalY, $buyerFirstName . " " . $buyerLastName);
		$pdf->Cell (25, $summaryIntervalY, $di_sellprice,0,1,"R");
		$curY = $pdf->GetY();
	}
	
	// Get purchased items and make the table
	$this->WriteAt ($this->leftX, $curY, gettext ("Purchased Items:"));
	$curY += 2 * $this->incrementY;
	
	$totalAmount = 0.0;

	// Get individual auction items first
	$sSQL = "SELECT di_item, di_title, di_donor_id, di_sellprice,
	                a.per_FirstName as donorFirstName,
	                a.per_LastName as donorLastName
	                FROM DonatedItem_di LEFT JOIN person_per a on a.per_ID = di.di_donor_id
	                WHERE di_buyer_id = " . $pn_per_ID;
	
	$rsPurchasedItems = RunQuery($sSQL);

	$pdf->SetXY($summaryDateX,$curY);
	$pdf->Cell (20, $summaryIntervalY, 'Item');
	$pdf->Cell (20, $summaryIntervalY, 'Qty');
	$pdf->Cell (20, $summaryIntervalY, 'Name');
	$pdf->Cell (25, $summaryIntervalY, 'Donor');
	$pdf->Cell (25, $summaryIntervalY, 'Amount',0,1,"R");

	while ($itemRow = mysql_fetch_array($rsPurchasedItems)) {
		extract ($itemRow);

		$pdf->SetFont('Times','', 10);
		$pdf->Cell (20, $summaryIntervalY, $di_item);
		$pdf->Cell (20, $summaryIntervalY, "1"); // quantity 1 for all individual items
		$pdf->Cell (20, $summaryIntervalY, $di_title);
		$pdf->Cell (25, $summaryIntervalY, ($donorFirstName . " " . $donorLastName));
		$pdf->Cell (25, $summaryIntervalY, $di_sellprice,0,1,"R");
		$totalAmount += $di_sellprice;
		$curY = $pdf->GetY();
	}

	// Get multibuy items for this buyer
	$sqlMultiBuy = "SELECT mb_count, mb_item_ID, 
	                a.per_FirstName as donorFirstName,
	                a.per_LastName as donorLastName
					b.di_item, b.di_title, b.di_donor_id, b.di_sellprice
					FROM Multibuy_mb
					LEFT JOIN donateditem_di b ON mb_item_ID=b.di_ID
					LEFT JOIN person_per a ON b.di_donor_id=a.per_ID 
					WHERE mb_per_ID=" . $pn_per_ID;
	$rsMultiBuy = RunQuery($sqlNumBought);
	while ($mbRow = mysql_fetch_array($rsMultiBuy) {
		extract ($mbRow);

		$pdf->SetFont('Times','', 10);
		$pdf->Cell (20, $summaryIntervalY, $di_item);
		$pdf->Cell (20, $summaryIntervalY, $mb_Count);
		$pdf->Cell (20, $summaryIntervalY, $di_title);
		$pdf->Cell (25, $summaryIntervalY, ($donorFirstName . " " . $donorLastName));
		$pdf->Cell (25, $summaryIntervalY, $mb_count * $di_sellprice,0,1,"R");
		$totalAmount += $mb_count * $di_sellprice;
		$curY = $pdf->GetY();
	}
	
	// Report total purchased items
	$this->WriteAt ($this->leftX, $curY, (gettext ("Total of all purchases: ") . $totalAmount));
	$curY += 2 * $this->incrementY;
	
	// Make the tear-off record for the bottom of the page
	
	$pdf->FinishPage ($curY,$prev_fam_ID,$prev_fam_Name, $prev_fam_Address1, $prev_fam_Address2, $prev_fam_City, $prev_fam_State, $prev_fam_Zip, $prev_fam_Country);
}

$pdf->Output("FundRaiserStatement" . date("Ymd") . ".pdf", true);
?>
