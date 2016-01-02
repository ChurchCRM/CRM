<?php
/*******************************************************************************
*
*  filename    : Reports/FRCertificates.php
*  last change : 2003-08-30
*  description : Creates a PDF with a silent auction bid sheet for every item.
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

class PDF_CertificatesReport extends ChurchInfoReport {
	// Constructor
	function PDF_CertificatesReport() {
		parent::FPDF("P", "mm", $this->paperFormat);
		$this->leftX = 10;
		$this->SetFont("Times",'',10);
		$this->SetMargins(15,25);
		$this->Open();
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
    	$this->SetFont("Times",'B',36);
		$this->Write (8, gettext ("Certificate of Ownership")."\n\n");
		$curY += 8;
		$this->SetFont("Times",'',10);
	}
}


// Get the information about this fundraiser
$sSQL = "SELECT * FROM fundraiser_fr WHERE fr_ID=".$iCurrentFundraiser;
$rsFR = RunQuery($sSQL);
$thisFR = mysql_fetch_array($rsFR);
extract ($thisFR);

// Get all the donated items
$sSQL = "SELECT * FROM donateditem_di LEFT JOIN person_per on per_ID=di_donor_ID WHERE di_FR_ID=".$iCurrentFundraiser." ORDER BY di_item";
$rsItems = RunQuery($sSQL);

$pdf = new PDF_CertificatesReport();
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

	$pdf->AddPage ();
	
	$pdf->SetFont("Times",'B',24);
	$pdf->Write (8, $di_item.":\t");
	$pdf->Write (8, stripslashes($di_title)."\n\n");
	$pdf->SetFont("Times",'',16);
	$pdf->Write (8, stripslashes($di_description)."\n");
	if ($di_estprice > 0)
		$pdf->Write (8, gettext ("Estimated value ")."\$".$di_estprice.".  ");
	if ($per_LastName!="")
		$pdf->Write (8, gettext ("Donated by ") . $per_FirstName . " " .$per_LastName.".\n\n");
}

header('Pragma: public');  // Needed for IE when using a shared SSL certificate
if ($iPDFOutputType == 1)
	$pdf->Output("FRCertificates" . date("Ymd") . ".pdf", "D");
else
	$pdf->Output();	
?>
