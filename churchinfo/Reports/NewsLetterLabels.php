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
require "../Include/ReportConfig.php";
require "../Include/ReportFunctions.php";

// Load the FPDF library
LoadLib_FPDF();

class PDF_NewsletterLabels extends FPDF {

	// Private properties
	var $_Char_Size   = 14;        // Character size
	var $_Font        = "Times";

	// Sets the character size
	// This changes the line height too
	function Set_Char_Size($pt) {
		if ($pt > 3) {
			$this->_Char_Size = $pt;
			$this->SetFont($this->_Font,'',$this->_Char_Size);
		}
	}

	// Constructor
	function PDF_NewsletterLabels() {
		global $paperFormat;
		parent::FPDF("P", "mm", $paperFormat);

		$this->_Font        = "Times";
		$this->SetMargins(0,0);
		$this->Open();
		$this->Set_Char_Size(14);
		$this->SetAutoPageBreak(false);
		$this->AddPage ();
	}

	function WriteAt ($x, $y, $str) {
		$this->SetXY ($x, $y);
		$this->Write (4, $str);
	}

	function MakeSalutation ($famID) {
		// Make it put the name if there is only one individual in the family
		// Make it put two first names and the last name when there are exactly two people in the family (e.g. "Nathaniel and Jeanette Brooks")
		// Make it put two whole names where there are exactly two people with different names (e.g. "Doug Philbrook and Karen Andrews")
		// When there are more than two people in the family I don't have any way to know which people are children, so I would have to just use the family name (e.g. "Grossman Family").
		$sSQL = "SELECT * FROM family_fam WHERE fam_ID=" . $famID;
		$rsFamInfo = RunQuery($sSQL);
		$aFam = mysql_fetch_array($rsFamInfo);
		extract ($aFam);

		$sSQL = "SELECT * FROM person_per WHERE per_fam_ID=" . $famID;
		$rsMembers = RunQuery($sSQL);
		$numMembers = mysql_num_rows ($rsMembers);

		if ($numMembers == 1) {
			$aMember = mysql_fetch_array($rsMembers);
			extract ($aMember);
			return ($per_FirstName . " " . $per_LastName);
		} else if ($numMembers == 2) {
			$firstMember = mysql_fetch_array($rsMembers);
			extract ($firstMember);
			$firstFirstName = $per_FirstName;
			$firstLastName = $per_LastName;
			$secondMember = mysql_fetch_array($rsMembers);
			extract ($secondMember);
			$secondFirstName = $per_FirstName;
			$secondLastName = $per_LastName;
			if ($firstLastName == $secondLastName) {
				return ($firstFirstName . " & " . $secondFirstName . " " . $firstLastName);
			} else {
				return ($firstFirstName . " " . $firstLastName . " & " . $secondFirstName . " " . $secondLastName);
			}
		} else {
			return ($fam_Name . " Family");
		}
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
