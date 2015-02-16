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
    "donateditem_di");

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
    $sSQL = "ALTER IGNORE TABLE donateditem_di ADD `di_picture` text NULL";
    RunQuery($sSQL, FALSE); // False means do not stop on error

    $sSQL = "INSERT INTO `stgeorge_churchinfo`.`config_cfg` VALUES ('2001', 'mailChimpApiKey', '', 'text', '', 'see http://kb.mailchimp.com/accounts/management/about-api-keys', 'General', NULL);";
    RunQuery($sSQL, FALSE); // False means do not stop on error

    $sSQL = " delete FROM menuconfig_mcf WHERE name = 'main'"; // Moved top row
    RunQuery($sSQL, FALSE); // False means do not stop on error

    $sSQL = "delete FROM menuconfig_mcf WHERE parent = 'main';"; // Moved to the new menu style
    RunQuery($sSQL, FALSE); // False means do not stop on error

    $sSQL = " delete FROM menuconfig_mcf WHERE name = 'help'"; // Moved top row
    RunQuery($sSQL, FALSE); // False means do not stop on error

    $sSQL = "delete FROM menuconfig_mcf WHERE name = 'admin'"; // Moved top row
    RunQuery($sSQL, FALSE); // False means do not stop on error

    $sSQL = "INSERT INTO `menuconfig_mcf` (`mid`,`name`,`parent`,`ismenu`,`content_english`,`content`,`uri`,`statustext`,`security_grp`,`session_var`,`session_var_in_text`,`session_var_in_uri`,`url_parm_name`,`active`,`sortorder`)  values ('95', 'separator4', 'groups', '0', '---------------------------', '---------------------------', '', '', 'bAll', NULL, '0', '0', NULL, '1', '4');";
    RunQuery($sSQL, FALSE); // False means do not stop on error

    $sSQL = "INSERT INTO `menuconfig_mcf` (`mid`,`name`,`parent`,`ismenu`,`content_english`,`content`,`uri`,`statustext`,`security_grp`,`session_var`,`session_var_in_text`,`session_var_in_uri`,`url_parm_name`,`active`,`sortorder`)  values ('96', 'cvsundayschool', 'groups', '0','View Sunday School Reports','View Sunday School Reports', 'SundaySchool.php',  '', 'bAll', NULL, '0', '0', NULL, '1', '5');";
    RunQuery($sSQL, FALSE); // False means do not stop on error

    $sSQL = "INSERT INTO `menuconfig_mcf` (`mid`,`name`,`parent`,`ismenu`,`content_english`,`content`,`uri`,`statustext`,`security_grp`,`session_var`,`session_var_in_text`,`session_var_in_uri`,`url_parm_name`,`active`,`sortorder`)  values ('97', 'cvsundayschool', 'groups', '0','View Sunday School Class List','View Sunday School Class List', 'Reports/SundaySchoolClassList.php',  '', 'bAll', NULL, '0', '0', NULL, '1', '6');";
    RunQuery($sSQL, FALSE); // False means do not stop on error

    $sSQL = "INSERT INTO `menuconfig_mcf` (`mid`,`name`,`parent`,`ismenu`,`content_english`,`content`,`uri`,`statustext`,`security_grp`,`session_var`,`session_var_in_text`,`session_var_in_uri`,`url_parm_name`,`active`,`sortorder`)  values ('98', 'cvsundayschool', 'groups', '0', 'Sunday School Class List CSV Export' , 'Sunday School Class List CSV Export' ,  'Reports/SundaySchoolClassListExport.php', '', 'bAll', NULL, '0', '0', NULL, '1', '7');";
    RunQuery($sSQL, FALSE); // False means do not stop on error

    $sSQL = "ALTER TABLE `menuconfig_mcf` ADD COLUMN `icon` VARCHAR(45) NULL AFTER `sortorder`;";
    RunQuery($sSQL, FALSE); // False means do not stop on error


    /*
     *
     * UPDATE `stgeorge_churchinfo`.`menuconfig_mcf` SET `icon`='fa-users' WHERE `mid`='19';
UPDATE `stgeorge_churchinfo`.`menuconfig_mcf` SET `icon`='fa-ticket' WHERE `mid`='31';
UPDATE `stgeorge_churchinfo`.`menuconfig_mcf` SET `icon`='fa-bank' WHERE `mid`='35';
UPDATE `stgeorge_churchinfo`.`menuconfig_mcf` SET `icon`='fa-money' WHERE `mid`='84';
UPDATE `stgeorge_churchinfo`.`menuconfig_mcf` SET `icon`='fa-shopping-cart' WHERE `mid`='41';
UPDATE `stgeorge_churchinfo`.`menuconfig_mcf` SET `icon`='fa-file-pdf-o' WHERE `mid`='47';
UPDATE `stgeorge_churchinfo`.`menuconfig_mcf` SET `icon`='fa-tag' WHERE `mid`='51';
UPDATE `stgeorge_churchinfo`.`menuconfig_mcf` SET `icon`='fa-cogs' WHERE `mid`='56';

     *
     */

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
