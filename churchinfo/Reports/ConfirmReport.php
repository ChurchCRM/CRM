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

class PDF_ConfirmReport extends ChurchInfoReport {

	// Constructor
	function PDF_ConfirmReport() {
		parent::FPDF("P", "mm", $this->paperFormat);

		$this->SetFont("Times",'',10);
		$this->SetMargins(20,20);
		$this->Open();
		$this->SetAutoPageBreak(false);
	}

	function StartNewPage ($fam_ID, $fam_Name, $fam_Address1, $fam_Address2, $fam_City, $fam_State, $fam_Zip, $fam_Country, $iYear) {
      $curY = $this->StartLetterPage ($fam_ID, $fam_Name, $fam_Address1, $fam_Address2, $fam_City, $fam_State, $fam_Zip, $fam_Country, $iYear);
		$curY += 2 * $this->incrementY;
		$blurb = $this->sConfirm1;
		$this->WriteAt ($this->leftX, $curY, $blurb);
		$curY += 2 * $this->incrementY;
		return ($curY);
	}

	function FinishPage ($curY) {
		$curY += 2 * $this->incrementY;
		$blurb = $this->sTaxReport2;
		$this->WriteAt ($this->leftX, $curY, $blurb);
		$curY += 3 * $this->incrementY;
		$blurb = $this->sTaxReport3;
		$this->WriteAt ($this->leftX, $curY, $blurb);

		$curY += 4 * $this->incrementY;

		$this->WriteAt ($this->leftX, $curY, "Sincerely,");
		$curY += 4 * $this->incrementY;
		$this->WriteAt ($this->leftX, $curY, $this->sConfirmSigner);
	}
}

// Instantiate the directory class and build the report.
$pdf = new PDF_ConfirmReport();

// Get all the families
$sSQL = "SELECT * FROM family_fam WHERE 1";
$rsFamilies = RunQuery($sSQL);

$dataCol = 50;
$dataWid = 50;

// Loop through families
while ($aFam = mysql_fetch_array($rsFamilies)) {
	extract ($aFam);

	$curY = $pdf->StartNewPage ($fam_ID, $fam_Name, $fam_Address1, $fam_Address2, $fam_City, 
                               $fam_State, $fam_Zip, $fam_Country, $iYear);

   $pdf->WriteAtCell ($pdf->leftX, $curY, $dataCol - $pdf->leftX, gettext ("Family name"));
   $pdf->WriteAtCell ($dataCol, $curY, $dataWid, $fam_Name);
}

if ($iPDFOutputType == 1)
	$pdf->Output("ConfirmReport" . date("Ymd") . ".pdf", true);
else
	$pdf->Output();	
?>
