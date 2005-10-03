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
		$this->leftX = 10;
		$this->SetFont("Times",'',10);
		$this->SetMargins(10,20);
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
		$this->WriteAt ($this->leftX, $curY, $this->sConfirm2);

		$curY += 3 * $this->incrementY;
		$this->WriteAt ($this->leftX, $curY, $this->sConfirm3);
		$curY += 2 * $this->incrementY;
		$this->WriteAt ($this->leftX, $curY, $this->sConfirm4);

		$curY += 4 * $this->incrementY;

		$this->WriteAt ($this->leftX, $curY, "Sincerely,");
		$curY += 4 * $this->incrementY;
		$this->WriteAt ($this->leftX, $curY, $this->sConfirmSigner);
	}
}

// Instantiate the directory class and build the report.
$pdf = new PDF_ConfirmReport();

// Read in report settings from database
$rsConfig = mysql_query("SELECT cfg_name, IFNULL(cfg_value, cfg_default) AS value FROM config_cfg WHERE cfg_section='ChurchInfoReport'");
if ($rsConfig) {
	while (list($cfg_name, $cfg_value) = mysql_fetch_row($rsConfig)) {
		$pdf->$cfg_name = $cfg_value;
	}
}

// Get all the families
$sSQL = "SELECT * FROM family_fam WHERE 1 ORDER BY fam_Name";
$rsFamilies = RunQuery($sSQL);

$dataCol = 55;
$dataWid = 65;

