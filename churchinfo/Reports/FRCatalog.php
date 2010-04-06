<?php
/*******************************************************************************
*
*  filename    : Reports/ConfirmReport.php
*  last change : 2003-08-30
*  description : Creates a PDF with all the confirmation letters asking member
*                families to verify the information in the database.
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

$iCurrentFundraiser = $_GET["CurrentFundraiser"];

$wid_ID = 12;
$wid_Name = 25;
$wid_MinBid = 15;
$wid_EstValue = 15;
$wid_Description = 100;

$topMargin = 25;
$leftMargin = 20;

$leftID = $leftMargin;
$leftName = $leftID+$wid_ID;
$leftMinBid = $leftName + $wid_Name;
$leftEstValue = $leftMinBid + $wid_MinBid;
$leftDescription = $leftEstValue + $wid_EstValue;

$curY = $topMargin;
$lineIncr = 5;

class PDF_FRCatalogReport extends ChurchInfoReport {
	// Constructor
	function PDF_FRCatalogReport() {
		parent::FPDF("P", "mm", $this->paperFormat);
		$this->leftX = 10;
		$this->SetFont("Times",'',10);
		$this->SetMargins(10,20);
		$this->Open();
		$this->AddPage ();
		$this->SetAutoPageBreak(true, 25);
	}
	
	function AddPage () {
		global $wid_ID,$wid_Name,$wid_MinBid,$wid_EstValue,$wid_Description;
		global $topMargin,$leftMargin,$leftID,$leftName,$leftMinBid,$leftEstValue,$leftDescription;
		global $curY, $lineIncr;
		global $fr_title, $fr_description;
		
		parent::AddPage();
    	$curY = $topMargin;
		
    	$this->SetFont("Times",'B',16);
    	$this->WriteAt ($leftMargin, $curY, $fr_title);
		$curY += 8;
		$this->WriteAt ($leftMargin, $curY, $fr_description);
		$curY += 8;
    	
    	$this->SetFont("Times",'B',10);
		$this->WriteAtCell ($leftID, $curY, $wid_ID, gettext ("Item"),0,"L");
		$this->WriteAtCell ($leftName, $curY, $wid_Name, gettext ("Name"),0,"L");
		$this->WriteAtCell ($leftMinBid, $curY, $wid_MinBid, gettext ("Min bid"),0,"L");
		$this->WriteAtCell ($leftEstValue, $curY, $wid_EstValue, gettext ("Est value"),0,"L");
		$this->WriteAtCell ($leftDescription, $curY, $wid_Description, gettext ("Description"),0,"L");
		$curY += $lineIncr;
		$this->SetY ($curY);
    	$this->SetFont("Times",'',10);
	}
}


// Get the information about this fundraiser
$sSQL = "SELECT * FROM fundraiser_fr WHERE fr_ID=".$iCurrentFundraiser;
$rsFR = RunQuery($sSQL);
$thisFR = mysql_fetch_array($rsFR);
extract ($thisFR);

// Get all the donated items
$sSQL = "SELECT * FROM donateditem_di WHERE di_FR_ID=".$iCurrentFundraiser." ORDER BY di_item";
$rsItems = RunQuery($sSQL);

$pdf = new PDF_FRCatalogReport();
$pdf->SetTitle ($fr_title);

// Read in report settings from database
$rsConfig = mysql_query("SELECT cfg_name, IFNULL(cfg_value, cfg_default) AS value FROM config_cfg WHERE cfg_section='ChurchInfoReport'");
if ($rsConfig) {
	while (list($cfg_name, $cfg_value) = mysql_fetch_row($rsConfig)) {
		$pdf->$cfg_name = $cfg_value;
	}
}

// Loop through items
while ($oneItem = mysql_fetch_array($rsItems)) {
	extract ($oneItem);

	$pdf->SetFont("Times",'',10);
	$pdf->SetXY ($leftID, $curY);
	$pdf->MultiCell ($wid_ID, $lineIncr, $di_item,0,"L");
	$maxY = $pdf->GetY();
	$maxPage = $pdf->PageNo ();
	$pdf->SetXY ($leftName, $curY);
	$pdf->MultiCell ($wid_Name, $lineIncr, $di_title,0,"L");
	if ($pdf->PageNo() > $maxPage)
		$maxY = $pdf->GetY();
	else
		$maxY = max($maxY, $pdf->GetY());
	$pdf->SetXY ($leftMinBid, $curY);
	$pdf->MultiCell ($wid_MinBid, $lineIncr, $di_minimum,0,"L");
	if ($pdf->PageNo() > $maxPage)
		$maxY = $pdf->GetY();
	else
		$maxY = max($maxY, $pdf->GetY());
	$pdf->SetXY ($leftEstValue, $curY);
	$pdf->MultiCell ($wid_EstValue, $lineIncr, $di_estprice,0,"L");
	if ($pdf->PageNo() > $maxPage)
		$maxY = $pdf->GetY();
	else
		$maxY = max($maxY, $pdf->GetY());
	$pdf->SetXY ($leftDescription, $curY);
	$pdf->MultiCell ($wid_Description, $lineIncr, $di_description,0,"L");
	if ($pdf->PageNo() > $maxPage)
		$maxY = $pdf->GetY();
	else
		$maxY = max($maxY, $pdf->GetY());
		
	$curY = $maxY+$lineIncr;
}

header('Pragma: public');  // Needed for IE when using a shared SSL certificate
if ($iPDFOutputType == 1)
	$pdf->Output("FRCatalog" . date("Ymd") . ".pdf", "D");
else
	$pdf->Output();	
?>
