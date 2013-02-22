<?php
/*******************************************************************************
*
*  filename    : Update1_2_12To1_2_13.php
*  description : auto-update script
*
*  http://www.churchdb.org/
*
*  Contributors:
*  2010-2012 Michael Wilt
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
    "menuconfig_mcf", "family_fam", "user_usr", "config_cfg", "pledge_plg", "queryparameteroptions_qpo");

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
    $sSQL = "ALTER IGNORE TABLE pledge_plg CHANGE plg_schedule plg_schedule enum('Weekly','Monthly','Quarterly','Once','Other')";
    RunQuery($sSQL, FALSE); // False means do not stop on error

    $sSQL = "ALTER IGNORE TABLE email_message_pending_emp ADD `emp_attach_name` text NULL";    
    RunQuery($sSQL, FALSE); // False means do not stop on error
    
    $sSQL = "ALTER IGNORE TABLE email_message_pending_emp ADD emp_attach tinyint(1) default 0";
    RunQuery($sSQL, FALSE); // False means do not stop on error
    
    $sSQL = "ALTER IGNORE TABLE user_usr MODIFY `usr_Password` text NOT NULL default ''";
    RunQuery($sSQL, FALSE); // False means do not stop on error
    
    $sSQL = "ALTER IGNORE TABLE user_usr MODIFY `usr_NeedPasswordChange` tinyint(3) unsigned NOT NULL default '1'";
    RunQuery($sSQL, FALSE); // False means do not stop on error
    
	// The older database has these set to empty string rather than NULL so they do not show up
    // in the settings page.
    $sSQL = "UPDATE IGNORE config_cfg SET cfg_category=NULL WHERE cfg_id IN (61,62,63,64,65)";
    RunQuery($sSQL, FALSE); // False means do not stop on error

    $sSQL = "UPDATE IGNORE menuconfig_mcf SET name='root' WHERE name='ROOT'";
    RunQuery($sSQL, FALSE); // False means do not stop on error
    
    $sSQL = "UPDATE IGNORE config_cfg SET cfg_value='Include/fpdf17' WHERE cfg_name='sFPDF_PATH'";
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

    // add time zone config parameter, now mandatory for time functions in php
	$sSQL = "INSERT IGNORE INTO `config_cfg` VALUES (65, 'sTimeZone', 'America/New_York', 'text', 'America/New_York', 'Time zone- see http://php.net/manual/en/timezones.php for valid choices.', 'General', NULL)";
	$rsIns = RunQuery($sSQL, FALSE); // False means do not stop on error
	
// Add config 'sGMapIcons' for icons list for family map
// 
	$sSQL = "INSERT IGNORE INTO `config_cfg` VALUES (66, 'sGMapIcons', 'red-dot,green-dot,purple,yellow-dot,blue-dot,orange,yellow,green,blue,red,pink,lightblue', 'text', 'red-dot,green-dot,purple,yellow-dot,blue-dot,orange,yellow,green,blue,red,pink,lightblue', 'Names of markers for Google Maps in order of classification', 'General',NULL);";
	$rsIns = RunQuery($sSQL, FALSE); // False means do not stop on error
	
// Change Wiki link in Help section to a Help page
	$sSQL = "UPDATE IGNORE `menuconfig_mcf` SET uri = 'Help.php?page=Wiki' where name = 'wiki';";
	$rsIns = RunQuery($sSQL, FALSE); // False means do not stop on error

	$sSQL = "INSERT IGNORE INTO `menuconfig_mcf` VALUES (90, 'helpfundraiser', 'help', 0, 'Fundraiser', NULL, 'Help.php?page=Fundraiser', '', 'bAll', NULL, 0, 0, NULL, 1, 8);";
	$rsIns = RunQuery($sSQL, FALSE); // False means do not stop on error

	$sSQL = "UPDATE menuconfig_mcf SET content=content_english;";
	if (!RunQuery($sSQL, FALSE))
	    break;
	
	$sSQL = "INSERT IGNORE INTO `config_cfg` VALUES (67, 'cfgForceUppercaseZip', '0', 'boolean', '0', 'Make user-entered zip/postcodes UPPERCASE when saving to the database. Useful in the UK.', 'General',NULL);";
	$rsIns = RunQuery($sSQL, FALSE); // False means do not stop on error

	$sSQL = "INSERT IGNORE INTO `config_cfg` VALUES (72, 'bEnableNonDeductible', '0', 'boolean', '0', 'Enable non-deductible payments', 'General',NULL);";
	$rsIns = RunQuery($sSQL, FALSE); // False means do not stop on error

	$sSQL = "INSERT IGNORE INTO `config_cfg` VALUES (1031, 'sZeroGivers', 'This letter shows our record of your payments for', 'text', '0', 'Verbage for top line of zero givers report. Dates will be appended to the end of this line.', 'ChurchInfoReport',NULL);";
	$rsIcons = RunQuery($sSQL, FALSE); // False means do not stop on error
	
	$sSQL = "INSERT IGNORE INTO `config_cfg` VALUES (1032, 'sZeroGivers2', 'Thank you for your help in making a difference. We greatly appreciate your gift!', 'text', '0', 'Verbage for bottom line of tax report. Dates will be appended to the end of this line.', 'ChurchInfoReport',NULL);";
	$rsIcons = RunQuery($sSQL, FALSE); // False means do not stop on error
	
	$sSQL = "INSERT IGNORE INTO `config_cfg` VALUES (1033, 'sZeroGivers3', 'If you have any questions or corrections to make to this report, please contact the church at the above number during business hours, 9am to 4pm, M-F.', 'text', '0', 'Verbage for bottom line of tax report.', 'ChurchInfoReport',NULL);";
	$rsIcons = RunQuery($sSQL, FALSE); // False means do not stop on error

	$sSQL = "INSERT IGNORE INTO `queryparameters_qrp` (`qrp_ID`, `qrp_qry_ID`, `qrp_Type`, `qrp_OptionSQL`, `qrp_Name`, `qrp_Description`, `qrp_Alias`, `qrp_Default`, `qrp_Required`, `qrp_InputBoxSize`, `qrp_Validation`, `qrp_NumericMax`, `qrp_NumericMin`, `qrp_AlphaMinLength`, `qrp_AlphaMaxLength`) VALUES 
	(200, 200, 2, 'SELECT custom_field as Value, custom_Name as Display FROM person_custom_master', 'Custom field', 'Choose customer person field', 'custom', '1', 0, 0, '', 0, 0, 0, 0),
	(201, 200, 0, '', 'Field value', 'Match custom field to this value', 'value', '1', 0, 0, '', 0, 0, 0, 0);";
	$rsIns = RunQuery($sSQL, FALSE); // False means do not stop on error

	$sSQL = "INSERT IGNORE INTO `query_qry` (`qry_ID`, `qry_SQL`, `qry_Name`, `qry_Description`, `qry_Count`) VALUES 
	(200, 
	\"SELECT a.per_ID as AddToCart, CONCAT('<a href=PersonView.php?PersonID=',a.per_ID,'>',a.per_FirstName,' ',a.per_LastName,'</a>') AS Name FROM person_per AS a LEFT JOIN person_custom pc ON a.per_id = pc.per_ID where pc.~custom~='~value~' ORDER BY per_LastName\",
	 'CustomSearch', 
	 'Find people with a custom field value', 
	 1)";
	$rsIns = RunQuery($sSQL, FALSE); // False means do not stop on error

	$sSQL = "UPDATE `queryparameteroptions_qpo` SET `qpo_Value` = 'fam_Zip' WHERE `queryparameteroptions_qpo`.`qpo_ID` = 6 "; 
	if (!RunQuery($sSQL, FALSE))
		break;
	
	$sSQL = "UPDATE `queryparameteroptions_qpo` SET `qpo_Value` = 'fam_State' WHERE `queryparameteroptions_qpo`.`qpo_ID` = 7 "; 
	if (!RunQuery($sSQL, FALSE))
		break;
	
	$sSQL = "UPDATE `queryparameteroptions_qpo` SET `qpo_Value` = 'fam_City' WHERE `queryparameteroptions_qpo`.`qpo_ID` = 8 "; 
	if (!RunQuery($sSQL, FALSE))
		break;
	
	// push the queries that incorporate a fiscal year forward
	$sSQL = "UPDATE `queryparameteroptions_qpo` SET `qpo_Display` = '2015/2016', qpo_Value = '20' WHERE `queryparameteroptions_qpo`.`qpo_Display` = '2010/2011' "; 
	if (!RunQuery($sSQL, FALSE))
		break;
		
	$sSQL = "UPDATE `queryparameteroptions_qpo` SET `qpo_Display` = '2014/2015', qpo_Value = '19' WHERE `queryparameteroptions_qpo`.`qpo_Display` = '2009/2010' "; 
	if (!RunQuery($sSQL, FALSE))
		break;
		
	$sSQL = "UPDATE `queryparameteroptions_qpo` SET `qpo_Display` = '2013/2014', qpo_Value = '18' WHERE `queryparameteroptions_qpo`.`qpo_Display` = '2008/2009' "; 
	if (!RunQuery($sSQL, FALSE))
		break;
		
	$sSQL = "UPDATE `queryparameteroptions_qpo` SET `qpo_Display` = '2012/2013', qpo_Value = '17' WHERE `queryparameteroptions_qpo`.`qpo_Display` = '2007/2008' "; 
	if (!RunQuery($sSQL, FALSE))
		break;
	
	$sSQL = "INSERT IGNORE INTO `version_ver` (`ver_version`, `ver_date`) VALUES ('".$sVersion."',NOW())";
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
