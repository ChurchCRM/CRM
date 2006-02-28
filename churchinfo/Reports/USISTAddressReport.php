<?php
/*******************************************************************************
*
*  filename    : Reports/USISTAddressReport.php
*  website     : http://www.churchdb.org
*  copyright   : Copyright Contributors
*  description : Creates address verification report
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

// If CSVAdminOnly option is enabled and user is not admin, redirect to the menu.
if (!$_SESSION['bAdmin'] && $bCSVAdminOnly) {
	Redirect("Menu.php");
	exit;
}

class PDF_AddressReport extends ChurchInfoReport {

	// Private properties
	var $_Margin_Left = 0;         // Left Margin
	var $_Margin_Top  = 0;         // Top margin 
	var $_Char_Size   = 12;        // Character size
	var $_CurLine     = 0;
	var $_Column      = 0;
	var $_Font        = "Times";
	var $sFamily;
	var $sLastName;

	// Sets the character size
	// This changes the line height too
	function Set_Char_Size($pt) {
		if ($pt > 3) {
			$this->_Char_Size = $pt;
			$this->SetFont($this->_Font,'',$this->_Char_Size);
		}
	}

	// Constructor
	function PDF_AddressReport() {
		global $paperFormat;
		parent::FPDF("P", "mm", $this->paperFormat);

		$this->_Column      = 0;
		$this->_CurLine     = 2;
		$this->_Font        = "Times";
		$this->SetMargins(0,0);
		$this->Open();
		$this->Set_Char_Size(12);
		$this->AddPage();
		$this->SetAutoPageBreak(false);

		$this->_Margin_Left = 12;
		$this->_Margin_Top  = 12;

		$this->Set_Char_Size(20);
		$this->Write (10, "ChurchInfo USPS Address Verification Report");
		$this->Set_Char_Size(12);
	}

	function Check_Lines($numlines)	{
		$CurY = $this->GetY();  // Temporarily store off the position

		// Need to determine if we will extend beyoned 20mm from the bottom of
		// the page.
		$this->SetY(-20);
		if ($this->_Margin_Top+(($this->_CurLine+$numlines)*5) > $this->GetY())
		{
			// Next Page
			$this->_CurLine = 5;
			$this->AddPage();
		}
		$this->SetY($CurY); // Put the position back
	}

	// Number of lines is only for the $text parameter
	function Add_Record($fam_Str, $USPS_Str) {
		$numlines=5; // add an extra blank line after record
		$this->Check_Lines($numlines);

		$_PosX = $this->_Margin_Left;
		$_PosY = $this->_Margin_Top+($this->_CurLine*5);
		$this->SetXY($_PosX, $_PosY);
		$this->MultiCell(90, 5, $fam_Str, 0, 'L');

		$_PosX += 100;
		$this->SetXY($_PosX, $_PosY);
		$this->MultiCell(90, 5, $USPS_Str, 0, 'L');

		$this->_CurLine += $numlines;
	}
}

// Read in report settings from database
$rsConfig = mysql_query("SELECT cfg_name, IFNULL(cfg_value, cfg_default) AS value FROM config_cfg WHERE cfg_section='ChurchInfoReport'");
if ($rsConfig) {
	while (list($cfg_name, $cfg_value) = mysql_fetch_row($rsConfig)) {
		$pdf->$cfg_name = $cfg_value;
	}
}

if ($_POST['MismatchReport']) {
	$iNum = 1;
	$sWhere = "WHERE fam_Country IN ('United States') ";
	$sMissing = "Ready for Lookup.  Lookup not done.";
}
elseif ($_POST['NonUSReport']) {
	$iNum = 2;
	$sWhere = "WHERE fam_Country NOT IN ('United States') ";
	$sMissing = "Unable to perform lookup for non-US address";
} else {
	Redirect("USISTAddressVerification.php");
}

// Instantiate the class and build the report.
$pdf = new PDF_AddressReport();

$sSQL  = "SELECT * FROM family_fam ";
$sSQL .= $sWhere;
$sSQL .= "ORDER BY fam_Name";

$rsFamilies = RunQuery($sSQL);

while ($aRow = mysql_fetch_array($rsFamilies)) {

	extract($aRow);
	$fam_Str  = "";
	if(strlen($fam_Address1))
		$fam_Str .= $fam_Address1 . "\n";
	if(strlen($fam_Address2))
		$fam_Str .= $fam_Address2 . "\n";
	$fam_Str .= $fam_City . " " . $fam_State . " " . $fam_Zip;

	$sSQL  = "SELECT count(lu_fam_ID) AS idexists FROM istlookup_lu ";
	$sSQL .= "WHERE lu_fam_ID IN (" . $fam_ID . ")";
	
	$rsLookup = RunQuery($sSQL);
	extract(mysql_fetch_array($rsLookup));
	if ($idexists == "0") {
		$lu_DeliveryLine1 = $sMissing;
		$lu_DeliveryLine2 = "";
		$lu_LastLine = "";
	} else {

		$sSQL  = "SELECT * FROM istlookup_lu ";
		$sSQL .= "WHERE lu_fam_ID IN (" . $fam_ID . ")";
		$rsLookup = RunQuery($sSQL);
		extract(mysql_fetch_array($rsLookup));

	}

	$lu_Str = "";
	if(strlen($lu_DeliveryLine1))
		$lu_Str .= $lu_DeliveryLine1 . "\n";
	if(strlen($lu_DeliveryLine2))
		$lu_Str .= $lu_DeliveryLine2 . "\n";
	$lu_Str .= $lu_LastLine;

	if (strtoupper($fam_Str) != strtoupper($lu_Str)){
		// Print both addresses if they don't match exactly

		$fam_Str = $fam_Name . "\n" . $fam_Str;
		$lu_Str = "Intelligent Search Technology, Ltd. Response\n" . $lu_Str;
		$pdf->Add_Record($fam_Str, $lu_Str);
	}
}

if ($iPDFOutputType == 1)
	$pdf->Output("Addresses-" . date("Ymd-Gis") . ".pdf", 'I');
else
	$pdf->Output();	
?>
