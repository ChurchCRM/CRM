<?php
/*******************************************************************************
*
*  filename    : Reports/NewsLetterLabels.php
*  last change : 2003-08-30
*  description : Creates a PDF with all the newletter mailing labels
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

class PDF_NewsletterLabels extends ChurchInfoReport {

	// Constructor
	function PDF_NewsletterLabels() {
		parent::FPDF("P", "mm", $this->paperFormat);

		$this->SetMargins(0,0);
		$this->Open();
		$this->SetFont("Times",'',14);
		$this->SetAutoPageBreak(false);
		$this->AddPage ();
	}
}

// Instantiate the directory class and build the report.
$pdf = new PDF_NewsletterLabels();

// Get all the families which receive the newsletter by mail
$sSQL = "SELECT * FROM family_fam WHERE fam_SendNewsLetter='TRUE' ORDER BY fam_Zip";
$rsFamilies = RunQuery($sSQL);

// Loop through families
$labelThisPage = 0;
$labelHeight = 26.5;
$labelLineHeight = 6;
$labelX = 10;

while ($aFam = mysql_fetch_array($rsFamilies)) {
	extract ($aFam);

	$curY = $labelThisPage * $labelHeight + 10;

	$pdf->WriteAt ($labelX, $curY, $pdf->MakeSalutation ($fam_ID)); $curY += $labelLineHeight;
	if ($fam_Address1 != "") {
		$pdf->WriteAt ($labelX, $curY, $fam_Address1); $curY += $labelLineHeight;
	}
	if ($fam_Address2 != "") {
		$pdf->WriteAt ($labelX, $curY, $fam_Address2); $curY += $labelLineHeight;
	}
	$pdf->WriteAt ($labelX, $curY, $fam_City . ", " .  $fam_State . "  " . $fam_Zip); $curY += $labelLineHeight;
	if ($fam_Country != "" && $fam_Country != "USA" && $fam_Country != "United States") {
		$pdf->WriteAt ($labelX, $curY, $fam_Country); $curY += $labelLineHeight;
	}
	if (++$labelThisPage == 10) {
		$labelThisPage = 0;
		$pdf->AddPage ();
	}
}

if ($iPDFOutputType == 1)
	$pdf->Output("NewsLetterLabels" . date("Ymd") . ".pdf", true);
else
	$pdf->Output();	
?>
