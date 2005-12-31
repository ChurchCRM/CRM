<?php
/*******************************************************************************
*
*  filename    : Reports/EnvelopeReport.php
*  description : Creates a report showing all envelope assignments
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

class PDF_EnvelopeReport extends ChurchInfoReport {

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
	function PDF_EnvelopeReport() {
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
		$this->WriteAt (12, 12, "Envelope Numbers for all Families");
		$this->Set_Char_Size(12);
	}

	function Check_Lines($numlines)

	{
		$CurY = $this->GetY();  // Temporarily store off the position

		// Need to determine if we will extend beyoned 17mm from the bottom of
		// the page.
		$this->SetY(-17);
		if ($this->_Margin_Top+(($this->_CurLine+$numlines)*5) > $this->GetY())
		{
			// Next Column or Page
			if ($this->_Column == 1)
			{
				$this->_Column = 0;
				$this->_CurLine = 2;
				$this->AddPage();
			}
			else
			{
				$this->_Column = 1;
				$this->_CurLine = 2;
			}
		}
		$this->SetY($CurY); // Put the position back
	}

	// This function formats the string for a family
	function sGetFamilyString( $aRow )
	{
		extract($aRow); // Get a row from family_fam

		return ($fam_Envelope . " " . $this->MakeSalutation ($fam_ID));
	}

	// Number of lines is only for the $text parameter
	function Add_Record($text, $numlines)
	{
		$numlines++; // add an extra blank line after record
		$this->Check_Lines($numlines);

		$_PosX = $this->_Margin_Left+($this->_Column*108);
		$_PosY = $this->_Margin_Top+($this->_CurLine*5);
		$this->SetXY($_PosX, $_PosY);
		$this->MultiCell(0, 5, $text); // set width to 0 prints to right margin
		$this->_CurLine += $numlines;
	}
}

// Instantiate the directory class and build the report.
$pdf = new PDF_EnvelopeReport();

// Read in report settings from database
$rsConfig = mysql_query("SELECT cfg_name, IFNULL(cfg_value, cfg_default) AS value FROM config_cfg WHERE cfg_section='ChurchInfoReport'");
if ($rsConfig) {
	while (list($cfg_name, $cfg_value) = mysql_fetch_row($rsConfig)) {
		$pdf->$cfg_name = $cfg_value;
	}
}

$sSQL = "SELECT fam_ID, fam_Envelope FROM family_fam WHERE fam_Envelope>0 ORDER BY fam_Envelope";
$rsRecords = RunQuery($sSQL);

while ($aRow = mysql_fetch_array($rsRecords))
{
	$OutStr = "";
	extract($aRow);

	$OutStr = $pdf->sGetFamilyString($aRow);

	// Count the number of lines in the output string
	if (strlen($OutStr))
		$numlines = substr_count($OutStr, "\n");
	else
		$numlines = 0;

	$pdf->Add_Record($OutStr, $numlines);
}

if ($iPDFOutputType == 1)
	$pdf->Output("EnvelopeAssingments-" . date("Ymd-Gis") . ".pdf", true);
else
	$pdf->Output();	
?>
