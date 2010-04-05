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

class PDF_FRCatalogReport extends ChurchInfoReport {

	// Constructor
	function PDF_FRCatalogReport() {
		parent::FPDF("P", "mm", $this->paperFormat);
		$this->leftX = 10;
		$this->SetFont("Times",'',10);
		$this->SetMargins(10,20);
		$this->Open();
		$this->SetAutoPageBreak(true);
	}
}

$pdf = new PDF_FRCatalogReport();

// Read in report settings from database
$rsConfig = mysql_query("SELECT cfg_name, IFNULL(cfg_value, cfg_default) AS value FROM config_cfg WHERE cfg_section='ChurchInfoReport'");
if ($rsConfig) {
	while (list($cfg_name, $cfg_value) = mysql_fetch_row($rsConfig)) {
		$pdf->$cfg_name = $cfg_value;
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
$leftDescription = $leftExtValue + $wid_EstValue;

$curY = $topMargin;
$lineIncr = 5;

$pdf->WriteAt ($leftMargin, $curY, $fr_title);
$curY += $lineIncr;
$pdf->WriteAt ($leftMargin, $topMargin, $fr_description);
$curY += $lineIncr;

$pdf->SetFont("Times",'B',10);
$pdf->WriteAtCell ($leftID, $curY, $wid_ID, gettext ("Item"));
$pdf->WriteAtCell ($leftName, $curY, $wid_ID, gettext ("Name"));
$pdf->WriteAtCell ($leftMinBid, $curY, $wid_ID, gettext ("Min bid"));
$pdf->WriteAtCell ($leftEstValue, $curY, $wid_ID, gettext ("Est value"));
$pdf->WriteAtCell ($leftDescription, $curY, $wid_ID, gettext ("Description"));
$curY += $lineIncr;

// Loop through items
while ($oneItem = mysql_fetch_array($rsItems)) {
	extract ($oneItem);

	$pdf->SetFont("Times",'',10);
	$pdf->WriteAtCell ($leftID, $curY, $wid_ID, $di_item);
	$pdf->WriteAtCell ($leftName, $curY, $wid_ID, $di_title);
	$pdf->WriteAtCell ($leftMinBid, $curY, $wid_ID, $di_minimum);
	$pdf->WriteAtCell ($leftEstValue, $curY, $wid_ID, $di_estprice);
	$pdf->WriteAtCell ($leftDescription, $curY, $wid_ID, $di_description);
	$curY += $lineIncr;

    if ($curY > 183)	// This insures the trailer information fits continuously on the page (3 inches of "footer"
    {
    }
}

header('Pragma: public');  // Needed for IE when using a shared SSL certificate
if ($iPDFOutputType == 1)
	$pdf->Output("FRCatalog" . date("Ymd") . ".pdf", "D");
else
	$pdf->Output();	
?>
