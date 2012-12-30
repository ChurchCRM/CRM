<?php
/*******************************************************************************
*
*  filename    : Update1_2_11To1_2_12.php
*  description : Update MySQL database from 1.2.11 To 1.2.12
*
*  http://www.churchdb.org/
*
*  Contributors:
*  2009 Kirby Bakken, Michael Wilt
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

$bErr = false;

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

// Need to back up tables we will be modifying- query_qry, queryparameteroptions_qpo, and menuconfig_mcf

    $needToBackUp = array (
    "config_cfg",
    "deposit_dep",
    "menuconfig_mcf",
    "pledge_plg",
    "queryparameteroptions_qpo",
    "queryparameters_qrp",
    "query_qry",
    "volunteeropportunity_vol");
    
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

$sSQL = "INSERT INTO `config_cfg` (`cfg_id`, `cfg_name`, `cfg_value`, `cfg_type`, `cfg_default`, `cfg_tooltip`, `cfg_section`, `cfg_category`) VALUES 
(57, 'iChecksPerDepositForm', '14', 'number', '14', 'Number of checks for Deposit Slip Report', 'General', NULL),
(58, 'bUseScannedChecks', '0', 'boolean', '0', 'Set true to enable use of scanned checks', 'General', NULL)";
if (!RunQuery($sSQL, FALSE))
    break;

$sSQL = "UPDATE config_cfg SET cfg_value=concat(cfg_value,',32')  WHERE cfg_name='aFinanceQueries'";
if (!RunQuery($sSQL, FALSE))
    break;

$sSQL = "UPDATE config_cfg SET cfg_value='Include/fpdf16'  WHERE cfg_name='sFPDF_PATH'";
if (!RunQuery($sSQL, FALSE))
    break;

$sSQL = "UPDATE config_cfg SET cfg_tooltip='Intelligent Search Technolgy, Ltd. CorrectAddress Username for https://www.intelligentsearch.com/Hosted/User' WHERE cfg_id='54'";
if (!RunQuery($sSQL, FALSE))
    break;

$sSQL = "UPDATE config_cfg SET cfg_tooltip='Intelligent Search Technolgy, Ltd. CorrectAddress Password for https://www.intelligentsearch.com/Hosted/User' WHERE cfg_id='55'";
if (!RunQuery($sSQL, FALSE))
    break;

$sSQL = "ALTER TABLE `menuconfig_mcf` CHANGE `content` `content` varchar(100) NULL";
if (!RunQuery($sSQL, FALSE))
    break;

$sSQL = "INSERT INTO `menuconfig_mcf` (`mid`, `name`, `parent`, `ismenu`, `content_english`, `content`, `uri`, `statustext`, `security_grp`, `session_var`, `session_var_in_text`, `session_var_in_uri`, `url_parm_name`, `active`, `sortorder`) VALUES 
(84, 'fundraiser', 'root', 1, 'Fundraiser', NULL, '', '', 'bAll', NULL, 0, 0, NULL, 1, 5),
(85, 'newfundraiser', 'fundraiser', 0, 'Create New Fundraiser', NULL, 'FundRaiserEditor.php?FundRaiserID=-1', '', 'bAll', NULL, 0, 0, NULL, 1, 1),
(86, 'viewfundraiser', 'fundraiser', 0, 'View All Fundraisers', NULL, 'FindFundRaiser.php', '', 'bAll', NULL, 0, 0, NULL, 1, 1),
(87, 'editfundraiser', 'fundraiser', 0, 'Edit Fundraiser', NULL, 'FundRaiserEditor.php', '', 'bAll', 'iCurrentFundraiser', 1, 1, 'FundRaiserID', 1, 5),
(88, 'viewbuyers', 'fundraiser', 0, 'View Buyers', NULL, 'PaddleNumList.php', '', 'bAll', 'iCurrentFundraiser', 1, 1, 'FundRaiserID', 1, 5),
(89, 'adddonors', 'fundraiser', 0, 'Add Donors to Buyer List', NULL, 'AddDonors.php', '', 'bAll', 'iCurrentFundraiser', 1, 1, 'FundRaiserID', 1, 5);";
if (!RunQuery($sSQL, FALSE))
    break;

$sSQL = "UPDATE menuconfig_mcf SET content=content_english;";
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
    
$sSQL = "ALTER TABLE `volunteeropportunity_vol` DROP PRIMARY KEY;";
if (!RunQuery($sSQL, FALSE))
    break;

$sSQL = "ALTER TABLE `volunteeropportunity_vol` CHANGE `vol_ID` `vol_ID` INT(3) PRIMARY KEY NOT NULL AUTO_INCREMENT;";
if (!RunQuery($sSQL, FALSE))
    break;

$sSQL = "ALTER TABLE `volunteeropportunity_vol` ADD COLUMN `vol_Order` INT(3) NOT NULL default '0' AFTER `vol_ID`;";
if (!RunQuery($sSQL, FALSE))
    break;

$sSQL = "CREATE TABLE `paddlenum_pn` (
   `pn_ID` mediumint(9) unsigned NOT NULL auto_increment,
   `pn_fr_ID` mediumint(9) unsigned,
   `pn_Num` mediumint(9) unsigned,
   `pn_per_ID` mediumint(9) NOT NULL default '0',
   PRIMARY KEY  (`pn_ID`),
   UNIQUE KEY `pn_ID` (`pn_ID`)
 ) ENGINE=MyISAM  AUTO_INCREMENT=1 ;";
if (!RunQuery($sSQL, FALSE))
    break;

$sSQL = "CREATE TABLE `fundraiser_fr` (
   `fr_ID` mediumint(9) unsigned NOT NULL auto_increment,
   `fr_date` date default NULL,
   `fr_title` varchar(128) NOT NULL,
   `fr_description` text,
   `fr_EnteredBy` smallint(5) unsigned NOT NULL default '0',
   `fr_EnteredDate` date NOT NULL,
   PRIMARY KEY  (`fr_ID`),
   UNIQUE KEY `fr_ID` (`fr_ID`)
 ) ENGINE=MyISAM  AUTO_INCREMENT=1 ;";
if (!RunQuery($sSQL, FALSE))
    break;

$sSQL = "CREATE TABLE `donateditem_di` (
   `di_ID` mediumint(9) unsigned NOT NULL auto_increment,
   `di_item` varchar(32) NOT NULL,
   `di_FR_ID` mediumint(9) unsigned NOT NULL,
   `di_donor_ID` mediumint(9) NOT NULL default '0',
   `di_buyer_ID` mediumint(9) NOT NULL default '0',
   `di_multibuy` smallint(1) NOT NULL default '0',
   `di_title` varchar(128) NOT NULL,
   `di_description` text,
   `di_sellprice` decimal(8,2) default NULL,
   `di_estprice` decimal(8,2) default NULL,
   `di_minimum` decimal(8,2) default NULL,
   `di_materialvalue` decimal(8,2) default NULL,
   `di_EnteredBy` smallint(5) unsigned NOT NULL default '0',
   `di_EnteredDate` date NOT NULL,
   PRIMARY KEY  (`di_ID`),
   UNIQUE KEY `di_ID` (`di_ID`)
) ENGINE=MyISAM  AUTO_INCREMENT=1 ;";
if (!RunQuery($sSQL, FALSE))
    break;

$sSQL = "CREATE TABLE `multibuy_mb` (
  `mb_ID` mediumint(9) unsigned NOT NULL auto_increment,
  `mb_per_ID` mediumint(9) NOT NULL default '0',
  `mb_item_ID` mediumint(9) NOT NULL default '0',
  `mb_count` decimal(8,0) default NULL,
  PRIMARY KEY  (`mb_ID`),
  UNIQUE KEY `mb_ID` (`mb_ID`)
) ENGINE=MyISAM  AUTO_INCREMENT=1 ;";
if (!RunQuery($sSQL, FALSE))
    break;

// add egive to enums

$sSQL = "ALTER TABLE `deposit_dep` CHANGE `dep_Type` `dep_Type` ENUM( 'Bank', 'CreditCard', 'BankDraft', 'eGive') NOT NULL default 'Bank'";
if (!RunQuery($sSQL, FALSE))
    break;

$sSQL = "ALTER TABLE `pledge_plg` CHANGE `plg_method` `plg_method` ENUM('CREDITCARD','CHECK','CASH','BANKDRAFT','EGIVE') default NULL";
if (!RunQuery($sSQL, FALSE))
    break;

$sSQL = "ALTER TABLE `pledge_plg` ADD `plg_GroupKey` VARCHAR( 64 ) NOT NULL ";
if (!RunQuery($sSQL, FALSE))
    break;

$sSQL = "CREATE TABLE IF NOT EXISTS `egive_egv` (
  `egv_egiveID` varchar(16) character set utf8 NOT NULL,
  `egv_famID` int(11) NOT NULL,
  `egv_DateEntered` datetime NOT NULL,
  `egv_DateLastEdited` datetime NOT NULL,
  `egv_EnteredBy` smallint(6) NOT NULL default '0',
  `egv_EditedBy` smallint(6) NOT NULL default '0'
) ENGINE=MyISAM ";
if (!RunQuery($sSQL, FALSE))
    break;
    
// Fix age

$sSQL = "UPDATE `query_qry` set `qry_SQL`='SELECT per_ID as AddToCart,CONCAT(''<a\r\nhref=PersonView.php?PersonID='',per_ID,''>'',per_FirstName,''\r\n'',per_LastName,''</a>'') AS Name,\r\nCONCAT(per_BirthMonth,''/'',per_BirthDay,''/'',per_BirthYear) AS ''Birth Date'',\r\nDATE_FORMAT(FROM_DAYS(TO_DAYS(NOW())-TO_DAYS(CONCAT(per_BirthYear,''-'',per_BirthMonth,''-'',per_BirthDay))),''%Y'')+0 AS  ''Age''\r\nFROM person_per\r\nWHERE\r\nDATE_ADD(CONCAT(per_BirthYear,''-'',per_BirthMonth,''-'',per_BirthDay),INTERVAL\r\n~min~ YEAR) <= CURDATE()\r\nAND\r\nDATE_ADD(CONCAT(per_BirthYear,''-'',per_BirthMonth,''-'',per_BirthDay),INTERVAL\r\n(~max~ + 1) YEAR) >= CURDATE()' WHERE `qry_ID`='4'";
if (!RunQuery($sSQL, FALSE))
    break;

$sSQL = "UPDATE pledge_plg SET plg_GroupKey=CONCAT(plg_method,plg_plgID)";
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
    $sSQL = 'DROP TABLE IF EXISTS `paddlenum_pn`';
    RunQuery($sSQL, FALSE);
    $sSQL = 'DROP TABLE IF EXISTS `fundraiser_fr`';
    RunQuery($sSQL, FALSE);
    $sSQL = 'DROP TABLE IF EXISTS `donateditem_di`';
    RunQuery($sSQL, FALSE);
    $sSQL = 'DROP TABLE IF EXISTS `multibuy_mb`';
    RunQuery($sSQL, FALSE);
    $sSQL = 'DROP TABLE IF EXISTS `egive_egv`';
    RunQuery($sSQL, FALSE);
}

$sSQL = $sSQL_Last;

?>
