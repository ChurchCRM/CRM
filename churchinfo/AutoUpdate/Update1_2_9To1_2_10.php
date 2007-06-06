<?php
/*******************************************************************************
*
*  filename    : Update1_2_8To1_2_9.php
*  description : Update MySQL database from 1.2.8 To 1.2.9
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

$sSQL = "INSERT INTO `menuconfig_mcf` VALUES (1, 'ROOT', '', 1, 'Main', '', '', 'bAll', NULL, 0, 0, NULL, 1, 0)";
RunQuery($sSQL, FALSE);

$sSQL = "INSERT INTO `menuconfig_mcf` VALUES (2, 'main', 'root', 1, 'Main', '', '', 'bAll', NULL, 0, 0, NULL, 1, 1)";
RunQuery($sSQL, FALSE);

$sSQL = "INSERT INTO `menuconfig_mcf` VALUES (3, 'logoff', 'main', 0, 'Log Off', 'Default.php?Logoff=True', '', 'bAll', NULL, 0, 0, NULL, 1, 1)";
RunQuery($sSQL, FALSE);

$sSQL = "INSERT INTO `menuconfig_mcf` VALUES (4, 'chgpassword', 'main', 0, 'Change My Password', 'UserPasswordChange.php', '', 'bAll', NULL, 0, 0, NULL, 1, 2)";
RunQuery($sSQL, FALSE);

$sSQL = "INSERT INTO `menuconfig_mcf` VALUES (5, 'chgsetting', 'main', 0, 'Change My Settings', 'SettingsIndividual.php', '', 'bAll', NULL, 0, 0, NULL, 1, 0)";
RunQuery($sSQL, FALSE);

$sSQL = "INSERT INTO `menuconfig_mcf` VALUES (6, 'admin', 'root', 1, 'Admin', '', '', 'bAdmin', NULL, 0, 0, NULL, 1, 2)";
RunQuery($sSQL, FALSE);

$sSQL = "INSERT INTO `menuconfig_mcf` VALUES (7, 'editusers', 'admin', 0, 'Edit Users', 'UserList.php', '', 'bAdmin', NULL, 0, 0, NULL, 1, 1)";
RunQuery($sSQL, FALSE);

$sSQL = "INSERT INTO `menuconfig_mcf` VALUES (8, 'addnewuser', 'admin', 0, 'Add New User', 'UserEditor.php', '', 'bAdmin', NULL, 0, 0, NULL, 1, 2)";
RunQuery($sSQL, FALSE);

$sSQL = "INSERT INTO `menuconfig_mcf` VALUES (9, 'custompersonfld', 'admin', 0, 'Edit Custom Person Fields', 'PersonCustomFieldsEditor.php', '', 'bAdmin', NULL, 0, 0, NULL, 1, 3)";
RunQuery($sSQL, FALSE);

$sSQL = "INSERT INTO `menuconfig_mcf` VALUES (10, 'donationfund', 'admin', 0, 'Edit Donation Funds', 'DonationFundEditor.php', '', 'bAdmin', NULL, 0, 0, NULL, 1, 4)";
RunQuery($sSQL, FALSE);

$sSQL = "INSERT INTO `menuconfig_mcf` VALUES (11, 'dbbackup', 'admin', 0, 'Backup Database', 'BackupDatabase.php', '', 'bAdmin', NULL, 0, 0, NULL, 1, 5)";
RunQuery($sSQL, FALSE);

$sSQL = "INSERT INTO `menuconfig_mcf` VALUES (12, 'cvsimport', 'admin', 0, 'CSV Import', 'CSVImport.php', '', 'bAdmin', NULL, 0, 0, NULL, 1, 6)";
RunQuery($sSQL, FALSE);

$sSQL = "INSERT INTO `menuconfig_mcf` VALUES (13, 'accessreport', 'admin', 0, 'Access report', 'AccessReport.php', '', 'bAdmin', NULL, 0, 0, NULL, 1, 7)";
RunQuery($sSQL, FALSE);

$sSQL = "INSERT INTO `menuconfig_mcf` VALUES (14, 'generalsetting', 'admin', 0, 'Edit General Settings', 'SettingsGeneral.php', '', 'bAdmin', NULL, 0, 0, NULL, 1, 8)";
RunQuery($sSQL, FALSE);

$sSQL = "INSERT INTO `menuconfig_mcf` VALUES (15, 'reportsetting', 'admin', 0, 'Edit Report Settings', 'SettingsReport.php', '', 'bAdmin', NULL, 0, 0, NULL, 1, 9)";
RunQuery($sSQL, FALSE);

$sSQL = "INSERT INTO `menuconfig_mcf` VALUES (16, 'userdefault', 'admin', 0, 'Edit User Default Settings', 'SettingsUser.php', '', 'bAdmin', NULL, 0, 0, NULL, 1, 10)";
RunQuery($sSQL, FALSE);

$sSQL = "INSERT INTO `menuconfig_mcf` VALUES (17, 'envelopmgr', 'admin', 0, 'Envelope Manager', 'ManageEnvelopes.php', '', 'bAdmin', NULL, 0, 0, NULL, 1, 11)";
RunQuery($sSQL, FALSE);

$sSQL = "INSERT INTO `menuconfig_mcf` VALUES (18, 'register', 'admin', 0, 'Please select this option to register ChurchInfo after configuring.', 'Register.php', '', 'bAdmin', NULL, 0, 0, NULL, 1, 12)";
RunQuery($sSQL, FALSE);

$sSQL = "INSERT INTO `menuconfig_mcf` VALUES (19, 'people', 'root', 1, 'People/Families', '', 'People/Families', 'bAll', NULL, 0, 0, NULL, 1, 3)";
RunQuery($sSQL, FALSE);

$sSQL = "INSERT INTO `menuconfig_mcf` VALUES (20, 'newperson', 'people', 0, 'Add New Person', 'PersonEditor.php', '', 'bAddRecords', NULL, 0, 0, NULL, 1, 1)";
RunQuery($sSQL, FALSE);

$sSQL = "INSERT INTO `menuconfig_mcf` VALUES (21, 'viewperson', 'people', 0, 'View All Persons', 'SelectList.php?mode=person', '', 'bAll', NULL, 0, 0, NULL, 1, 2)";
RunQuery($sSQL, FALSE);

$sSQL = "INSERT INTO `menuconfig_mcf` VALUES (22, 'classes', 'people', 0, 'Classification Manager', 'OptionManager.php?mode=classes', '', 'bMenuOptions', NULL, 0, 0, NULL, 1, 3)";
RunQuery($sSQL, FALSE);

$sSQL = "INSERT INTO `menuconfig_mcf` VALUES (23, 'separator1', 'people', 0, '---------------------------', '', '', 'bAll', NULL, 0, 0, NULL, 1, 4)";
RunQuery($sSQL, FALSE);

$sSQL = "INSERT INTO `menuconfig_mcf` VALUES (24, 'volunteeropportunity', 'people', 0, 'Edit volunteer opportunities', 'VolunteerOpportunityEditor.php', '', 'bAll', NULL, 0, 0, NULL, 1, 5)";
RunQuery($sSQL, FALSE);

$sSQL = "INSERT INTO `menuconfig_mcf` VALUES (25, 'separator2', 'people', 0, '---------------------------', '', '', 'bAll', NULL, 0, 0, NULL, 1, 6)";
RunQuery($sSQL, FALSE);

$sSQL = "INSERT INTO `menuconfig_mcf` VALUES (26, 'newfamily', 'people', 0, 'Add New Family', 'FamilyEditor.php', '', 'bAddRecords', NULL, 0, 0, NULL, 1, 7)";
RunQuery($sSQL, FALSE);

$sSQL = "INSERT INTO `menuconfig_mcf` VALUES (27, 'viewfamily', 'people', 0, 'View All Families', 'SelectList.php?mode=family', '', 'bAll', NULL, 0, 0, NULL, 1, 8)";
RunQuery($sSQL, FALSE);

$sSQL = "INSERT INTO `menuconfig_mcf` VALUES (28, 'familygeotools', 'people', 0, 'Family Geographic Utilties', 'GeoPage.php', '', 'bAll', NULL, 0, 0, NULL, 1, 9)";
RunQuery($sSQL, FALSE);

$sSQL = "INSERT INTO `menuconfig_mcf` VALUES (29, 'familymap', 'people', 0, 'Family Map', 'MapUsingGoogle.php?GroupID=-1', '', 'bAll', NULL, 0, 0, NULL, 1, 10)";
RunQuery($sSQL, FALSE);

$sSQL = "INSERT INTO `menuconfig_mcf` VALUES (30, 'rolemanager', 'people', 0, 'Family Roles Manager', 'OptionManager.php?mode=famroles', '', 'bMenuOptions', NULL, 0, 0, NULL, 1, 11)";
RunQuery($sSQL, FALSE);

$sSQL = "INSERT INTO `menuconfig_mcf` VALUES (31, 'events', 'root', 1, 'Events', '', 'Events', 'bAll', NULL, 0, 0, NULL, 1, 4)";
RunQuery($sSQL, FALSE);

$sSQL = "INSERT INTO `menuconfig_mcf` VALUES (32, 'listevent', 'events', 0, 'List Church Events', 'ListEvents.php', 'List Church Events', 'bAll', NULL, 0, 0, NULL, 1, 1)";
RunQuery($sSQL, FALSE);

$sSQL = "INSERT INTO `menuconfig_mcf` VALUES (33, 'addevent', 'events', 0, 'Add Church Event', 'EventNames.php', 'Add Church Event', 'bAll', NULL, 0, 0, NULL, 1, 2)";
RunQuery($sSQL, FALSE);

$sSQL = "INSERT INTO `menuconfig_mcf` VALUES (34, 'eventype', 'events', 0, 'List Event Types', 'EventNames.php', '', 'bAdmin', NULL, 0, 0, NULL, 1, 3)";
RunQuery($sSQL, FALSE);

$sSQL = "INSERT INTO `menuconfig_mcf` VALUES (35, 'deposit', 'root', 1, 'Deposit', '', '', 'bFinance', NULL, 0, 0, NULL, 1, 5)";
RunQuery($sSQL, FALSE);

$sSQL = "INSERT INTO `menuconfig_mcf` VALUES (36, 'newdeposit', 'deposit', 0, 'Create New Deposit', 'DepositSlipEditor.php?DepositType=Bank', '', 'bFinance', NULL, 0, 0, NULL, 1, 1)";
RunQuery($sSQL, FALSE);

$sSQL = "INSERT INTO `menuconfig_mcf` VALUES (37, 'viewdeposit', 'deposit', 0, 'View All Deposits', 'FindDepositSlip.php', '', 'bFinance', NULL, 0, 0, NULL, 1, 2)";
RunQuery($sSQL, FALSE);

$sSQL = "INSERT INTO `menuconfig_mcf` VALUES (38, 'depositreport', 'deposit', 0, 'Deposit Reports', 'FinancialReports.php', '', 'bFinance', NULL, 0, 0, NULL, 1, 3)";
RunQuery($sSQL, FALSE);

$sSQL = "INSERT INTO `menuconfig_mcf` VALUES (39, 'separator3', 'deposit', 0, '---------------------------', '', '', 'bFinance', NULL, 0, 0, NULL, 1, 4)";
RunQuery($sSQL, FALSE);

$sSQL = "INSERT INTO `menuconfig_mcf` VALUES (40, 'depositslip', 'deposit', 0, 'Edit Deposit Slip', 'DepositSlipEditor.php', '', 'bFinance', 'iCurrentDeposit', 1, 1, 'DepositSlipID', 1, 5)";
RunQuery($sSQL, FALSE);

$sSQL = "INSERT INTO `menuconfig_mcf` VALUES (41, 'cart', 'root', 1, 'Cart', '', '', 'bAll', NULL, 0, 0, NULL, 1, 6)";
RunQuery($sSQL, FALSE);

$sSQL = "INSERT INTO `menuconfig_mcf` VALUES (42, 'viewcart', 'cart', 0, 'List Cart Items', 'CartView.php', '', 'bAll', NULL, 0, 0, NULL, 1, 1)";
RunQuery($sSQL, FALSE);

$sSQL = "INSERT INTO `menuconfig_mcf` VALUES (43, 'emptycart', 'cart', 0, 'Empty Cart', 'CartView.php?Action=EmptyCart', '', 'bAll', NULL, 0, 0, NULL, 1, 2)";
RunQuery($sSQL, FALSE);

$sSQL = "INSERT INTO `menuconfig_mcf` VALUES (44, 'carttogroup', 'cart', 0, 'Empty Cart to Group', 'CartToGroup.php', '', 'bManageGroups', NULL, 0, 0, NULL, 1, 3)";
RunQuery($sSQL, FALSE);

$sSQL = "INSERT INTO `menuconfig_mcf` VALUES (45, 'carttofamily', 'cart', 0, 'Empty Cart to Family', 'CartToFamily.php', '', 'bAddRecords', NULL, 0, 0, NULL, 1, 4)";
RunQuery($sSQL, FALSE);

$sSQL = "INSERT INTO `menuconfig_mcf` VALUES (46, 'carttoevent', 'cart', 0, 'Empty Cart to Event', 'CartToEvent.php', 'Empty Cart contents to Event', 'bAll', NULL, 0, 0, NULL, 1, 5)";
RunQuery($sSQL, FALSE);

$sSQL = "INSERT INTO `menuconfig_mcf` VALUES (47, 'report', 'root', 1, 'Data/Reports', '', '', 'bAll', NULL, 0, 0, NULL, 1, 7)";
RunQuery($sSQL, FALSE);

$sSQL = "INSERT INTO `menuconfig_mcf` VALUES (48, 'cvsexport', 'report', 0, 'CSV Export Records', 'CSVExport.php', '', 'bAll', NULL, 0, 0, NULL, 1, 1)";
RunQuery($sSQL, FALSE);

$sSQL = "INSERT INTO `menuconfig_mcf` VALUES (49, 'querymenu', 'report', 0, 'Query Menu', 'QueryList.php', '', 'bAll', NULL, 0, 0, NULL, 1, 2)";
RunQuery($sSQL, FALSE);

$sSQL = "INSERT INTO `menuconfig_mcf` VALUES (50, 'reportmenu', 'report', 0, 'Reports Menu', 'ReportList.php', '', 'bAll', NULL, 0, 0, NULL, 1, 3)";
RunQuery($sSQL, FALSE);

$sSQL = "INSERT INTO `menuconfig_mcf` VALUES (51, 'groups', 'root', 1, 'Groups', '', '', 'bAll', NULL, 0, 0, NULL, 1, 8)";
RunQuery($sSQL, FALSE);

$sSQL = "INSERT INTO `menuconfig_mcf` VALUES (52, 'listgroups', 'groups', 0, 'List Groups', 'GroupList.php', '', 'bAll', NULL, 0, 0, NULL, 1, 1)";
RunQuery($sSQL, FALSE);

$sSQL = "INSERT INTO `menuconfig_mcf` VALUES (53, 'newgroup', 'groups', 0, 'Add a New Group', 'GroupEditor.php', '', 'bManageGroups', NULL, 0, 0, NULL, 1, 2)";
RunQuery($sSQL, FALSE);

$sSQL = "INSERT INTO `menuconfig_mcf` VALUES (54, 'editgroup', 'groups', 0, 'Edit Group Types', 'OptionManager.php?mode=grptypes', '', 'bMenuOptions', NULL, 0, 0, NULL, 1, 3)";
RunQuery($sSQL, FALSE);

$sSQL = "INSERT INTO `menuconfig_mcf` VALUES (55, 'assigngroup', 'group', 0, 'Group Assignment Helper', 'SelectList.php?mode=groupassign', '', 'bAll', NULL, 0, 0, NULL, 1, 4)";
RunQuery($sSQL, FALSE);

$sSQL = "INSERT INTO `menuconfig_mcf` VALUES (56, 'properties', 'root', 1, 'Properties', '', '', 'bAll', NULL, 0, 0, NULL, 1, 9)";
RunQuery($sSQL, FALSE);

$sSQL = "INSERT INTO `menuconfig_mcf` VALUES (57, 'peopleproperty', 'properties', 0, 'People Properties', 'PropertyList.php?Type=p', '', 'bAll', NULL, 0, 0, NULL, 1, 1)";
RunQuery($sSQL, FALSE);

$sSQL = "INSERT INTO `menuconfig_mcf` VALUES (58, 'familyproperty', 'properties', 0, 'Family Properties', 'PropertyList.php?Type=f', '', 'bAll', NULL, 0, 0, NULL, 1, 2)";
RunQuery($sSQL, FALSE);

$sSQL = "INSERT INTO `menuconfig_mcf` VALUES (59, 'groupproperty', 'properties', 0, 'Group Properties', 'PropertyList.php?Type=g', '', 'bAll', NULL, 0, 0, NULL, 1, 3)";
RunQuery($sSQL, FALSE);

$sSQL = "INSERT INTO `menuconfig_mcf` VALUES (60, 'propertytype', 'properties', 0, 'Property Types', 'PropertyTypeList.php', '', 'bAll', NULL, 0, 0, NULL, 1, 4)";
RunQuery($sSQL, FALSE);

$sSQL = "INSERT INTO `menuconfig_mcf` VALUES (64, 'help', 'root', 1, 'Help', '', '', 'bAll', NULL, 0, 0, NULL, 1, 127)";
RunQuery($sSQL, FALSE);

$sSQL = "INSERT INTO `menuconfig_mcf` VALUES (65, 'about', 'help', 0, 'About ChurchInfo', 'Help.php?page=About', '', 'bAll', NULL, 0, 0, NULL, 1, 1)";
RunQuery($sSQL, FALSE);

$sSQL = "INSERT INTO `menuconfig_mcf` VALUES (66, 'wiki', 'help', 0, 'Wiki Documentation', 'JumpToWiki.php', '', 'bAll', NULL, 0, 0, NULL, 1, 2)";
RunQuery($sSQL, FALSE);

$sSQL = "INSERT INTO `menuconfig_mcf` VALUES (67, 'helppeople', 'help', 0, 'People', 'Help.php?page=People', '', 'bAll', NULL, 0, 0, NULL, 1, 3)";
RunQuery($sSQL, FALSE);

$sSQL = "INSERT INTO `menuconfig_mcf` VALUES (68, 'helpfamily', 'help', 0, 'Families', 'Help.php?page=Family', '', 'bAll', NULL, 0, 0, NULL, 1, 4)";
RunQuery($sSQL, FALSE);

$sSQL = "INSERT INTO `menuconfig_mcf` VALUES (69, 'helpgeofeature', 'help', 0, 'Geographic features', 'Help.php?page=Geographic', '', 'bAll', NULL, 0, 0, NULL, 1, 5)";
RunQuery($sSQL, FALSE);

$sSQL = "INSERT INTO `menuconfig_mcf` VALUES (70, 'helpgroups', 'help', 0, 'Groups', 'Help.php?page=Groups', '', 'bAll', NULL, 0, 0, NULL, 1, 6)";
RunQuery($sSQL, FALSE);

$sSQL = "INSERT INTO `menuconfig_mcf` VALUES (71, 'helpfinance', 'help', 0, 'Finances', 'Help.php?page=Finances', '', 'bAll', NULL, 0, 0, NULL, 1, 7)";
RunQuery($sSQL, FALSE);

$sSQL = "INSERT INTO `menuconfig_mcf` VALUES (72, 'helpreports', 'help', 0, 'Reports', 'Help.php?page=Reports', '', 'bAll', NULL, 0, 0, NULL, 1, 8)";
RunQuery($sSQL, FALSE);

$sSQL = "INSERT INTO `menuconfig_mcf` VALUES (73, 'helpadmin', 'help', 0, 'Administration', 'Help.php?page=Admin', '', 'bAll', NULL, 0, 0, NULL, 1, 9)";
RunQuery($sSQL, FALSE);

$sSQL = "INSERT INTO `menuconfig_mcf` VALUES (74, 'helpcart', 'help', 0, 'Cart', 'Help.php?page=Cart', '', 'bAll', NULL, 0, 0, NULL, 1, 10)";
RunQuery($sSQL, FALSE);

$sSQL = "INSERT INTO `menuconfig_mcf` VALUES (75, 'helpproperty', 'help', 0, 'Properties', 'Help.php?page=Properties', '', 'bAll', NULL, 0, 0, NULL, 1, 11)";
RunQuery($sSQL, FALSE);

$sSQL = "INSERT INTO `menuconfig_mcf` VALUES (76, 'helpnotes', 'help', 0, 'Notes', 'Help.php?page=Notes', '', 'bAll', NULL, 0, 0, NULL, 1, 12)";
RunQuery($sSQL, FALSE);

$sSQL = "INSERT INTO `menuconfig_mcf` VALUES (77, 'helpcustomfields', 'help', 0, 'Custom Fields', 'Help.php?page=Custom', '', 'bAll', NULL, 0, 0, NULL, 1, 13)";
RunQuery($sSQL, FALSE);

$sSQL = "INSERT INTO `menuconfig_mcf` VALUES (78, 'helpclassification', 'help', 0, 'Classifications', 'Help.php?page=Class', '', 'bAll', NULL, 0, 0, NULL, 1, 14)";
RunQuery($sSQL, FALSE);

$sSQL = "INSERT INTO `menuconfig_mcf` VALUES (79, 'helpcanvass', 'help', 0, 'Canvass Support', 'Help.php?page=Canvass', '', 'bAll', NULL, 0, 0, NULL, 1, 15)";
RunQuery($sSQL, FALSE);

$sSQL = "INSERT INTO `menuconfig_mcf` VALUES (80, 'helpevents', 'help', 0, 'Events', 'Help.php?page=Events', '', 'bAll', NULL, 0, 0, NULL, 1, 16)";
RunQuery($sSQL, FALSE);

$sSQL = "INSERT INTO `config_cfg` VALUES (61, 'iEventPeriodStartHr', '7', 'number', '7', 'Church Event Valid Period Start Hour (0-23)', 'General')";
RunQuery($sSQL, FALSE);

$sSQL = "INSERT INTO `config_cfg` VALUES (62, 'iEventPeriodEndHr', '18', 'number', '18', 'Church Event Valid Period End Hour (0-23, must be greater than iEventStartHr)', 'General')";
RunQuery($sSQL, FALSE);

$sSQL = "INSERT INTO `config_cfg` VALUES (63, 'iEventPeriodIntervalMin', '15', 'number', '15', 'Event Period interval (in minutes)', 'General')";
RunQuery($sSQL, FALSE);

$sError = mysql_error();

?>
