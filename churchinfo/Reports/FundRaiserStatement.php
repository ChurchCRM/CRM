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
	$selectOneCrit = "";
}

$sSQL = "SELECT pn_ID, pn_fr_ID, pn_Num, pn_per_ID,
                a.per_FirstName as paddleFirstName, a.per_LastName as paddleLastName,
				b.fam_ID, b.fam_Name, b.fam_Address1, b.fam_Address2, b.fam_City, b.fam_State, b.fam_Zip, b.fam_Country                
         FROM paddlenum_pn
         LEFT JOIN person_per a ON pn_per_ID=a.per_ID
         LEFT JOIN family_fam b ON fam_ID = a.per_fam_ID 
         WHERE pn_FR_ID =" . $iFundRaiserID . $selectOneCrit . " ORDER BY pn_Num"; 
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

	$pdf->WriteAt ($pdf->leftX, $curY, gettext ("Donated Items:"));
	$curY += 2 * $pdf->incrementY;

	$ItemWid = 20;
	$QtyWid = 20;
	$TitleWid = 60;
	$DonorWid = 40;
	$PriceWid = 25;
	$tableCellY = 4;
	
	// Get donated items and make the table
	$sSQL = "SELECT di_item, di_title, di_buyer_id, di_sellprice,
	                a.per_FirstName as buyerFirstName,
	                a.per_LastName as buyerLastName
	                FROM donateditem_di LEFT JOIN person_per a on a.per_ID = di_buyer_id 
	                WHERE di_donor_id = " . $pn_per_ID;
	$rsDonatedItems = RunQuery($sSQL);
	
	$pdf->SetXY($pdf->leftX,$curY);
	$pdf->SetFont('Times','B', 10);
	
	$pdf->Cell ($ItemWid, $tableCellY, 'Item');
	$pdf->Cell ($TitleWid, $tableCellY, 'Name');
	$pdf->Cell ($DonorWid, $tableCellY, 'Buyer');
	$pdf->Cell ($PriceWid, $tableCellY, 'Amount',0,1,"R");
	$curY = $pdf->GetY();
	$pdf->SetFont('Times','', 10);
	
	while ($itemRow = mysql_fetch_array($rsDonatedItems)) {
		extract ($itemRow);
		$pdf->Cell ($ItemWid, $tableCellY, $di_item);
		$pdf->Cell ($TitleWid, $tableCellY, $di_title);
		$pdf->Cell ($DonorWid, $tableCellY, $buyerFirstName . " " . $buyerLastName);
		$pdf->Cell ($PriceWid, $tableCellY, $di_sellprice,0,1,"R");
		$curY = $pdf->GetY();	
	}
	
	// Get purchased items and make the table
	$curY += 2 * $tableCellY;
	$pdf->SetFont('Times','', 10);
	$pdf->WriteAt ($pdf->leftX, $curY, gettext ("Purchased Items:"));
	$curY += 2 * $pdf->incrementY;
	
	$totalAmount = 0.0;

	// Get individual auction items first
	$sSQL = "SELECT di_item, di_title, di_donor_id, di_sellprice,
	                a.per_FirstName as donorFirstName,
	                a.per_LastName as donorLastName
	                FROM donateditem_di LEFT JOIN person_per a on a.per_ID = di_donor_id
	                WHERE di_buyer_id = " . $pn_per_ID;
	$rsPurchasedItems = RunQuery($sSQL);

	$pdf->SetXY($pdf->leftX,$curY);
	$pdf->SetFont('Times','B', 10);
	$pdf->Cell ($ItemWid, $tableCellY, 'Item');
	$pdf->Cell ($QtyWid, $tableCellY, 'Qty');
	$pdf->Cell ($TitleWid, $tableCellY, 'Name');
	$pdf->Cell ($DonorWid, $tableCellY, 'Donor');
	$pdf->Cell ($PriceWid, $tableCellY, 'Amount',0,1,"R");
	$pdf->SetFont('Times','', 10);
	$curY += $pdf->incrementY;
	
	while ($itemRow = mysql_fetch_array($rsPurchasedItems)) {
		extract ($itemRow);
		$pdf->Cell ($ItemWid, $tableCellY, $di_item);
		$pdf->Cell ($QtyWid, $tableCellY, "1"); // quantity 1 for all individual items
		$pdf->Cell ($TitleWid, $tableCellY, $di_title);
		$pdf->Cell ($DonorWid, $tableCellY, ($donorFirstName . " " . $donorLastName));
		$pdf->Cell ($PriceWid, $tableCellY, "$".$di_sellprice,0,1,"R");
		$curY = $pdf->GetY();
		$totalAmount += $di_sellprice;
	}

	// Get multibuy items for this buyer
	$sqlMultiBuy = "SELECT mb_count, mb_item_ID, 
	                a.per_FirstName as donorFirstName,
	                a.per_LastName as donorLastName,
					b.di_item, b.di_title, b.di_donor_id, b.di_sellprice
					FROM multibuy_mb
					LEFT JOIN donateditem_di b ON mb_item_ID=b.di_ID
					LEFT JOIN person_per a ON b.di_donor_id=a.per_ID 
					WHERE mb_per_ID=" . $pn_per_ID;
	$rsMultiBuy = RunQuery($sqlMultiBuy);
	while ($mbRow = mysql_fetch_array($rsMultiBuy)) {
		extract ($mbRow);
		$pdf->Cell ($ItemWid, $tableCellY, $di_item);
		$pdf->Cell ($QtyWid, $tableCellY, $mb_count);
		$pdf->Cell ($TitleWid, $tableCellY, $di_title);
		$pdf->Cell ($DonorWid, $tableCellY, ($donorFirstName . " " . $donorLastName));
		$pdf->Cell ($PriceWid, $tableCellY, ("$". ($mb_count * $di_sellprice)),0,1,"R");
		$curY = $pdf->GetY();
		$totalAmount += $mb_count * $di_sellprice;
	}
	
	// Report total purchased items
	$pdf->WriteAt ($pdf->leftX, $curY, (gettext ("Total of all purchases: $") . $totalAmount));
	$curY += 2 * $pdf->incrementY;
	
	// Make the tear-off record for the bottom of the page
	$curY = 240;
	$pdf->WriteAt ($pdf->leftX, $curY, gettext ("-----------------------------------------------------------------------------------------------------------------------------------------------"));
	$curY += 2 * $pdf->incrementY;
	$pdf->WriteAt ($pdf->leftX, $curY, (gettext ("Buyer # ") . $pn_Num . " : " . $paddleFirstName . " " . $paddleLastName . " : " . gettext ("Total purchases: $") . $totalAmount . " : " . gettext ("Amount paid: ________________")));
	$curY += 2 * $pdf->incrementY;
	$pdf->WriteAt ($pdf->leftX, $curY, gettext ("Paid by (  ) Cash    (  ) Check    (  ) Credit card __ __ __ __    __ __ __ __    __ __ __ __    __ __ __ __  Exp __ / __"));
	$curY += 2 * $pdf->incrementY;
	$pdf->WriteAt ($pdf->leftX, $curY, gettext ("                                        Signature ________________________________________________________________"));
	
	$pdf->FinishPage ($curY,$prev_fam_ID,$prev_fam_Name, $prev_fam_Address1, $prev_fam_Address2, $prev_fam_City, $prev_fam_State, $prev_fam_Zip, $prev_fam_Country);
}

$pdf->Output("FundRaiserStatement" . date("Ymd") . ".pdf", true);
?>
