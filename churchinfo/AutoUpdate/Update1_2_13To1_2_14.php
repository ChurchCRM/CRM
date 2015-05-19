<?php
/*******************************************************************************
*
*  filename    : Update1_2_13To1_2_14.php
*  description : auto-update script
*
*  http://www.churchdb.org/
*
*  Contributors:
*  2010-2013 Michael Wilt
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

$sVersion = '1.2.14';

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
    "donateditem_di", "config_cfg", "autopayment_aut", "menuconfig_mcf");

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

    // Add picture field for donated items
    $sSQL = "ALTER IGNORE TABLE donateditem_di ADD `di_picture` text NULL";
    RunQuery($sSQL, FALSE); // False means do not stop on error

    // Add electronic payment processor defaulting to Vanco, also supporting AuthorizeNet
    $sSQL = "INSERT IGNORE INTO `config_cfg` (`cfg_id`, `cfg_name`, `cfg_value`, `cfg_type`, `cfg_default`, `cfg_tooltip`, `cfg_section`, `cfg_category`) VALUES (73, 'sElectronicTransactionProcessor', 'Vanco', 'text', 'Vanco', 'Electronic Transaction Processor', 'General', NULL)";
	if (!RunQuery($sSQL, FALSE))
	    break;
        
    $sSQL = "ALTER IGNORE TABLE autopayment_aut ADD `aut_CreditCardVanco` text NULL";
	if (!RunQuery($sSQL, FALSE))
	    break;
        
    $sSQL = "ALTER IGNORE TABLE autopayment_aut ADD `aut_AccountVanco` text NULL";
	if (!RunQuery($sSQL, FALSE))
	    break;

   	$sSQL = "INSERT IGNORE INTO `menuconfig_mcf` VALUES (91, 'automaticpayments', 'admin', 0, 'Electronic Payments', NULL, 'ElectronicPaymentList.php', '', 'bAdmin', NULL, 0, 0, NULL, 1, 12);";
	if (!RunQuery($sSQL, FALSE))
	    break;
   		
	$sSQL = "UPDATE menuconfig_mcf SET sortorder=13	WHERE mid=18 AND sortorder=12";
	if (!RunQuery($sSQL, FALSE))
	    break;
		
	$sSQL = "UPDATE menuconfig_mcf SET content=content_english;";
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
