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
require "../Include/class_fpdf_labels.php";

class PDF_NewsletterLabels extends PDF_Label {

	// Constructor
	function PDF_NewsletterLabels($sLabelFormat) {
   	parent::PDF_Label ($sLabelFormat);
      $this->Open();
	}
}

$sLabelFormat = FilterInput($_GET["LabelFormat"]);

// Instantiate the directory class and build the report.
$pdf = new PDF_NewsletterLabels($sLabelFormat);

$sFontInfo = FontFromName($_GET["labelfont"]);
$sFontSize = $_GET["labelfontsize"];
$pdf->SetFont($sFontInfo[0],$sFontInfo[1]);
if($sFontSize != "default") $pdf->Set_Char_Size($sFontSize);

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

   $labelText = $pdf->MakeSalutation ($fam_ID);
	if ($fam_Address1 != "") {
		$labelText .= "\n" . $fam_Address1;
	}
	if ($fam_Address2 != "") {
		$labelText .= "\n" . $fam_Address2;
	}
	$labelText .= sprintf ("\n%s, %s  %s", $fam_City, $fam_State, $fam_Zip);

	if ($fam_Country != "" && $fam_Country != "USA" && $fam_Country != "United States") {
		$labelText .= "\n" . $fam_Country;
   }

	$pdf->Add_PDF_Label($labelText);
}

if ($iPDFOutputType == 1)
	$pdf->Output("NewsLetterLabels" . date("Ymd") . ".pdf", true);
else
	$pdf->Output();	
?>
