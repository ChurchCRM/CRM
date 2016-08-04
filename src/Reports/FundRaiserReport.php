<?php
/*******************************************************************************
*
*  filename    : Reports/FundRaiserReport.php
*  last change : 2016-03-15
*  description : Creates a PDF report about the auction
*  copyright   : Copyright 2016 Michael Wilt
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

$iFundRaiserID = $_SESSION['iCurrentFundraiser'];

//Get the paddlenum records for this fundraiser
if ($iPaddleNumID > 0) {
	$selectOneCrit = " AND pn_ID=" . $iPaddleNumID . " ";
} else {
	$selectOneCrit = "";
}

$sSQL = "SELECT pn_ID, pn_fr_ID, pn_Num, pn_per_ID,
                a.per_FirstName as paddleFirstName, a.per_LastName as paddleLastName, a.per_Email as paddleEmail,
				b.fam_ID, b.fam_Name, b.fam_Address1, b.fam_Address2, b.fam_City, b.fam_State, b.fam_Zip, b.fam_Country                
         FROM paddlenum_pn
         LEFT JOIN person_per a ON pn_per_ID=a.per_ID
         LEFT JOIN family_fam b ON fam_ID = a.per_fam_ID 
         WHERE pn_FR_ID =" . $iFundRaiserID . $selectOneCrit . " ORDER BY pn_Num"; 
$rsPaddleNums = RunQuery($sSQL);

class PDF_FundRaiserReport extends ChurchInfoReport {

	// Constructor
	function PDF_FundRaiserStatement() {
		parent::FPDF("P", "mm", $this->paperFormat);
		$this->SetFont("Times",'',10);
		$this->SetMargins(20,20);
		$this->Open();
		$this->SetAutoPageBreak(false);
	}

	function CellWithWrap ($curY, $curNewY, $ItemWid, $tableCellY, $txt, $bdr, $aligncode) {
		$curPage = $this->PageNo();
		$leftX = $this->GetX ();
		$this->SetXY ($leftX, $curY);
		$this->MultiCell ($ItemWid, $tableCellY, $txt, $bdr, $aligncode);
		$newY = $this->GetY ();
		$newPage = $this->PageNo ();
		$this->SetXY ($leftX+$ItemWid, $curY);
		if ($newPage > $curPage)
			return $newY;
		else
			return (max ($newY, $curNewY));
	}
}

// Read in report settings from database
$rsConfig = mysql_query("SELECT cfg_name, IFNULL(cfg_value, cfg_default) AS value FROM config_cfg WHERE cfg_section='ChurchInfoReport'");
if ($rsConfig) {
	while (list($cfg_name, $cfg_value) = mysql_fetch_row($rsConfig)) {
		$pdf->$cfg_name = $cfg_value;
	}
}

$totalAuctionItems = 0.0;
$totalSellToAll = array();

// Loop through result array
while ($row = mysql_fetch_array($rsPaddleNums)) {
	extract ($row);

	// Get individual auction items first
	$sSQL = "SELECT di_item, di_title, di_donor_id, di_sellprice,
	                a.per_FirstName as donorFirstName,
	                a.per_LastName as donorLastName,
	                a.per_Email as donorEmail,
	                b.fam_homePhone as donorPhone
	                FROM donateditem_di LEFT JOIN person_per a on a.per_ID = di_donor_id
	                                    LEFT JOIN family_fam b on a.per_fam_id=b.fam_id
	                WHERE di_FR_ID = ".$iFundRaiserID." AND di_buyer_id = " . $pn_per_ID;
	$rsPurchasedItems = RunQuery($sSQL);
	
	while ($itemRow = mysql_fetch_array($rsPurchasedItems)) {
		extract ($itemRow);
		$totalAuctionItems += $di_sellprice;
	}
	
	// Get multibuy items for this buyer
	$sqlMultiBuy = "SELECT mb_count, mb_item_ID, 
	                a.per_FirstName as donorFirstName,
	                a.per_LastName as donorLastName,
	                a.per_Email as donorEmail,
	                c.fam_HomePhone as donorPhone,
					b.di_item, b.di_title, b.di_donor_id, b.di_sellprice
					FROM multibuy_mb
					LEFT JOIN donateditem_di b ON mb_item_ID=b.di_ID
					LEFT JOIN person_per a ON b.di_donor_id=a.per_ID 
					LEFT JOIN family_fam c ON a.per_fam_id = c.fam_ID
					WHERE b.di_FR_ID=".$iFundRaiserID." AND mb_per_ID=" . $pn_per_ID;
	$rsMultiBuy = RunQuery($sqlMultiBuy);
	while ($mbRow = mysql_fetch_array($rsMultiBuy)) {
		extract ($mbRow);
		$totalSellToAll[$di_item] += $mb_count * $di_sellprice;
	}
}
// Instantiate the directory class and build the report.
$pdf = new PDF_FundRaiserReport();

$pdf->Text (10, 10, "Fund Raiser: $iFundRaiserID");
$pdf->Text (10, 20, "Total item sales: $totalAuctionItems");
$y = 30;
foreach ($totalSellToAll as $name => $value) {
	$pdf->Text (10, $y, "$name: $value");	
}

header('Pragma: public');  // Needed for IE when using a shared SSL certificate
$pdf->Output("FundRaiserStatement" . date("Ymd") . ".pdf", "D");
?>
