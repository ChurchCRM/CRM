<?php
/*******************************************************************************
*
*  filename    : Update1_2_11To1_2_12.php
*  description : Update MySQL database from 1.2.11 To 1.2.12
*
*  http://www.churchdb.org/
*
*  Contributors:
*  2009 Kirby Bakken
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

$sVersion = '1.2.12';

for (; ; ) {    // This is not a loop but a section of code to be 
                // executed once.  If an error occurs running a query the
                // remaining code section is skipped and all table 
                // modifications are "un-done" at the end.
                // The idea here is that upon failure the users database
                // is restored to the previous version.

// **************************************************************************
// Make a backup copies of each changed table so we can restore from them if we have problems

$changing_tables[] = "volunteeropportunity_vol";

changing_tables = array("config_cfg", "query_qry", "queryparameters_qrp", "queryparameteroptions_qpo", "deposit_dep", "pledge_plg");

$error = 0;
foreach ($changing_tables as $tn) {
	$btn = $tn . "_backup";
	$sSQL = "DROP TABLE IF EXISTS " . $btn; 
	if (!RunQuery($sSQL, FALSE))
		$error = 1;
		break;

	$sSQL = "CREATE TABLE " . $btn . " SELECT * FROM " . $tn"; 

	if (!RunQuery($sSQL, FALSE))
		$error = 1;
		break;
}

// if we couldn't create backup tables, we don't want to do anything more
if ($error) break;

// ********************************************************
// ********************************************************
// Begin modifying tables now that backups are available
// The $bStopOnError argument to RunQuery can now be changed from
// TRUE to FALSE now that backup copies of all tables are available

//Change int type to avoid wrap of values
$sSQL = "ALTER TABLE `volunteeropportunity_vol` CHANGE `vol_ID` `vol_ID` INT( 3 ) NOT NULL AUTO_INCREMENT";
if (!RunQuery($sSQL, FALSE))
	break;

// Add vol_Order field to table so that we can alter display order of volunteer opps
$sSQL = "ALTER TABLE `volunteeropportunity_vol` ADD COLUMN `vol_Order` int(3) NOT NULL default '0' AFTER `vol_ID`";
if (!RunQuery($sSQL, FALSE))
	break;

// New config values to enable multiple fund input
$sSQL = "INSERT IGNORE INTO `config_cfg` (`cfg_id`, `cfg_name`, `cfg_value`, `cfg_type`, `cfg_default`, `cfg_tooltip`, `cfg_section`, `cfg_category`) VALUES 
(57, 'bUseScannedChecks', '0', 'boolean', '0', 'Switch to enable use of checks scanned by a character scanner', 'General', NULL),
(58, 'bChecksPerDepositForm', '14', 'number', '14', 'Number of checks on the deposit form, typically 14', 'General', NULL)";
if (!RunQuery($sSQL, FALSE))
	break;


// New query
$sSQL = "INSERT IGNORE INTO `query_qry` (`qry_ID`, `qry_SQL`, `qry_Name`, `qry_Description`, `qry_Count`) VALUES (32, 'SELECT fam_Name, fam_Envelope, b.fun_Name as Fund_Name, a.plg_amount as Pledge from family_fam left join pledge_plg a on a.plg_famID = fam_ID and a.plg_FYID=~fyid~ and a.plg_PledgeOrPayment=\'Pledge\' and a.plg_amount>0 join donationfund_fun b on b.fun_ID = a.plg_fundID order by fam_Name, a.plg_fundID', 'Family Pledge by Fiscal Year', 'Pledge summary by family name for each fund for the selected fiscal year', 1)";
if (!RunQuery($sSQL, FALSE))
	break;

$sSQL = "INSERT IGNORE INTO `queryparameters_qrp` (`qrp_ID`, `qrp_qry_ID`, `qrp_Type`, `qrp_OptionSQL`, `qrp_Name`, `qrp_Description`, `qrp_Alias`, `qrp_Default`, `qrp_Required`, `qrp_InputBoxSize`, `qrp_Validation`, `qrp_NumericMax`, `qrp_NumericMin`, `qrp_AlphaMinLength`, `qrp_AlphaMaxLength`) VALUES (32, 32, 1, '', 'Fiscal Year', 'Fiscal Year.', 'fyid', '9', 1, 0, '', 12, 9, 0, 0)";
if (!RunQuery($sSQL, FALSE))
	break;

$sSQL = "INSERT IGNORE INTO `queryparameteroptions_qpo` (`qpo_ID`, `qpo_qrp_ID`, `qpo_Display`, `qpo_Value`) VALUES 
(28, 32, '2007/2008', '12'),
(29, 32, '2008/2009', '13'),
(30, 32, '2009/2010', '14'),
(31, 32, '2010/2011', '15')";
if (!RunQuery($sSQL, FALSE))
	break;

// Change Birthday query to select Type (Member, Regular attender, etc) instead of hard-coding to Member.
$sSQL = "UPDATE `query_qry` SET `qry_SQL`='SELECT per_ID as AddToCart, per_BirthDay as Day, CONCAT(per_FirstName,'' '',per_LastName) AS Name FROM person_per WHERE per_cls_ID=~percls~ AND per_BirthMonth=~birthmonth~ ORDER BY per_BirthDay', `qry_Description`='People with birthdays in a particular month' WHERE `qry_ID`='18'";
if (!RunQuery($sSQL, FALSE))
	break;

$sSQL = "INSERT IGNORE INTO `queryparameters_qrp` (`qrp_ID`, `qrp_qry_ID`, `qrp_Type`, `qrp_OptionSQL`, `qrp_Name`, `qrp_Description`, `qrp_Alias`, `qrp_Default`, `qrp_Required`, `qrp_InputBoxSize`, `qrp_Validation`, `qrp_NumericMax`, `qrp_NumericMin`, `qrp_AlphaMinLength`, `qrp_AlphaMaxLength`) VALUES (33, 18, 1, '', 'Classification', 'Member, Regular Attender, etc.', 'percls', '1', 1, 0, '', 12, 1, 1, 2)";
if (!RunQuery($sSQL, FALSE))
	break;

$sSQL = "INSERT IGNORE INTO `queryparameteroptions_qpo` (`qpo_ID`, `qpo_qrp_ID`, `qpo_Display`, `qpo_Value`) VALUES 
(32, 33, 'Member', '1'),
(33, 33, 'Regular Attender', '2'),
(34, 33, 'Guest', '3'),
(35, 33, 'Non-Attender', '4'),
(36, 33, 'Non-Attender (staff)', '5')";
if (!RunQuery($sSQL, FALSE))
	break;


// add egive to enums

$sSQL = "ALTER TABLE `deposit_dep` CHANGE `dep_Type` `dep_Type` ENUM( 'Bank', 'CreditCard', 'BankDraft', 'eGive') NOT NULL default 'Bank'";
if (!RunQuery($sSQL, FALSE))
	break;


$sSQL = "ALTER TABLE `pledge_plg` CHANGE `plg_method` `plg_method` ENUM('CREDITCARD','CHECK','CASH','BANKDRAFT','EGIVE') default NULL";
if (!RunQuery($sSQL, FALSE))
	break;

$sSQL = "CREATE TABLE IF NOT EXISTS `egive_egv` (
  `egv_egiveID` varchar(16) character set utf8 NOT NULL,
  `egv_famID` int(11) NOT NULL,
  `egv_DateEntered` datetime NOT NULL,
  `egv_DateLastEdited` datetime NOT NULL,
  `egv_EnteredBy` smallint(6) NOT NULL default '0',
  `egv_EditedBy` smallint(6) NOT NULL default '0'
) ENGINE=MyISAM DEFAULT CHARSET=utf8";
if (!RunQuery($sSQL, FALSE))
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
	foreach ($changing_tables as $tn) {
		$btn = $tn . "_backup";
		$sSQL = "DROP TABLE IF EXISTS " . $btn; 
		if (!RunQuery($sSQL, FALSE)) {
			break;
		}
	}
} else {
	foreach ($changing_tables as $tn) {
		$btn = $tn . "_backup";
		$sSQL = "DROP TABLE IF EXISTS `" . $tn . "`";
		RunQuery($sSQL, TRUE);
		$sSQL  = "RENAME TABLE `" . $btn . "` TO `" . $tn . "`";
		RunQuery($sSQL, TRUE);
    }
}

$sSQL = $sSQL_Last;

?>
