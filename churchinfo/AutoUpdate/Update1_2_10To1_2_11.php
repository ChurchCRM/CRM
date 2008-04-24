<?php
/*******************************************************************************
*
*  filename    : Update1_2_10To1_2_11.php
*  description : Update MySQL database from 1.2.10 To 1.2.11
*
*  http://www.churchdb.org/
*
*  Contributors:
*  2007 Ed Davis
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

$sVersion = '1.2.11';

for (; ; ) {    // This is not a loop but a section of code to be 
                // executed once.  If an error occurs running a query the
                // remaining code section is skipped and all table 
                // modifications are "un-done" at the end.
                // The idea here is that upon failure the users database
                // is restored to the previous version.

// **************************************************************************
// Make a backup copy of config_cfg before making changes to the table.  This
// makes it possible to recover from an error.

// Need to back up tables we will be modifying- query_qry, queryparameteroptions_qpo, and menuconfig_mcf
$sSQL = "DROP TABLE IF EXISTS query_qry_backup"; 
if (!RunQuery($sSQL, FALSE))
	break;
$sSQL = "CREATE TABLE query_qry_backup SELECT * FROM query_qry"; 
if (!RunQuery($sSQL, FALSE))
	break;
	
$sSQL = "DROP TABLE IF EXISTS queryparameteroptions_qpo_backup"; 
if (!RunQuery($sSQL, FALSE))
	break;
$sSQL = "CREATE TABLE queryparameteroptions_qpo_backup SELECT * FROM queryparameteroptions_qpo"; 
if (!RunQuery($sSQL, FALSE))
	break;
	
$sSQL = "DROP TABLE IF EXISTS menuconfig_mcf_backup"; 
if (!RunQuery($sSQL, FALSE))
	break;
$sSQL = "CREATE TABLE menuconfig_mcf_backup SELECT * FROM menuconfig_mcf"; 
if (!RunQuery($sSQL, FALSE))
	break;

// ********************************************************
// ********************************************************
// Begin modifying tables now that backups are available
// The $bStopOnError argument to RunQuery can now be changed from
// TRUE to FALSE now that backup copies of all tables are available

$queryText = <<<EOD
SELECT per_ID as AddToCart, CONCAT('<a
href=PersonView.php?PersonID=',per_ID,'>',per_FirstName,'
',per_MiddleName,' ',per_LastName,'</a>') AS Name, 
fam_City as City, fam_State as State,
fam_Zip as ZIP, per_HomePhone as HomePhone, per_Email as Email,
per_WorkEmail as WorkEmail
FROM person_per 
RIGHT JOIN family_fam ON family_fam.fam_id = person_per.per_fam_id 
WHERE ~searchwhat~ LIKE '%~searchstring~%'
EOD;

$sSQL = "UPDATE `query_qry` SET `qry_SQL` = '" . 
         mysql_real_escape_string($queryText) . 
         "' WHERE `query_qry`.`qry_ID` = 15 "; 
if (!RunQuery($sSQL, FALSE))
	break;

$sSQL = "UPDATE `queryparameteroptions_qpo` SET `qpo_Value` = 'fam_Zip' WHERE `queryparameteroptions_qpo`.`qpo_ID` = 6 "; 
if (!RunQuery($sSQL, FALSE))
	break;

$sSQL = "UPDATE `queryparameteroptions_qpo` SET `qpo_Value` = 'fam_State' WHERE `queryparameteroptions_qpo`.`qpo_ID` = 7 "; 
if (!RunQuery($sSQL, FALSE))
	break;

$sSQL = "UPDATE `queryparameteroptions_qpo` SET `qpo_Value` = 'fam_City' WHERE `queryparameteroptions_qpo`.`qpo_ID` = 8 "; 
if (!RunQuery($sSQL, FALSE))
	break;

$sSQL = "ALTER TABLE menuconfig_mcf ADD `content_english` varchar(100) NOT NULL AFTER ismenu"; 
if (!RunQuery($sSQL, FALSE))
	break;

$sSQL = "UPDATE menuconfig_mcf SET content_english=content"; 
if (!RunQuery($sSQL, FALSE))
	break;
	
// If we got this far it means all queries ran without error.  It is okay to update
// the version information.


$sSQL = "INSERT INTO `version_ver` (`ver_version`, `ver_date`) VALUES ('".$sVersion."',NOW())";
RunQuery($sSQL, FALSE); // False means do not stop on error
break;

}  // End of for  


$sError = mysql_error();
$sSQL_Last = $sSQL;

// Let's check if MySQL database is in sync with PHP code
    $sSQL = 'SELECT * FROM version_ver ORDER BY ver_ID DESC';
    $aRow = mysql_fetch_array(RunQuery($sSQL));
    extract($aRow);

    if ($ver_version == $sVersion) {
        // We're good.  Clean up by dropping the
        // temporary tables
    	$sSQL = "DROP TABLE IF EXISTS query_qry_backup";
        RunQuery($sSQL, TRUE);
    	
    	$sSQL = "DROP TABLE IF EXISTS queryparameteroptions_qpo_backup";
        RunQuery($sSQL, TRUE);
    	
        $sSQL = "DROP TABLE IF EXISTS menuconfig_mcf_backup";
        RunQuery($sSQL, TRUE);
    	
    } else {
        // An error occured.  Clean up by restoring
        // tables to their original condition by using
        // the temporary tables.

    	$sSQL = "DROP TABLE IF EXISTS query_qry";
        RunQuery($sSQL, TRUE);
    	
    	$sSQL = "DROP TABLE IF EXISTS queryparameteroptions_qpo";
        RunQuery($sSQL, TRUE);
    	
        $sSQL = "DROP TABLE IF EXISTS menuconfig_mcf";
        RunQuery($sSQL, TRUE);
    	
        $sSQL  = "RENAME TABLE `query_qry_backup`                 TO `query_qry`, ";
        $sSQL .= "             `queryparameteroptions_qpo_backup` TO `queryparameteroptions_qpo`, ";
        $sSQL .= "             `menuconfig_mcf_backup`            TO `menuconfig_mcf`";
        RunQuery($sSQL, TRUE);
    }

$sSQL = $sSQL_Last;

?>
