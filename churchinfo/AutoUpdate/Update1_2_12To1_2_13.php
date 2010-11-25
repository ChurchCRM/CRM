<?php
/*******************************************************************************
*
*  filename    : Update1_2_12To1_2_13.php
*  description : auto-update script
*
*  http://www.churchdb.org/
*
*  Contributors:
*  2010 Michael Wilt
*
*  Copyright Contributors
*
*  ChurchInfo is free software; you can redistribute it and/or modify
*  it under the terms of the GNU General Public License as published by
*  the Free Software Foundation; either version 2 of the License, or
*  (at your option) any later version.
*
*  This file best viewed in a text editor with tabs stops set to 4 characters
*
******************************************************************************/

$sVersion = '1.2.13';

require "Include/MICRFunctions.php";
$micrObj = new MICRReader();


// Update the format of the scanned check stored in the family record.
// The original implementation stored the whole string, including the check number.
// The new version strips out the check number to facilitate matching.  The original
// version worked most of the time, but not in the rare cases when the check number
// was in the middle.

$sSQL = "SELECT fam_ID, fam_scanCheck from family_fam";
$rsFamilies = RunQuery($sSQL);

while ($aRow = mysql_fetch_array($rsFamilies)) {
	$scanFormat = $micrObj->IdentifyFormat ($aRow['fam_scanCheck']);
	if ($aRow['fam_scanCheck'] != '' && $scanFormat != $micrObj->NOT_RECOGNIZED) {
		$newScanCheck = $micrObj->FindRouteAndAccount ($aRow['fam_scanCheck']);
		$sSQL = "UPDATE family_fam SET fam_scanCheck='$newScanCheck' WHERE fam_ID=".$aRow['fam_ID'];
		RunQuery($sSQL);
	}
}

$sSQL = "INSERT INTO `version_ver` (`ver_version`, `ver_date`) VALUES ('".$sVersion."',NOW())";
RunQuery($sSQL, FALSE); // False means do not stop on error
$sError = mysql_error();

?>
