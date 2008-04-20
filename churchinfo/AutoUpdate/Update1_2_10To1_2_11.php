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

$sSQL = "DROP TABLE IF EXISTS `tempconfig_tcfg`";
RunQuery($sSQL, TRUE);

$sSQL = "CREATE TABLE `tempconfig_tcfg` (
  `cfg_id` int(11) NOT NULL default '0',
  `cfg_name` varchar(50) NOT NULL default '',
  `cfg_value` text,
  `cfg_type` enum('text','number','date','boolean','textarea') NOT NULL default 'text',
  `cfg_default` text NOT NULL,
  `cfg_tooltip` text NOT NULL,
  `cfg_section` varchar(50) NOT NULL default '',
  PRIMARY KEY  (`cfg_id`),
  UNIQUE KEY `tcfg_name` (`cfg_name`),
  KEY `cfg_id` (`cfg_id`)
) ENGINE=MyISAM";
RunQuery($sSQL, TRUE);

$sSQL = "INSERT INTO `tempconfig_tcfg` 
SELECT `cfg_id`,`cfg_name`,`cfg_value`,`cfg_type`,`cfg_default`,`cfg_tooltip`,`cfg_section`
FROM `config_cfg` ORDER BY `cfg_id`";
RunQuery($sSQL, TRUE);

// Make a backup copy of list_lst before making changes to the table.  This
// makes it possible to recover from an error.

$sSQL = "DROP TABLE IF EXISTS `templist_tlst`";
RunQuery($sSQL, TRUE);

$sSQL = "CREATE TABLE IF NOT EXISTS `templist_tlst` (
  `lst_ID` mediumint(8) unsigned NOT NULL default '0',
  `lst_OptionID` mediumint(8) unsigned NOT NULL default '0',
  `lst_OptionSequence` tinyint(3) unsigned NOT NULL default '0',
  `lst_OptionName` varchar(50) NOT NULL default ''
) ENGINE=MyISAM";
RunQuery($sSQL, TRUE);

$sSQL = "INSERT INTO `templist_tlst` 
SELECT `lst_ID`,`lst_OptionID`,`lst_OptionSequence`,`lst_OptionName`
FROM `list_lst` ORDER BY `lst_id`, `lst_OptionID`";
RunQuery($sSQL, TRUE);

// Make a backup copy of userconfig_ucfg before making changes to the table.  This
// makes it possible to recover from an error.

$sSQL = "DROP TABLE IF EXISTS `tempuserconfig_tucfg`";
RunQuery($sSQL, TRUE);

$sSQL = "CREATE TABLE IF NOT EXISTS `tempuserconfig_tucfg` (
  `ucfg_per_id` mediumint(9) unsigned NOT NULL,
  `ucfg_id` int(11) NOT NULL default '0',
  `ucfg_name` varchar(50) NOT NULL default '',
  `ucfg_value` text,
  `ucfg_type` enum('text','number','date','boolean','textarea') NOT NULL default 'text',
  `ucfg_tooltip` text NOT NULL,
  `ucfg_permission` enum('FALSE','TRUE') NOT NULL default 'FALSE',
  PRIMARY KEY  (`ucfg_per_ID`,`ucfg_id`)
) ENGINE=MyISAM";
RunQuery($sSQL, TRUE);

$sSQL = "INSERT INTO `tempuserconfig_tucfg` 
SELECT `ucfg_per_id`,`ucfg_id`,`ucfg_name`,`ucfg_value`, `ucfg_type`, `ucfg_tooltip`, `ucfg_permission`
FROM `userconfig_ucfg` ORDER BY `ucfg_per_ID`, `ucfg_id`";
RunQuery($sSQL, TRUE);

// Make a backup copy of person_custom_master before making changes to the table.  This
// makes it possible to recover from an error.

$sSQL = "DROP TABLE IF EXISTS `temp_person_custom_master`";
RunQuery($sSQL, TRUE);

$sSQL = "CREATE TABLE IF NOT EXISTS `temp_person_custom_master` (
  `custom_Order` smallint(6) NOT NULL default '0',
  `custom_Field` varchar(5) NOT NULL default '',
  `custom_Name` varchar(40) NOT NULL default '',
  `custom_Special` mediumint(8) unsigned default NULL,
  `custom_Side` enum('left','right') NOT NULL default 'left',
  `type_ID` tinyint(4) NOT NULL default '0'
) ENGINE=MyISAM";
RunQuery($sSQL, TRUE);

$sSQL = "INSERT INTO `temp_person_custom_master` 
SELECT `custom_Order`, `custom_Field`, `custom_Name`, `custom_Special`, `custom_Side`, `type_ID` 
FROM `person_custom_master` ";
RunQuery($sSQL, TRUE);

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
        $sSQL  = "DROP TABLE IF EXISTS `tempconfig_tcfg`, `templist_tlst`, ";
        $sSQL .= "`tempuserconfig_tucfg`, `temp_person_custom_master`";
        RunQuery($sSQL, TRUE);

    } else {
        // An error occured.  Clean up by restoring
        // tables to their original condition by using
        // the temporary tables.

        $sSQL  = "DROP TABLE IF EXISTS `config_cfg`, `list_lst`, ";
        $sSQL .= "`userconfig_ucfg`, `person_custom_master`";
        RunQuery($sSQL, TRUE);

        $sSQL  = "RENAME TABLE `tempconfig_tcfg`           TO `config_cfg`, ";
        $sSQL .= "             `templist_tlst`             TO `list_lst`, ";
        $sSQL .= "             `tempuserconfig_tucfg`      TO `userconfig_ucfg`, ";
        $sSQL .= "             `temp_person_custom_master` TO `person_custom_master`";
        RunQuery($sSQL, TRUE);

    }

$sSQL = $sSQL_Last;

?>
