<?php
/*******************************************************************************
*
*  filename    : Update1_2_8To1_2_9.php
*  description : Update MySQL database from 1.29 To 1.2.10
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

$sSQL = "INSERT INTO `version_ver` (`ver_version`, `ver_date`) VALUES ('1.2.10',NOW())";
RunQuery($sSQL, FALSE); // False means do not stop on error

$sSQL = "CREATE TABLE `menuconfig_mcf` (
  `mid` int(11) NOT NULL auto_increment,
  `name` varchar(20) NOT NULL,
  `parent` varchar(20) NOT NULL,
  `ismenu` tinyint(1) NOT NULL,
  `content` varchar(100) NOT NULL,
  `uri` varchar(255) NOT NULL,
  `statustext` varchar(255) NOT NULL,
  `security_grp` varchar(50) NOT NULL,
  `session_var` varchar(50) default NULL,
  `session_var_in_text` tinyint(1) NOT NULL,
  `session_var_in_uri` tinyint(1) NOT NULL,
  `url_parm_name` varchar(50) default NULL,
  `active` tinyint(1) NOT NULL,
  `sortorder` tinyint(3) NOT NULL,
  PRIMARY KEY  (`mid`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8 AUTO_INCREMENT=81 ";
RunQuery($sSQL, FALSE);

$sSQL = "INSERT INTO `menuconfig_mcf` VALUES (1, 'ROOT', '', 1, ".gettext('Main').", '', '', 'bAll', NULL, 0, 0, NULL, 1, 0)";
RunQuery($sSQL, FALSE);

$sSQL = "INSERT INTO `menuconfig_mcf` VALUES (2, 'main', 'root', 1, ".gettext('Main').", '', '', 'bAll', NULL, 0, 0, NULL, 1, 1)";
RunQuery($sSQL, FALSE);

$sSQL = "INSERT INTO `menuconfig_mcf` VALUES (3, 'logoff', 'main', 0, ".gettext('Log Off').", 'Default.php?Logoff=True', '', 'bAll', NULL, 0, 0, NULL, 1, 1)";
RunQuery($sSQL, FALSE);

$sSQL = "INSERT INTO `menuconfig_mcf` VALUES (4, 'chgpassword', 'main', 0, ".gettext('Change My Password').", 'UserPasswordChange.php', '', 'bAll', NULL, 0, 0, NULL, 1, 2)";
RunQuery($sSQL, FALSE);

$sSQL = "INSERT INTO `menuconfig_mcf` VALUES (5, 'chgsetting', 'main', 0, ".gettext('Change My Settings').", 'SettingsIndividual.php', '', 'bAll', NULL, 0, 0, NULL, 1, 0)";
RunQuery($sSQL, FALSE);

$sSQL = "INSERT INTO `menuconfig_mcf` VALUES (6, 'admin', 'root', 1, ".gettext('Admin').", '', '', 'bAdmin', NULL, 0, 0, NULL, 1, 2)";
RunQuery($sSQL, FALSE);

$sSQL = "INSERT INTO `menuconfig_mcf` VALUES (7, 'editusers', 'admin', 0, ".gettext('Edit Users').", 'UserList.php', '', 'bAdmin', NULL, 0, 0, NULL, 1, 1)";
RunQuery($sSQL, FALSE);

$sSQL = "INSERT INTO `menuconfig_mcf` VALUES (8, 'addnewuser', 'admin', 0, ".gettext('Add New User').", 'UserEditor.php', '', 'bAdmin', NULL, 0, 0, NULL, 1, 2)";
RunQuery($sSQL, FALSE);

$sSQL = "INSERT INTO `menuconfig_mcf` VALUES (9, 'custompersonfld', 'admin', 0, ".gettext('Edit Custom Person Fields').", 'PersonCustomFieldsEditor.php', '', 'bAdmin', NULL, 0, 0, NULL, 1, 3)";
RunQuery($sSQL, FALSE);

$sSQL = "INSERT INTO `menuconfig_mcf` VALUES (10, 'donationfund', 'admin', 0, ".gettext('Edit Donation Funds').", 'DonationFundEditor.php', '', 'bAdmin', NULL, 0, 0, NULL, 1, 4)";
RunQuery($sSQL, FALSE);

$sSQL = "INSERT INTO `menuconfig_mcf` VALUES (11, 'dbbackup', 'admin', 0, ".gettext('Backup Database').", 'BackupDatabase.php', '', 'bAdmin', NULL, 0, 0, NULL, 1, 5)";
RunQuery($sSQL, FALSE);

$sSQL = "INSERT INTO `menuconfig_mcf` VALUES (12, 'cvsimport', 'admin', 0, ".gettext('CSV Import').", 'CSVImport.php', '', 'bAdmin', NULL, 0, 0, NULL, 1, 6)";
RunQuery($sSQL, FALSE);

$sSQL = "INSERT INTO `menuconfig_mcf` VALUES (13, 'accessreport', 'admin', 0, ".gettext('Access report').", 'AccessReport.php', '', 'bAdmin', NULL, 0, 0, NULL, 1, 7)";
RunQuery($sSQL, FALSE);

$sSQL = "INSERT INTO `menuconfig_mcf` VALUES (14, 'generalsetting', 'admin', 0, ".gettext('Edit General Settings').", 'SettingsGeneral.php', '', 'bAdmin', NULL, 0, 0, NULL, 1, 8)";
RunQuery($sSQL, FALSE);

$sSQL = "INSERT INTO `menuconfig_mcf` VALUES (15, 'reportsetting', 'admin', 0, ".gettext('Edit Report Settings').", 'SettingsReport.php', '', 'bAdmin', NULL, 0, 0, NULL, 1, 9)";
RunQuery($sSQL, FALSE);

$sSQL = "INSERT INTO `menuconfig_mcf` VALUES (16, 'userdefault', 'admin', 0, ".gettext('Edit User Default Settings').", 'SettingsUser.php', '', 'bAdmin', NULL, 0, 0, NULL, 1, 10)";
RunQuery($sSQL, FALSE);

$sSQL = "INSERT INTO `menuconfig_mcf` VALUES (17, 'envelopmgr', 'admin', 0, ".gettext('Envelope Manager').", 'ManageEnvelopes.php', '', 'bAdmin', NULL, 0, 0, NULL, 1, 11)";
RunQuery($sSQL, FALSE);

if (! $bRegistered) {
	$sSQL = "INSERT INTO `menuconfig_mcf` VALUES (18, 'register', 'admin', 0, ".gettext('Please select this option to register ChurchInfo after configuring.').", 'Register.php', '', 'bAdmin', NULL, 0, 0, NULL, 1, 12)";
} else {
	$sSQL = "INSERT INTO `menuconfig_mcf` VALUES (18, 'register', 'admin', 0, ".gettext('Update registration').", 'Register.php', '', 'bAdmin', NULL, 0, 0, NULL, 1, 12)";
}
RunQuery($sSQL, FALSE);

$sSQL = "INSERT INTO `menuconfig_mcf` VALUES (19, 'people', 'root', 1, ".gettext('People/Families').", '', 'People/Families', 'bAll', NULL, 0, 0, NULL, 1, 3)";
RunQuery($sSQL, FALSE);

$sSQL = "INSERT INTO `menuconfig_mcf` VALUES (20, 'newperson', 'people', 0, ".gettext('Add New Person').", 'PersonEditor.php', '', 'bAddRecords', NULL, 0, 0, NULL, 1, 1)";
RunQuery($sSQL, FALSE);

$sSQL = "INSERT INTO `menuconfig_mcf` VALUES (21, 'viewperson', 'people', 0, ".gettext('View All Persons').", 'SelectList.php?mode=person', '', 'bAll', NULL, 0, 0, NULL, 1, 2)";
RunQuery($sSQL, FALSE);

$sSQL = "INSERT INTO `menuconfig_mcf` VALUES (22, 'classes', 'people', 0, ".gettext('Classification Manager').", 'OptionManager.php?mode=classes', '', 'bMenuOptions', NULL, 0, 0, NULL, 1, 3)";
RunQuery($sSQL, FALSE);

$sSQL = "INSERT INTO `menuconfig_mcf` VALUES (23, 'separator1', 'people', 0, '---------------------------', '', '', 'bAll', NULL, 0, 0, NULL, 1, 4)";
RunQuery($sSQL, FALSE);

$sSQL = "INSERT INTO `menuconfig_mcf` VALUES (24, 'volunteeropportunity', 'people', 0, ".gettext('Edit volunteer opportunities').", 'VolunteerOpportunityEditor.php', '', 'bAll', NULL, 0, 0, NULL, 1, 5)";
RunQuery($sSQL, FALSE);

$sSQL = "INSERT INTO `menuconfig_mcf` VALUES (25, 'separator2', 'people', 0, '---------------------------', '', '', 'bAll', NULL, 0, 0, NULL, 1, 6)";
RunQuery($sSQL, FALSE);

$sSQL = "INSERT INTO `menuconfig_mcf` VALUES (26, 'newfamily', 'people', 0, ".gettext('Add New Family').", 'FamilyEditor.php', '', 'bAddRecords', NULL, 0, 0, NULL, 1, 7)";
RunQuery($sSQL, FALSE);

$sSQL = "INSERT INTO `menuconfig_mcf` VALUES (27, 'viewfamily', 'people', 0, ".gettext('View All Families').", 'SelectList.php?mode=family', '', 'bAll', NULL, 0, 0, NULL, 1, 8)";
RunQuery($sSQL, FALSE);

$sSQL = "INSERT INTO `menuconfig_mcf` VALUES (28, 'familygeotools', 'people', 0, ".gettext('Family Geographic Utilties').", 'GeoPage.php', '', 'bAll', NULL, 0, 0, NULL, 1, 9)";
RunQuery($sSQL, FALSE);

$sSQL = "INSERT INTO `menuconfig_mcf` VALUES (29, 'familymap', 'people', 0, ".gettext('Family Map').", 'MapUsingGoogle.php?GroupID=-1', '', 'bAll', NULL, 0, 0, NULL, 1, 10)";
RunQuery($sSQL, FALSE);

$sSQL = "INSERT INTO `menuconfig_mcf` VALUES (30, 'rolemanager', 'people', 0, ".gettext('Family Roles Manager').", 'OptionManager.php?mode=famroles', '', 'bMenuOptions', NULL, 0, 0, NULL, 1, 11)";
RunQuery($sSQL, FALSE);

$sSQL = "INSERT INTO `menuconfig_mcf` VALUES (31, 'events', 'root', 1, ".gettext('Events').", '', 'Events', 'bAll', NULL, 0, 0, NULL, 1, 4)";
RunQuery($sSQL, FALSE);

$sSQL = "INSERT INTO `menuconfig_mcf` VALUES (32, 'listevent', 'events', 0, ".gettext('List Church Events').", 'ListEvents.php', 'List Church Events', 'bAll', NULL, 0, 0, NULL, 1, 1)";
RunQuery($sSQL, FALSE);

$sSQL = "INSERT INTO `menuconfig_mcf` VALUES (33, 'addevent', 'events', 0, ".gettext('Add Church Event').", 'EventNames.php', 'Add Church Event', 'bAll', NULL, 0, 0, NULL, 1, 2)";
RunQuery($sSQL, FALSE);

$sSQL = "INSERT INTO `menuconfig_mcf` VALUES (34, 'eventype', 'events', 0, ".gettext('List Event Types').", 'EventNames.php', '', 'bAdmin', NULL, 0, 0, NULL, 1, 3)";
RunQuery($sSQL, FALSE);

$sSQL = "INSERT INTO `menuconfig_mcf` VALUES (35, 'deposit', 'root', 1, ".gettext('Deposit').", '', '', 'bFinance', NULL, 0, 0, NULL, 1, 5)";
RunQuery($sSQL, FALSE);

$sSQL = "INSERT INTO `menuconfig_mcf` VALUES (36, 'newdeposit', 'deposit', 0, ".gettext('Create New Deposit').", 'DepositSlipEditor.php?DepositType=Bank', '', 'bFinance', NULL, 0, 0, NULL, 1, 1)";
RunQuery($sSQL, FALSE);

$sSQL = "INSERT INTO `menuconfig_mcf` VALUES (37, 'viewdeposit', 'deposit', 0, ".gettext('View All Deposits').", 'FindDepositSlip.php', '', 'bFinance', NULL, 0, 0, NULL, 1, 2)";
RunQuery($sSQL, FALSE);

$sSQL = "INSERT INTO `menuconfig_mcf` VALUES (38, 'depositreport', 'deposit', 0, ".gettext('Deposit Reports').", 'FinancialReports.php', '', 'bFinance', NULL, 0, 0, NULL, 1, 3)";
RunQuery($sSQL, FALSE);

$sSQL = "INSERT INTO `menuconfig_mcf` VALUES (39, 'separator3', 'deposit', 0, '---------------------------', '', '', 'bFinance', NULL, 0, 0, NULL, 1, 4)";
RunQuery($sSQL, FALSE);

$sSQL = "INSERT INTO `menuconfig_mcf` VALUES (40, 'depositslip', 'deposit', 0, ".gettext('Edit Deposit Slip').", 'DepositSlipEditor.php', '', 'bFinance', 'iCurrentDeposit', 1, 1, 'DepositSlipID', 1, 5)";
RunQuery($sSQL, FALSE);

$sSQL = "INSERT INTO `menuconfig_mcf` VALUES (41, 'cart', 'root', 1, ".gettext('Cart').", '', '', 'bAll', NULL, 0, 0, NULL, 1, 6)";
RunQuery($sSQL, FALSE);

$sSQL = "INSERT INTO `menuconfig_mcf` VALUES (42, 'viewcart', 'cart', 0, ".gettext('List Cart Items').", 'CartView.php', '', 'bAll', NULL, 0, 0, NULL, 1, 1)";
RunQuery($sSQL, FALSE);

$sSQL = "INSERT INTO `menuconfig_mcf` VALUES (43, 'emptycart', 'cart', 0, ".gettext('Empty Cart').", 'CartView.php?Action=EmptyCart', '', 'bAll', NULL, 0, 0, NULL, 1, 2)";
RunQuery($sSQL, FALSE);

$sSQL = "INSERT INTO `menuconfig_mcf` VALUES (44, 'carttogroup', 'cart', 0, ".gettext('Empty Cart to Group').", 'CartToGroup.php', '', 'bManageGroups', NULL, 0, 0, NULL, 1, 3)";
RunQuery($sSQL, FALSE);

$sSQL = "INSERT INTO `menuconfig_mcf` VALUES (45, 'carttofamily', 'cart', 0, ".gettext('Empty Cart to Family').", 'CartToFamily.php', '', 'bAddRecords', NULL, 0, 0, NULL, 1, 4)";
RunQuery($sSQL, FALSE);

$sSQL = "INSERT INTO `menuconfig_mcf` VALUES (46, 'carttoevent', 'cart', 0, ".gettext('Empty Cart to Event').", 'CartToEvent.php', 'Empty Cart contents to Event', 'bAll', NULL, 0, 0, NULL, 1, 5)";
RunQuery($sSQL, FALSE);

$sSQL = "INSERT INTO `menuconfig_mcf` VALUES (47, 'report', 'root', 1, ".gettext('Data/Reports').", '', '', 'bAll', NULL, 0, 0, NULL, 1, 7)";
RunQuery($sSQL, FALSE);

$sSQL = "INSERT INTO `menuconfig_mcf` VALUES (48, 'cvsexport', 'report', 0, ".gettext('CSV Export Records').", 'CSVExport.php', '', 'bAll', NULL, 0, 0, NULL, 1, 1)";
RunQuery($sSQL, FALSE);

$sSQL = "INSERT INTO `menuconfig_mcf` VALUES (49, 'querymenu', 'report', 0, ".gettext('Query Menu').", 'QueryList.php', '', 'bAll', NULL, 0, 0, NULL, 1, 2)";
RunQuery($sSQL, FALSE);

$sSQL = "INSERT INTO `menuconfig_mcf` VALUES (50, 'reportmenu', 'report', 0, ".gettext('Reports Menu').", 'ReportList.php', '', 'bAll', NULL, 0, 0, NULL, 1, 3)";
RunQuery($sSQL, FALSE);

$sSQL = "INSERT INTO `menuconfig_mcf` VALUES (51, 'groups', 'root', 1, ".gettext('Groups').", '', '', 'bAll', NULL, 0, 0, NULL, 1, 8)";
RunQuery($sSQL, FALSE);

$sSQL = "INSERT INTO `menuconfig_mcf` VALUES (52, 'listgroups', 'groups', 0, ".gettext('List Groups').", 'GroupList.php', '', 'bAll', NULL, 0, 0, NULL, 1, 1)";
RunQuery($sSQL, FALSE);

$sSQL = "INSERT INTO `menuconfig_mcf` VALUES (53, 'newgroup', 'groups', 0, ".gettext('Add a New Group').", 'GroupEditor.php', '', 'bManageGroups', NULL, 0, 0, NULL, 1, 2)";
RunQuery($sSQL, FALSE);

$sSQL = "INSERT INTO `menuconfig_mcf` VALUES (54, 'editgroup', 'groups', 0, ".gettext('Edit Group Types').", 'OptionManager.php?mode=grptypes', '', 'bMenuOptions', NULL, 0, 0, NULL, 1, 3)";
RunQuery($sSQL, FALSE);

$sSQL = "INSERT INTO `menuconfig_mcf` VALUES (55, 'assigngroup', 'group', 0, ".gettext('Group Assignment Helper').", 'SelectList.php?mode=groupassign', '', 'bAll', NULL, 0, 0, NULL, 1, 4)";
RunQuery($sSQL, FALSE);

$sSQL = "INSERT INTO `menuconfig_mcf` VALUES (56, 'properties', 'root', 1, ".gettext('Properties').", '', '', 'bAll', NULL, 0, 0, NULL, 1, 9)";
RunQuery($sSQL, FALSE);

$sSQL = "INSERT INTO `menuconfig_mcf` VALUES (57, 'peopleproperty', 'properties', 0, ".gettext('People Properties').", 'PropertyList.php?Type=p', '', 'bAll', NULL, 0, 0, NULL, 1, 1)";
RunQuery($sSQL, FALSE);

$sSQL = "INSERT INTO `menuconfig_mcf` VALUES (58, 'familyproperty', 'properties', 0, ".gettext('Family Properties').", 'PropertyList.php?Type=f', '', 'bAll', NULL, 0, 0, NULL, 1, 2)";
RunQuery($sSQL, FALSE);

$sSQL = "INSERT INTO `menuconfig_mcf` VALUES (59, 'groupproperty', 'properties', 0, ".gettext('Group Properties').", 'PropertyList.php?Type=g', '', 'bAll', NULL, 0, 0, NULL, 1, 3)";
RunQuery($sSQL, FALSE);

$sSQL = "INSERT INTO `menuconfig_mcf` VALUES (60, 'propertytype', 'properties', 0, ".gettext('Property Types').", 'PropertyTypeList.php', '', 'bAll', NULL, 0, 0, NULL, 1, 4)";
RunQuery($sSQL, FALSE);

$sSQL = "INSERT INTO `menuconfig_mcf` VALUES (64, 'help', 'root', 1, ".gettext('Help').", '', '', 'bAll', NULL, 0, 0, NULL, 1, 127)";
RunQuery($sSQL, FALSE);

$sSQL = "INSERT INTO `menuconfig_mcf` VALUES (65, 'about', 'help', 0, ".gettext('About ChurchInfo').", 'Help.php?page=About', '', 'bAll', NULL, 0, 0, NULL, 1, 1)";
RunQuery($sSQL, FALSE);

$sSQL = "INSERT INTO `menuconfig_mcf` VALUES (66, 'wiki', 'help', 0, ".gettext('Wiki Documentation').", 'JumpToWiki.php', '', 'bAll', NULL, 0, 0, NULL, 1, 2)";
RunQuery($sSQL, FALSE);

$sSQL = "INSERT INTO `menuconfig_mcf` VALUES (67, 'helppeople', 'help', 0, ".gettext('People').", 'Help.php?page=People', '', 'bAll', NULL, 0, 0, NULL, 1, 3)";
RunQuery($sSQL, FALSE);

$sSQL = "INSERT INTO `menuconfig_mcf` VALUES (68, 'helpfamily', 'help', 0, ".gettext('Families').", 'Help.php?page=Family', '', 'bAll', NULL, 0, 0, NULL, 1, 4)";
RunQuery($sSQL, FALSE);

$sSQL = "INSERT INTO `menuconfig_mcf` VALUES (69, 'helpgeofeature', 'help', 0, ".gettext('Geographic features').", 'Help.php?page=Geographic', '', 'bAll', NULL, 0, 0, NULL, 1, 5)";
RunQuery($sSQL, FALSE);

$sSQL = "INSERT INTO `menuconfig_mcf` VALUES (70, 'helpgroups', 'help', 0, ".gettext('Groups').", 'Help.php?page=Groups', '', 'bAll', NULL, 0, 0, NULL, 1, 6)";
RunQuery($sSQL, FALSE);

$sSQL = "INSERT INTO `menuconfig_mcf` VALUES (71, 'helpfinance', 'help', 0, ".gettext('Finances').", 'Help.php?page=Finances', '', 'bAll', NULL, 0, 0, NULL, 1, 7)";
RunQuery($sSQL, FALSE);

$sSQL = "INSERT INTO `menuconfig_mcf` VALUES (72, 'helpreports', 'help', 0, ".gettext('Reports').", 'Help.php?page=Reports', '', 'bAll', NULL, 0, 0, NULL, 1, 8)";
RunQuery($sSQL, FALSE);

$sSQL = "INSERT INTO `menuconfig_mcf` VALUES (73, 'helpadmin', 'help', 0, ".gettext('Administration').", 'Help.php?page=Admin', '', 'bAll', NULL, 0, 0, NULL, 1, 9)";
RunQuery($sSQL, FALSE);

$sSQL = "INSERT INTO `menuconfig_mcf` VALUES (74, 'helpcart', 'help', 0, ".gettext('Cart').", 'Help.php?page=Cart', '', 'bAll', NULL, 0, 0, NULL, 1, 10)";
RunQuery($sSQL, FALSE);

$sSQL = "INSERT INTO `menuconfig_mcf` VALUES (75, 'helpproperty', 'help', 0, ".gettext('Properties').", 'Help.php?page=Properties', '', 'bAll', NULL, 0, 0, NULL, 1, 11)";
RunQuery($sSQL, FALSE);

$sSQL = "INSERT INTO `menuconfig_mcf` VALUES (76, 'helpnotes', 'help', 0, ".gettext('Notes').", 'Help.php?page=Notes', '', 'bAll', NULL, 0, 0, NULL, 1, 12)";
RunQuery($sSQL, FALSE);

$sSQL = "INSERT INTO `menuconfig_mcf` VALUES (77, 'helpcustomfields', 'help', 0, ".gettext('Custom Fields').", 'Help.php?page=Custom', '', 'bAll', NULL, 0, 0, NULL, 1, 13)";
RunQuery($sSQL, FALSE);

$sSQL = "INSERT INTO `menuconfig_mcf` VALUES (78, 'helpclassification', 'help', 0, ".gettext('Classifications').", 'Help.php?page=Class', '', 'bAll', NULL, 0, 0, NULL, 1, 14)";
RunQuery($sSQL, FALSE);

$sSQL = "INSERT INTO `menuconfig_mcf` VALUES (79, 'helpcanvass', 'help', 0, ".gettext('Canvass Support').", 'Help.php?page=Canvass', '', 'bAll', NULL, 0, 0, NULL, 1, 15)";
RunQuery($sSQL, FALSE);

$sSQL = "INSERT INTO `menuconfig_mcf` VALUES (80, 'helpevents', 'help', 0, ".gettext('Events').", 'Help.php?page=Events', '', 'bAll', NULL, 0, 0, NULL, 1, 16)";
RunQuery($sSQL, FALSE);

$sSQL = "INSERT INTO `menuconfig_mcf` VALUES (81, 'menusetup', 'admin', '0', ".gettext('Menu Options').", 'MenuSetup.php', '', 'bAdmin', NULL , 0, 0, NULL , 1, 13)";
RunQuery($sSQL, FALSE);

$sSQL = "INSERT INTO `menuconfig_mcf` VALUES (82, 'customfamilyfld', 'admin', 0, ".gettext('Edit Custom Family Fields').", 'FamilyCustomFieldsEditor.php', '', 'bAdmin', NULL, 0, 0, NULL, 1, 3)";
RunQuery($sSQL, FALSE);

$sSQL = "ALTER TABLE `config_cfg` ADD `cfg_category` VARCHAR( 20 ) NULL AFTER `cfg_section` ";
RunQuery($sSQL, FALSE);

$sSQL = "INSERT INTO `config_cfg` VALUES (61, 'iEventPeriodStartHr', '7', 'number', '7', 'Church Event Valid Period Start Hour (0-23)', 'General')";
RunQuery($sSQL, FALSE);

$sSQL = "INSERT INTO `config_cfg` VALUES (62, 'iEventPeriodEndHr', '18', 'number', '18', 'Church Event Valid Period End Hour (0-23, must be greater than iEventStartHr)', 'General')";
RunQuery($sSQL, FALSE);

$sSQL = "INSERT INTO `config_cfg` VALUES (63, 'iEventPeriodIntervalMin', '15', 'number', '15', 'Event Period interval (in minutes)', 'General')";
RunQuery($sSQL, FALSE);

$sSQL = "INSERT INTO `config_cfg` VALUES (64, 'sDistanceUnit', 'miles', 'text', 'miles', 'Unit used to measure distance, miles or km.', 'General', '')";
RunQuery($sSQL, FALSE);

$sSQL = "CREATE TABLE `family_custom` (
  `fam_ID` mediumint(9) NOT NULL default '0',
  PRIMARY KEY  (`fam_ID`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8";
RunQuery($sSQL, FALSE);

$sSQL = "CREATE TABLE `family_custom_master` (
  `fam_custom_Order` smallint(6) NOT NULL default '0',
  `fam_custom_Field` varchar(5) NOT NULL default '',
  `fam_custom_Name` varchar(40) NOT NULL default '',
  `fam_custom_Special` mediumint(8) unsigned default NULL,
  `fam_custom_Side` enum('left','right') NOT NULL default 'left',
  `fam_custom_FieldSec` tinyint(4) NOT NULL default '1',
  `type_ID` tinyint(4) NOT NULL default '0'
) ENGINE=MyISAM DEFAULT CHARSET=utf8";
RunQuery($sSQL, FALSE);

$sSQL = "INSERT INTO `list_lst` VALUES (5, 1, 1, 'bAll')";
RunQuery($sSQL, FALSE);

$sSQL = "INSERT INTO `list_lst` VALUES (5, 2, 2, 'bAdmin')";
RunQuery($sSQL, FALSE);

$sSQL = "INSERT INTO `list_lst` VALUES (5, 3, 3, 'bAddRecords')";
RunQuery($sSQL, FALSE);

$sSQL = "INSERT INTO `list_lst` VALUES (5, 4, 4, 'bEditRecords')";
RunQuery($sSQL, FALSE);

$sSQL = "INSERT INTO `list_lst` VALUES (5, 5, 5, 'bDeleteRecords')";
RunQuery($sSQL, FALSE);

$sSQL = "INSERT INTO `list_lst` VALUES (5, 6, 6, 'bMenuOptions')";
RunQuery($sSQL, FALSE);

$sSQL = "INSERT INTO `list_lst` VALUES (5, 7, 7, 'bManageGroups')";
RunQuery($sSQL, FALSE);

$sSQL = "INSERT INTO `list_lst` VALUES (5, 8, 8, 'bFinance')";
RunQuery($sSQL, FALSE);

$sSQL = "INSERT INTO `list_lst` VALUES (5, 9, 9, 'bNotes')";
RunQuery($sSQL, FALSE);

$sSQL = "INSERT INTO `list_lst` VALUES (5, 10, 10, 'bCommunication')";
RunQuery($sSQL, FALSE);

$sSQL = "INSERT INTO `list_lst` VALUES (5, 11, 11, 'bCanvasser')";
RunQuery($sSQL, FALSE);

$sSQL = "INSERT INTO `userconfig_ucfg` VALUES ('', '10', 'bAddEvent', '1', 'number', 'Allow user to add new event', 'FALSE')";
RunQuery($sSQL, FALSE);

$sSQL = "INSERT INTO `userconfig_ucfg` VALUES ('', '11', 'bSeePrivacyData', '1', 'number', 'Allow user to see member privacy data, e.g. Birth Year, Age.', 'FALSE')";
RunQuery($sSQL, FALSE);

$sSQL = "ALTER TABLE `userconfig_ucfg` ADD `ucfg_cat` VARCHAR( 20 ) NOT NULL AFTER `ucfg_permission` ";
RunQuery($sSQL, FALSE);

$sSQL = "UPDATE `userconfig_ucfg` SET `ucfg_cat` = 'SECURITY' WHERE `userconfig_ucfg`.`ucfg_per_id` =0 AND `userconfig_ucfg`.`ucfg_id` =5 LIMIT 1 ";
RunQuery($sSQL, FALSE);

$sSQL = "UPDATE `userconfig_ucfg` SET `ucfg_cat` = 'SECURITY' WHERE `userconfig_ucfg`.`ucfg_per_id` =0 AND `userconfig_ucfg`.`ucfg_id` =6 LIMIT 1 ";
RunQuery($sSQL, FALSE);

$sSQL = "ALTER TABLE `person_custom_master` ADD `custom_FieldSec` TINYINT( 4 ) NOT NULL AFTER `custom_Side` ;";
RunQuery($sSQL, FALSE);

if (mysql_num_rows(RunQuery("SELECT * FROM person_customer_master"))> 0) {

	$sSQL = "UPDATE TABLE `person_customer_master` SET `custom_FieldSec` = 1";	
	RunQuery($sSQL, FALSE);
}





$sError = mysql_error();

?>
