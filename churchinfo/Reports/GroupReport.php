<?php
/*******************************************************************************
*
*  filename    : Reports/GroupReport.php
*  last change : 2003-09-09
*  description : Creates a group-member directory
*
*  http://www.infocentral.org/
*  Copyright 2003  Chris Gebhardt, Jason York
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

$bOnlyCartMembers = $_POST["OnlyCart"];
$iGroupID = FilterInput($_POST["GroupID"],'int');
$iMode = FilterInput($_POST["ReportModel"],'int');

if ($iMode == 1)
	$iRoleID = FilterInput($_POST["GroupRole"],'int');
else
	$iRoleID = 0;

class PDF_Directory extends ChurchInfoReport
{

	// Private properties
	var $_Margin_Left = 0;         // Left Margin
	var $_Margin_Top  = 0;         // Top margin
	var $_Char_Size   = 12;        // Character size
	var $_CurLine     = 0;
	var $_Column      = 0;
	var $_Font        = "Times";
	var $sFamily;
	var $sLastName;

	function Header()
	{
		if ($this->PageNo() == 1)
		{
			global $sGroupName;
			global $sRoleName;
			//Select Arial bold 15
			$this->SetFont($this->_Font,'B',15);
			//Line break
			$this->Ln(7);
			//Move to the right
			$this->Cell(10);
			//Framed title
			$sTitle = $sGroupName . " - " . gettext("Group Directory");
			if (strlen($sRoleName))
				$sTitle .= " (" . $sRoleName . ")";
			$this->Cell(197,10,$sTitle,1,0,'C');
		}
	}

	function Footer()
	{
		//Go to 1.5 cm from bottom
		$this->SetY(-15);
		//Select Arial italic 8
		$this->SetFont($this->_Font,'I',8);
		//Print centered page number
		$this->Cell(0,10,'Page '.($this->PageNo()),0,0,'C');
	}

	// Sets the character size
	// This changes the line height too
	function Set_Char_Size($pt) {
		if ($pt > 3) {
			$this->_Char_Size = $pt;
			$this->SetFont($this->_Font,'',$this->_Char_Size);
		}
	}

	// Constructor
	function PDF_Directory() {
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
	}

	function Check_Lines($numlines)
	{
		$CurY = $this->GetY();  // Temporarily store off the position

		// Need to determine if we will extend beyoned 15mm from the bottom of
		// the page.
		$this->SetY(-15);
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

	// This function prints out the heading when a letter
	// changes.
/*	function Add_Header($sLetter)
	{
		$this->Check_Lines(2);
		$this->SetTextColor(255);
		$this->SetFont($this->_Font,'B',12);
		$_PosX = $this->_Margin_Left+($this->_Column*108);
		$_PosY = $this->_Margin_Top+($this->_CurLine*5);
		$this->SetXY($_PosX, $_PosY);
		$this->Cell(80, 5, $sLetter, 1, 1, "C", 1) ;
		$this->SetTextColor(0);
		$this->SetFont($this->_Font,'',$this->_Char_Size);
		$this->_CurLine+=2;
	}
*/

	// This prints the name in BOLD
	function Print_Name($sName)
	{
		$this->SetFont($this->_Font,'B',12);
		$_PosX = $this->_Margin_Left+($this->_Column*108);
		$_PosY = $this->_Margin_Top+($this->_CurLine*5);
		$this->SetXY($_PosX, $_PosY);
		$this->Write(5, $sName);
		$this->SetFont($this->_Font,'',$this->_Char_Size);
		$this->_CurLine++;
	}


	// Number of lines is only for the $text parameter
	function Add_Record($sName, $text, $numlines)
	{
		$this->Check_Lines($numlines);

		$this->Print_Name($sName);

		$_PosX = $this->_Margin_Left+($this->_Column*108);
		$_PosY = $this->_Margin_Top+($this->_CurLine*5);
		$this->SetXY($_PosX, $_PosY);
		$this->MultiCell(108, 5, $text);
		$this->_CurLine += $numlines;
	}
}

	// Get the group name
	$sSQL = "SELECT grp_Name, grp_RoleListID FROM group_grp WHERE grp_ID = " . $iGroupID;
	$rsGroupName = RunQuery($sSQL);
	$aRow = mysql_fetch_array($rsGroupName);
	$sGroupName = $aRow[0];
	$iRoleListID = $aRow[1];

	// Get the selected role name
	if ($iRoleID > 0)
	{
		$sSQL = "SELECT lst_OptionName FROM list_lst WHERE lst_ID = " . $iRoleListID . " AND lst_OptionID = " . $iRoleID;
		$rsTemp = RunQuery($sSQL);
		$aRow = mysql_fetch_array($rsTemp);
		$sRoleName = $aRow[0];
	}

	elseif (isset($_POST['GroupRoleEnable']))
	{
		$sSQL = "SELECT lst_OptionName,lst_OptionID FROM list_lst WHERE lst_ID = " . $iRoleListID;
		$rsTemp = RunQuery($sSQL);

		while ($aRow = mysql_fetch_array($rsTemp)) {
			$aRoleNames[$aRow[1]] = $aRow[0];
		}
	}

	$pdf = new PDF_Directory();

	// See if this group has special properties.
	$sSQL = "SELECT * FROM groupprop_master WHERE grp_ID = " . $iGroupID . " ORDER BY prop_ID";
	$rsProps = RunQuery($sSQL);
	$bHasProps = (mysql_num_rows($rsProps) > 0);

	$sSQL = "SELECT * FROM person_per
			LEFT JOIN family_fam ON per_fam_ID = fam_ID ";

	if ($bHasProps)
		$sSQL .= "LEFT JOIN groupprop_" . $iGroupID . " ON groupprop_" . $iGroupID . ".per_ID = person_per.per_ID ";

	$sSQL .= "LEFT JOIN person2group2role_p2g2r ON p2g2r_per_ID = person_per.per_ID
			WHERE p2g2r_grp_ID = " . $iGroupID;

	if ($iRoleID > 0)
		$sSQL .= " AND p2g2r_rle_ID = " . $iRoleID;

	if ($bOnlyCartMembers && count($_SESSION['aPeopleCart']) > 0)
		$sSQL .= " AND person_per.per_ID IN (" . ConvertCartToString($_SESSION['aPeopleCart']) . ")";

	$sSQL .= " ORDER BY per_LastName";

	$rsRecords = RunQuery($sSQL);

	// This is used for the headings for the letter changes.
	// Start out with something that isn't a letter to force the first one to work
	// $sLastLetter = "0";

	while ($aRow = mysql_fetch_array($rsRecords))
	{
		$OutStr = "";

		$pdf->sFamily = FormatFullName($aRow['per_Title'], $aRow['per_FirstName'], $aRow['per_MiddleName'], $aRow['per_LastName'], $aRow['per_Suffix'], 3);

		SelectWhichAddress($sAddress1, $sAddress2, $aRow['per_Address1'], $aRow['per_Address2'], $aRow['fam_Address1'], $aRow['fam_Address2'], false);

		$sCity = SelectWhichInfo($aRow['per_City'], $aRow['fam_City'], false);
		$sState = SelectWhichInfo($aRow['per_State'], $aRow['fam_State'], false);
		$sZip = SelectWhichInfo($aRow['per_Zip'], $aRow['fam_Zip'], false);
		$sHomePhone = SelectWhichInfo($aRow['per_HomePhone'], $aRow['fam_HomePhone'], false);
		$sWorkPhone = SelectWhichInfo($aRow['per_WorkPhone'], $aRow['fam_WorkPhone'], false);
		$sCellPhone = SelectWhichInfo($aRow['per_CellPhone'], $aRow['fam_CellPhone'], false);
		$sEmail = SelectWhichInfo($aRow['per_Email'], $aRow['fam_Email'], false);

		if (isset($_POST['GroupRoleEnable'])) {
			$OutStr = gettext("Role") . ": " . $aRoleNames[$aRow['p2g2r_rle_ID']] . "\n";
		}

		if (isset($_POST['AddressEnable']))	{
			if (strlen($sAddress1)) { $OutStr .= $sAddress1 . "\n";  }
			if (strlen($sAddress2)) { $OutStr .= $sAddress2 . "\n";  }
			if (strlen($sCity)) { $OutStr .= $sCity . ", " . $sState . " " . $sZip . "\n";  }
		}

		if (isset($_POST['HomePhoneEnable']) && strlen($sHomePhone)) {
			$TempStr = ExpandPhoneNumber($sHomePhone, $sDefaultCountry, $bWierd);
			$OutStr .= "  " . gettext("Phone") . ": " . $TempStr . "\n";
		}

		if (isset($_POST['WorkPhoneEnable']) && strlen($sWorkPhone)) {
			$TempStr = ExpandPhoneNumber($sWorkPhone, $sDefaultCountry, $bWierd);
			$OutStr .= "  " . gettext("Work") . ": " . $TempStr . "\n";
		}

		if (isset($_POST['CellPhoneEnable']) && strlen($sCellPhone)) {
			$TempStr = ExpandPhoneNumber($sCellPhone, $sDefaultCountry, $bWierd);
			$OutStr .= "  " . gettext("Cell") . ": " . $TempStr . "\n";
		}

		if (isset($_POST['EmailEnable']) && strlen($sEmail))
			$OutStr .= "  " . gettext("Email") . ": " . $sEmail . "\n";

		if (isset($_POST['OtherEmailEnable']) && strlen($aRow['per_WorkEmail']))
			$OutStr .= "  " . gettext("Other Email") . ": " . $aRow['per_WorkEmail'] .= "\n";

		if ($bHasProps)
		{
			while ($aPropRow = mysql_fetch_array($rsProps))
			{
				if (isset($_POST[$aPropRow['prop_Field'] . 'enable']))
				{
					$currentData = trim($aRow[$aPropRow['prop_Field']]);
					$OutStr .= $aPropRow['prop_Name'] . ": " . displayCustomField($aPropRow['type_ID'], $currentData, $aPropRow['prop_Special']) . "\n";
				}
			}
			mysql_data_seek($rsProps,0);
		}

		// Count the number of lines in the output string
		$numlines = 1;
		$offset = 0;
		while ($result = strpos($OutStr, "\n", $offset))
		{
			$offset = $result + 1;
			$numlines++;
		}

		//if ($numlines > 1)
		//{
			/* if (strtoupper($sLastLetter) != strtoupper(substr($pdf->sFamily,0,1)))
			{
				$pdf->Check_Lines($numlines+2);
				$sLastLetter = strtoupper(substr($pdf->sFamily,0,1));
				$pdf->Add_Header($sLastLetter);
			} */
			$pdf->Add_Record($pdf->sFamily, $OutStr, $numlines);
		// }
	}

if ($iPDFOutputType == 1)
	$pdf->Output("GroupDirectory-" . date("Ymd-Gis") . ".pdf", true);
else
	$pdf->Output();
?>
