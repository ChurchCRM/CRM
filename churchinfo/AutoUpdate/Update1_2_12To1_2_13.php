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
*  LICENSE:
*  (C) Free Software Foundation, Inc.
*
*  ChurchInfo is free software; you can redistribute it and/or modify
*  it under the terms of the GNU General Public License as published by
*  the Free Software Foundation; either version 3 of the License, or
*  (at your option) any later version.
*
*  This program is distributed in the hope that it will be useful, but
*  WITHOUT ANY WARRANTY; without even the implied warranty of
*  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU
*  General Public License for more details.
*
*  http://www.gnu.org/licenses
*
*  This file best viewed in a text editor with tabs stops set to 4 characters
*
******************************************************************************/

$sVersion = '1.2.13';
$sVersion = '1.2.12.1'; // Delete this line before release

function BackUpTable ($tn)
{
    $sSQL = "DROP TABLE IF EXISTS $tn". "_backup";
    if (!RunQuery($sSQL, FALSE))
        return (false);
    $sSQL = "CREATE TABLE $tn" . "_backup SELECT * FROM $tn";
    if (!RunQuery($sSQL, FALSE))
        return (false);
    return (true);
}

function RestoreTableFromBackup ($tn)
{
    $sSQL = "DROP TABLE IF EXISTS $tn";
    if (!RunQuery($sSQL, FALSE))
        return (false);
    $sSQL  = "RENAME TABLE `$tn"."_backup` TO `$tn`";
    if (!RunQuery($sSQL, FALSE))
        return (false);
    return (true);
}

function DeleteTableBackup ($tn)
{
    $sSQL = "DROP TABLE IF EXISTS $tn"."_backup";
    if (!RunQuery($sSQL, FALSE))
        return (false);
    return (true);
}

for (; ; ) {    // This is not a loop but a section of code to be
                // executed once.  If an error occurs running a query the
                // remaining code section is skipped and all table
                // modifications are "un-done" at the end.
                // The idea here is that upon failure the users database
                // is restored to the previous version.

// **************************************************************************

// Need to back up tables we will be modifying- 

    $needToBackUp = array (
    "family_fam", "config_cfg", "pledge_plg");

    $bErr = false;
    foreach ($needToBackUp as $backUpName) {
        if (! BackUpTable ($backUpName)) {
            $bErr = true;
            break;
        }
    }
    if ($bErr)
        break;

// ********************************************************
// ********************************************************
// Begin modifying tables now that backups are available
// The $bStopOnError argument to RunQuery can now be changed from
// TRUE to FALSE now that backup copies of all tables are available

    // Allow pledge to be weekly
    $sSQL = "ALTER TABLE pledge_plg CHANGE plg_schedule plg_schedule enum('Weekly','Monthly','Quarterly','Once','Other')";
    RunQuery($sSQL, FALSE); // False means do not stop on error
        

	// The older database has these set to empty string rather than NULL so they do not show up
    // in the settings page.
    $sSQL = "UPDATE config_cfg SET cfg_category=NULL WHERE cfg_id IN (61,62,63,64,65)";
    RunQuery($sSQL, FALSE); // False means do not stop on error

// Update the format of the scanned check stored in the family record.
// The original implementation stored the whole string, including the check number.
// The new version strips out the check number to facilitate matching.  The original
// version worked most of the time, but not in the rare cases when the check number
// was in the middle.
    require "Include/MICRFunctions.php";
    $micrObj = new MICRReader();
    
    $sSQL = "SELECT fam_ID, fam_scanCheck from family_fam";
    $rsFamilies = RunQuery($sSQL);

    while ($aRow = mysql_fetch_array($rsFamilies)) {
        $scanFormat = $micrObj->IdentifyFormat ($aRow['fam_scanCheck']);
        if ($aRow['fam_scanCheck'] != '' && $scanFormat != $micrObj->NOT_RECOGNIZED) {
            $newScanCheck = $micrObj->FindRouteAndAccount ($aRow['fam_scanCheck']);
            $sSQL = "UPDATE family_fam SET fam_scanCheck='$newScanCheck' WHERE fam_ID=".$aRow['fam_ID'];
            if (!RunQuery($sSQL, FALSE))
                break 2; // break while and for
        }
    }

// Add config 'sGMapIcons' for icons list for family map
// 
	$sSQL = "INSERT INTO `config_cfg` VALUES (66, 'sGMapIcons', 'red-dot,green-dot,purple,yellow-dot,blue-dot,orange,yellow,green,blue,red,pink,lightblue', 'text', 'red-dot,green-dot,purple,yellow-dot,blue-dot,orange,yellow,green,blue,red,pink,lightblue', 'Names of markers for Google Maps in order of classification', 'General',NULL);";
	$rsIcons = RunQuery($sSQL, FALSE); // False means do not stop on error
	
// Change Wiki link in Help section to a Help page
	$sSQL = "UPDATE `menuconfig_mcf` SET uri = 'Help.php?page=Wiki' where name = 'wiki';";
	$rsIcons = RunQuery($sSQL, FALSE); // False means do not stop on error

	$sSQL = "INSERT INTO `config_cfg` VALUES (67, 'cfgForceUppercaseZip', '0', 'boolean', '0', 'Make user-entered zip/postcodes UPPERCASE when saving to the database. Useful in the UK.', 'General',NULL);";
	$rsIcons = RunQuery($sSQL, FALSE); // False means do not stop on error

	
    $sSQL = "INSERT INTO `version_ver` (`ver_version`, `ver_date`) VALUES ('".$sVersion."',NOW())";
    RunQuery($sSQL, FALSE); // False means do not stop on error
    break;

}

$sError = mysql_error();
$sSQL_Last = $sSQL;

// Let's check if MySQL database is in sync with PHP code
$sSQL = 'SELECT * FROM version_ver ORDER BY ver_ID DESC';
$aRow = mysql_fetch_array(RunQuery($sSQL));
extract($aRow);

if ($ver_version == $sVersion) {
    // We're good.  Clean up by dropping the
    // temporary tables
    foreach ($needToBackUp as $backUpName) {
        if (! DeleteTableBackup ($backUpName)) {
            break;
        }
    }
} else {
    // An error occured.  Clean up by restoring
    // tables to their original condition by using
    // the temporary tables.

    foreach ($needToBackUp as $backUpName) {
        if (! RestoreTableFromBackup ($backUpName)) {
            break;
        }
    }

    // Finally, Drop any tables that were created new

}


$sSQL = $sSQL_Last;

?>
