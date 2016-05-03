<?php
/*******************************************************************************
*
*  filename    : Reports/ConfirmReport.php
*  last change : 2003-08-30
*  description : Creates a PDF with all the confirmation letters asking member
*                families to verify the information in the database.
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

$iCurrentFundraiser = $_GET["CurrentFundraiser"];

$curY = 0;

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
	
	function AddPage ($orientation='', $format='') {
		global $fr_title, $fr_description, $curY;

		parent::AddPage($orientation, $format);
		
    	$this->SetFont("Times",'B',16);
    	$this->Write (8, $fr_title."\n");
		$curY += 8;
		$this->Write (8, $fr_description."\n\n");
		$curY += 8;
    	$this->SetFont("Times",'',12);
	}
}


// Get the information about this fundraiser
$sSQL = "SELECT * FROM fundraiser_fr WHERE fr_ID=".$iCurrentFundraiser;
$rsFR = RunQuery($sSQL);
$thisFR = mysql_fetch_array($rsFR);
extract ($thisFR);

// Get all the donated items
$sSQL = "SELECT * FROM donateditem_di LEFT JOIN person_per on per_ID=di_donor_ID WHERE di_FR_ID=".$iCurrentFundraiser.
" ORDER BY substr(di_item,1,1),cast(substr(di_item,2) as unsigned integer),substr(di_item,4)";
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
$idFirstChar = '';

while ($oneItem = mysql_fetch_array($rsItems)) {
	extract ($oneItem);

	$newIdFirstChar = substr($di_item,0,1);
	$maxYNewPage = 220;
	if ($di_picture != "")
	    $maxYNewPage = 120;
	if ($pdf->GetY() > $maxYNewPage || ($idFirstChar <> '' && $idFirstChar <> $newIdFirstChar))
	  $pdf->AddPage ();
	$idFirstChar = $newIdFirstChar;

	$pdf->SetFont("Times",'B',12);
	$pdf->Write (6, $di_item.": ");
	$pdf->Write (6, stripslashes($di_title)."\n");
	
    if ($di_picture != "") {
        $s = getimagesize($di_picture);
        $h = (100.0 / $s[0]) * $s[1];
        $pdf->Image($di_picture, $pdf->GetX(), $pdf->GetY(), 100.0, $h);
        $pdf->SetY ($pdf->GetY() + $h);
    }
	
	$pdf->SetFont("Times",'',12);
	$pdf->Write (6, stripslashes($di_description)."\n");
	if ($di_minimum > 0)
		$pdf->Write (6, gettext ("Minimum bid ")."\$".$di_minimum.".  ");
	if ($di_estprice > 0)
		$pdf->Write (6, gettext ("Estimated value ")."\$".$di_estprice.".  ");
	if ($per_LastName!="")
		$pdf->Write (6, gettext ("Donated by ") . $per_FirstName . " " .$per_LastName.".\n");
	$pdf->Write (6, "\n");
}

header('Pragma: public');  // Needed for IE when using a shared SSL certificate
if ($iPDFOutputType == 1)
	$pdf->Output("FRCatalog" . date("Ymd") . ".pdf", "D");
else
	$pdf->Output();	
?>
