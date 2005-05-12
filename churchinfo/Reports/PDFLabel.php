<?php
/*******************************************************************************
*
*  filename    : Reports/PDFLabel.php
*  last change : 2003-08-08
*  description : Creates a PDF document containing the addresses of
*                The people in the Cart
*
*  http://www.infocentral.org/
*  Copyright 2003  Jason York
*
*  Portions based on code by LPA (lpasseb@numericable.fr)
*  and Steve Dillon (steved@mad.scientist.com) from www.fpdf.org
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

function GenerateLabels(&$pdf, $mode, $bOnlyComplete = false)
{
	// $mode is "indiv" or "fam"

	$sSQL = "SELECT * FROM person_per LEFT JOIN family_fam ON person_per.per_fam_ID = family_fam.fam_ID WHERE per_ID IN (" . ConvertCartToString($_SESSION['aPeopleCart']) . ") ORDER BY fam_Zip";
	$rsCartItems = RunQuery($sSQL);

	while ($aRow = mysql_fetch_array($rsCartItems))
	{
		$sRowClass = AlternateRowStyle($sRowClass);

		if ($aRow['per_fam_ID'] == 0) { // Person is a member with no family assigned
			// echo "<p>No family assigned for " . $aRow['per_FirstName'] . " " . $aRow['per_LastName'] . "</p>" ;
			continue;
		}

		if ($mode == "fam")
			$sName = $pdf->MakeSalutation ($aRow['per_fam_ID']);
		else
			$sName = FormatFullName($aRow['per_Title'], $aRow['per_FirstName'], "", $aRow['per_LastName'], $aRow['per_Suffix'], 1);

		if ($didFam[$aRow['per_fam_ID']] && ($mode == "fam"))
			continue;

		$didFam[$aRow['per_fam_ID']] = 1;

		SelectWhichAddress($sAddress1, $sAddress2, $aRow['per_Address1'], $aRow['per_Address2'], $aRow['fam_Address1'], $aRow['fam_Address2'], false);

		$sCity = SelectWhichInfo($aRow['per_City'], $aRow['fam_City'], False);
		$sState = SelectWhichInfo($aRow['per_State'], $aRow['fam_State'], False);
		$sZip = SelectWhichInfo($aRow['per_Zip'], $aRow['fam_Zip'], False);

		$sAddress = $sAddress1;
		if ($sAddress2 != "")
			$sAddress .= "\n" . $sAddress2;

		if (!$bOnlyComplete || ( (strlen($sAddress)) && strlen($sCity) && strlen($sState) && strlen($sZip) ) )
		{
			$pdf->Add_PDF_Label(sprintf("%s\n%s\n%s, %s %s", $sName, $sAddress, $sCity, $sState, $sZip));
		}
	}
}


$startcol = FilterInput($_GET["startcol"],'int');
if ($startcol < 1) $startcol = 1;

$startrow = FilterInput($_GET["startrow"],'int');
if ($startrow < 1) $startrow = 1;

$sLabelType = FilterInput($_GET["labeltype"],'char',8);

// Standard format
$pdf = new PDF_Label($sLabelType,$startcol,$startrow);
$pdf->Open();
$sFontInfo = FontFromName($_GET["labelfont"]);
$sFontSize = $_GET["labelfontsize"];
$pdf->SetFont($sFontInfo[0],$sFontInfo[1]);
if($sFontSize != "default") $pdf->Set_Char_Size($sFontSize);
// Manually add a new page if we're using offsets
if ($startcol > 1 || $startrow > 1)	$pdf->AddPage();

$mode = $_GET["mode"];
$bOnlyComplete = ($_GET["onlyfull"] == 1);

GenerateLabels($pdf, $mode, $bOnlyComplete);

if ($iPDFOutputType == 1)
	$pdf->Output("Labels-" . date("Ymd-Gis") . ".pdf", true);
else
	$pdf->Output();
?>
