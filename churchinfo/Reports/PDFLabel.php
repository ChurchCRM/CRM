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
require "../Include/ReportConfig.php";
require "../Include/ReportFunctions.php";

// Load the FPDF library
LoadLib_FPDF();

require "../Include/class_fpdf_labels.php";

function GenerateLabels(&$pdf, $mode, $bOnlyComplete = false)
{
	if ($mode == "indiv")
	{
		$sSQL = "SELECT * FROM person_per LEFT JOIN family_fam ON person_per.per_fam_ID = family_fam.fam_ID WHERE per_ID IN (" . ConvertCartToString($_SESSION['aPeopleCart']) . ") ORDER BY per_LastName";
	}
	else
	{
		$sSQL = "(SELECT *, 0  AS memberCount FROM person_per  LEFT JOIN family_fam ON per_fam_ID = fam_ID WHERE per_fam_ID = 0 AND per_ID in ( " . ConvertCartToString($_SESSION['aPeopleCart']) ." ))
		UNION (SELECT *, COUNT(*) AS memberCount FROM person_per  LEFT JOIN family_fam ON per_fam_ID = fam_ID WHERE per_fam_ID > 0 AND per_ID in ( " . ConvertCartToString($_SESSION['aPeopleCart']) ." ) GROUP BY per_fam_ID HAVING memberCount = 1)
		UNION (SELECT *, COUNT(*) AS memberCount FROM person_per  LEFT JOIN family_fam ON per_fam_ID = fam_ID WHERE per_fam_ID > 0 AND per_ID in ( " . ConvertCartToString($_SESSION['aPeopleCart']) ." ) GROUP BY per_fam_ID HAVING memberCount > 1)";
	}
	$rsCartItems = RunQuery($sSQL);

	while ($aRow = mysql_fetch_array($rsCartItems))
	{
		$sRowClass = AlternateRowStyle($sRowClass);

		if (($aRow['memberCount'] > 1) && ($mode == "fam"))
			$sName = $aRow['fam_Name'] . " Family";
		else
			$sName = FormatFullName($aRow['per_Title'], $aRow['per_FirstName'], "", $aRow['per_LastName'], $aRow['per_Suffix'], 1);

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