// Loop through families
while ($aFam = mysql_fetch_array($rsFamilies)) {
	extract ($aFam);

	$curY = $pdf->StartNewPage ($fam_ID, $fam_Name, $fam_Address1, $fam_Address2, $fam_City, 
                               $fam_State, $fam_Zip, $fam_Country, $iYear);
	$curY += $pdf->incrementY;

	$pdf->SetFont("Times",'B',10);
   $pdf->WriteAtCell ($pdf->leftX, $curY, $dataCol - $pdf->leftX, gettext ("Family name"));
	$pdf->SetFont("Times",'',10);
   $pdf->WriteAtCell ($dataCol, $curY, $dataWid, $fam_Name); $curY += $pdf->incrementY;
	$pdf->SetFont("Times",'B',10);
   $pdf->WriteAtCell ($pdf->leftX, $curY, $dataCol - $pdf->leftX, gettext ("Address 1"));
	$pdf->SetFont("Times",'',10);
   $pdf->WriteAtCell ($dataCol, $curY, $dataWid, $fam_Address1); $curY += $pdf->incrementY;
	$pdf->SetFont("Times",'B',10);
   $pdf->WriteAtCell ($pdf->leftX, $curY, $dataCol - $pdf->leftX, gettext ("Address 2"));
	$pdf->SetFont("Times",'',10);
   $pdf->WriteAtCell ($dataCol, $curY, $dataWid, $fam_Address2); $curY += $pdf->incrementY;
	$pdf->SetFont("Times",'B',10);
   $pdf->WriteAtCell ($pdf->leftX, $curY, $dataCol - $pdf->leftX, gettext ("City, State, Zip"));
	$pdf->SetFont("Times",'',10);
   $pdf->WriteAtCell ($dataCol, $curY, $dataWid, ($fam_City . ", " . $fam_State . "  " . $fam_Zip)); $curY += $pdf->incrementY;
	$pdf->SetFont("Times",'B',10);
   $pdf->WriteAtCell ($pdf->leftX, $curY, $dataCol - $pdf->leftX, gettext ("Home phone"));
	$pdf->SetFont("Times",'',10);
   $pdf->WriteAtCell ($dataCol, $curY, $dataWid, $fam_HomePhone); $curY += $pdf->incrementY;
	$pdf->SetFont("Times",'B',10);
   $pdf->WriteAtCell ($pdf->leftX, $curY, $dataCol - $pdf->leftX, gettext ("Send Newsletter"));
	$pdf->SetFont("Times",'',10);
   $pdf->WriteAtCell ($dataCol, $curY, $dataWid, $fam_SendNewsLetter); $curY += $pdf->incrementY;
	$curY += $pdf->incrementY;

	$sSQL = "SELECT *, cls.lst_OptionName AS sClassName, fmr.lst_OptionName AS sFamRole FROM person_per 
				LEFT JOIN list_lst cls ON per_cls_ID = cls.lst_OptionID AND cls.lst_ID = 1
				LEFT JOIN list_lst fmr ON per_fmr_ID = fmr.lst_OptionID AND fmr.lst_ID = 2
				WHERE per_fam_ID = " . $fam_ID . " ORDER BY per_fmr_ID";
	$rsFamilyMembers = RunQuery ($sSQL);

	$XName = 10;
	$XGender = 50;
	$XRole = 60;
	$XEmail = 90;
	$XBirthday = 135;
	$XCellPhone = 155;
	$XClassification = 180;
	$XRight = 208;

	$pdf->SetFont("Times",'B',10);
   $pdf->WriteAtCell ($XName, $curY, $XGender - $XName, gettext ("Member Name"));
   $pdf->WriteAtCell ($XGender, $curY, $XRole - $XGender, gettext ("M/F"));
   $pdf->WriteAtCell ($XRole, $curY, $XEmail - $XRole, gettext ("Adult/Child"));
   $pdf->WriteAtCell ($XEmail, $curY, $XBirthday - $XEmail, gettext ("Email"));
   $pdf->WriteAtCell ($XBirthday, $curY, $XCellPhone - $XBirthday, gettext ("Birthday"));
   $pdf->WriteAtCell ($XCellPhone, $curY, $XClassification - $XCellPhone, gettext ("Cell phone"));
   $pdf->WriteAtCell ($XClassification, $curY, $XRight - $XClassification, gettext ("Member/Friend"));
	$pdf->SetFont("Times",'',10);
	$curY += $pdf->incrementY;

	while ($aMember = mysql_fetch_array($rsFamilyMembers)) {
		extract ($aMember);
		
		$pdf->WriteAtCell ($XName, $curY, $XGender - $XName, $per_FirstName . " " . $per_MiddleName . " " . $per_LastName);
		$genderStr = ($per_Gender == 1 ? "M" : "F");
		$pdf->WriteAtCell ($XGender, $curY, $XRole - $XGender, $genderStr);
		$pdf->WriteAtCell ($XRole, $curY, $XEmail - $XRole, $sFamRole);
		$pdf->WriteAtCell ($XEmail, $curY, $XBirthday - $XEmail, $per_Email);
		if ($per_BirthYear)
			$birthdayStr = $per_BirthYear . "-" . $per_BirthMonth . "-" . $per_BirthDay;
		else
			$birthdayStr = "";
		$pdf->WriteAtCell ($XBirthday, $curY, $XCellPhone - $XBirthday, $birthdayStr);
		$pdf->WriteAtCell ($XCellPhone, $curY, $XClassification - $XCellPhone, $per_CellPhone);
		$pdf->WriteAtCell ($XClassification, $curY, $XRight - $XClassification, $sClassName);
		$curY += $pdf->incrementY;
	}

	$curY += $pdf->incrementY;

	$sSQL = "SELECT * FROM person_per WHERE per_fam_ID = " . $fam_ID . " ORDER BY per_fmr_ID";
	$rsFamilyMembers = RunQuery ($sSQL);
	while ($aMember = mysql_fetch_array($rsFamilyMembers)) {
		extract ($aMember);

		// Get the Groups this Person is assigned to
		$sSQL = "SELECT grp_ID, grp_Name, grp_hasSpecialProps, role.lst_OptionName AS roleName
				FROM group_grp
				LEFT JOIN person2group2role_p2g2r ON p2g2r_grp_ID = grp_ID
				LEFT JOIN list_lst role ON lst_OptionID = p2g2r_rle_ID AND lst_ID = grp_RoleListID
				WHERE person2group2role_p2g2r.p2g2r_per_ID = " . $per_ID . "
				ORDER BY grp_Name";
		$rsAssignedGroups = RunQuery($sSQL);
		if (mysql_num_rows ($rsAssignedGroups) > 0) {
			$groupStr = "Assigned groups for " . $per_FirstName . " " . $per_LastName . ": ";

			while ($aGroup = mysql_fetch_array($rsAssignedGroups)) {
				extract ($aGroup);
				$groupStr .= $grp_Name . " (" . $roleName . ") ";
			}

			$pdf->WriteAt ($pdf->leftX, $curY, $groupStr);
			$curY += 2 * $pdf->incrementY;
		}
	}
	$pdf->FinishPage ($curY);
}

if ($iPDFOutputType == 1)
	$pdf->Output("ConfirmReport" . date("Ymd") . ".pdf", true);
else
	$pdf->Output();	
?>
