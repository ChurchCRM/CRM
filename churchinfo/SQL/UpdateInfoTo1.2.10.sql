--        It is highly recommended you backup your MySQL database before executing this
--        script. To backup from the command prompt use the following.
--
-- system> mysqldump -u root -p db_name > filename.sql
--
-- Upon success filename.sql contains all the SQL to rebuild the database db_name.
-- In case you need to restore your backup use the following command.
--
-- system> mysql -u root -p db_name < filename.sql
--
--      The SQL script below will migrate your database from version 1.2.9 to 1.2.10.
--

-- *****************************************************************************
--  The following adds control parameters for MRBS plugin. 
-- *****************************************************************************

--  Insert rows to table `config_cfg`
-- 
INSERT INTO `config_cfg` VALUES (2001, 'sMRBS_eable_periods', '', 'boolean', '0', 'Use "clock" based intervals (FALSE) or user defined periods (TRUE).  If user-defined periods are used then sMRBS_resolution, sMRBS_morningstarts, sMRBS_eveningends, sMRBS_morningstart_min and sMRBS_eveningends_min are ignored.', 'General');
INSERT INTO `config_cfg` VALUES (2002, 'sMRBS_resolution', '1800', 'number', '1800', 'Resolution - what blocks can be booked, in seconds.', 'General');
INSERT INTO `config_cfg` VALUES (2003, 'sMRBS_morningstarts', '7', 'number', '7', 'Start of day, integer hours only, 0-23.\r\nsMRBS_morningstarts must be < sMRBS_eveningends. See also sMRBS_eveningends.', 'General');
INSERT INTO `config_cfg` VALUES (2004, 'sMRBS_eveningends', '19', 'number', '19', 'endof day, integer hours only, 0-23.\r\nsMRBS_eveningends must be > sMRBS_eveningends. See also sMRBS_morningstarts.', 'General');
INSERT INTO `config_cfg` VALUES (2005, 'sMRBS_morningstart_min', '0', 'number', '0', 'Minutes to add to sMRBS_morningstarts to get to the real start of the day. Be sure to consider the value of sMRBS_eveningends_min if you change this, so that you do not cause a day to finish before the start of the last period. ', 'General');
INSERT INTO `config_cfg` VALUES (2006, 'sMRBS_eveningends_min', '0', 'number', '0', 'Minutes to add to sMRBS_eveningends hours to get the real end of the day.', 'General');
INSERT INTO `config_cfg` VALUES (2007, 'sMRBS_weekstarts', '0', 'number', '0', 'Start of week: 0 for Sunday, 1 for Monday, 2 Ffor Tuesday etc.', 'General');
INSERT INTO `config_cfg` VALUES (2008, 'sMRBS_dateformat', '0', 'number', '0', 'Trailer date format: 0 to show dates as "Jul 10", 1 for "10 Jul"', 'General');
INSERT INTO `config_cfg` VALUES (2009, 'sMRBS_24hrs_format', '1', 'number', '1', 'Time format in pages. 0 to show dates in 12 hour format, 1 to show them in 24 hour format', 'General');
INSERT INTO `config_cfg` VALUES (2010, 'sMRBS_default_rpt_days', '60', 'number', '60', 'Default report span in days', 'General');
INSERT INTO `config_cfg` VALUES (2011, 'sMRBS_search_count', '20', 'number', '20', 'Results per page for searching', 'General');
INSERT INTO `config_cfg` VALUES (2012, 'sMRBS_refresh_rate', '0', 'number', '0', 'Page refresh time (in seconds). Set to 0 to disable', 'General');
INSERT INTO `config_cfg` VALUES (2013, 'sMRBS_area_list_fmt', 'list', 'text', 'list', 'should areas be shown as a list or a drop-down select box? (list / select)', 'General');
INSERT INTO `config_cfg` VALUES (2014, 'sMRBS_mon_v_entries_dtl', 'both', 'text', 'both', 'Entries in monthly view can be shown as start/end slot, brief description or\r\nboth. Set to "description" for brief description, "slot" for time slot and "both" for both. Default is "both", but 6 entries per day are shown instead of 12.\r\n', 'General');

--
-- Insert rows to Table userconfig_ucfg
-- to add access control to mrbs for admin
--

INSERT INTO `userconfig_ucfg` VALUES (0, 8, 'bEditMRBSBooking', '', 'boolean', 'Reserve resources in MRBS system, modify reservations and delete self reservations', 'TRUE');
INSERT INTO `userconfig_ucfg` VALUES (0, 9, 'bAddMRBSResource', '', 'boolean', 'Add, modify and delete resources in MRBS, modify other user''s reservations.', 'TRUE');

-- --------------------------------------------------------

-- 
-- Table structure for table `mrbs_area`
-- 

DROP TABLE IF EXISTS `mrbs_area`;
CREATE TABLE `mrbs_area` (
  `id` int(11) NOT NULL auto_increment,
  `area_name` varchar(30) default NULL,
  `area_admin_email` text,
  PRIMARY KEY  (`id`)
) ENGINE=MyISAM  DEFAULT CHARSET=ucs2 AUTO_INCREMENT=1 ;

-- 
-- Table structure for table `mrbs_entry`
-- 
DROP TABLE IF EXISTS `mrbs_entry`;
CREATE TABLE `mrbs_entry` (
  `id` int(11) NOT NULL auto_increment,
  `start_time` int(11) NOT NULL default '0',
  `end_time` int(11) NOT NULL default '0',
  `entry_type` int(11) NOT NULL default '0',
  `repeat_id` int(11) NOT NULL default '0',
  `room_id` int(11) NOT NULL default '1',
  `timestamp` timestamp NOT NULL default CURRENT_TIMESTAMP on update CURRENT_TIMESTAMP,
  `create_by` varchar(80) NOT NULL default '',
  `name` varchar(80) NOT NULL default '',
  `type` char(1) NOT NULL default 'E',
  `description` text,
  PRIMARY KEY  (`id`),
  KEY `idxStartTime` (`start_time`),
  KEY `idxEndTime` (`end_time`)
) ENGINE=MyISAM DEFAULT CHARSET=ucs2 AUTO_INCREMENT=1 ;


-- 
-- Table structure for table `mrbs_repeat`
-- 

DROP TABLE IF EXISTS `mrbs_repeat`;
CREATE TABLE `mrbs_repeat` (
  `id` int(11) NOT NULL auto_increment,
  `start_time` int(11) NOT NULL default '0',
  `end_time` int(11) NOT NULL default '0',
  `rep_type` int(11) NOT NULL default '0',
  `end_date` int(11) NOT NULL default '0',
  `rep_opt` varchar(32) NOT NULL default '',
  `room_id` int(11) NOT NULL default '1',
  `timestamp` timestamp NOT NULL default CURRENT_TIMESTAMP on update CURRENT_TIMESTAMP,
  `create_by` varchar(80) NOT NULL default '',
  `name` varchar(80) NOT NULL default '',
  `type` char(1) NOT NULL default 'E',
  `description` text,
  `rep_num_weeks` smallint(6) default NULL,
  PRIMARY KEY  (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=ucs2 AUTO_INCREMENT=1 ;

-- 
-- Table structure for table `mrbs_room`
-- 

DROP TABLE IF EXISTS `mrbs_room`;
CREATE TABLE `mrbs_room` (
  `id` int(11) NOT NULL auto_increment,
  `area_id` int(11) NOT NULL default '0',
  `room_name` varchar(25) NOT NULL default '',
  `description` varchar(60) default NULL,
  `capacity` int(11) NOT NULL default '0',
  `room_admin_email` text,
  PRIMARY KEY  (`id`)
) ENGINE=MyISAM  DEFAULT CHARSET=ucs2 AUTO_INCREMENT=1 ;

-- *************************************************************************
-- MRBS plug-in database changes end here
-- *************************************************************************

-- *************************************************************************
-- The following SQL adds Nativation Menu definition information
-- *************************************************************************

-- 
-- Table structure for table `menuconfig_mcf`
-- 

CREATE TABLE `menuconfig_mcf` (
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
) ENGINE=MyISAM  DEFAULT CHARSET=utf8 AUTO_INCREMENT=81 ;

-- 
-- Dumping data for table `menuconfig_mcf`
-- 

INSERT INTO `menuconfig_mcf` VALUES (1, 'ROOT', '', 1, 'Main', '', '', 'bAll', NULL, 0, 0, NULL, 1, 0);
INSERT INTO `menuconfig_mcf` VALUES (2, 'main', 'root', 1, 'Main', '', '', 'bAll', NULL, 0, 0, NULL, 1, 1);
INSERT INTO `menuconfig_mcf` VALUES (3, 'logoff', 'main', 0, 'Log Off', 'Default.php?Logoff=True', '', 'bAll', NULL, 0, 0, NULL, 1, 1);
INSERT INTO `menuconfig_mcf` VALUES (4, 'chgpassword', 'main', 0, 'Change My Password', 'UserPasswordChange.php', '', 'bAll', NULL, 0, 0, NULL, 1, 2);
INSERT INTO `menuconfig_mcf` VALUES (5, 'chgsetting', 'main', 0, 'Change My Settings', 'SettingsIndividual.php', '', 'bAll', NULL, 0, 0, NULL, 1, 0);
INSERT INTO `menuconfig_mcf` VALUES (6, 'admin', 'root', 1, 'Admin', '', '', 'bAdmin', NULL, 0, 0, NULL, 1, 2);
INSERT INTO `menuconfig_mcf` VALUES (7, 'editusers', 'admin', 0, 'Edit Users', 'UserList.php', '', 'bAdmin', NULL, 0, 0, NULL, 1, 1);
INSERT INTO `menuconfig_mcf` VALUES (8, 'addnewuser', 'admin', 0, 'Add New User', 'UserEditor.php', '', 'bAdmin', NULL, 0, 0, NULL, 1, 2);
INSERT INTO `menuconfig_mcf` VALUES (9, 'custompersonfld', 'admin', 0, 'Edit Custom Person Fields', 'PersonCustomFieldsEditor.php', '', 'bAdmin', NULL, 0, 0, NULL, 1, 3);
INSERT INTO `menuconfig_mcf` VALUES (10, 'donationfund', 'admin', 0, 'Edit Donation Funds', 'DonationFundEditor.php', '', 'bAdmin', NULL, 0, 0, NULL, 1, 4);
INSERT INTO `menuconfig_mcf` VALUES (11, 'dbbackup', 'admin', 0, 'Backup Database', 'BackupDatabase.php', '', 'bAdmin', NULL, 0, 0, NULL, 1, 5);
INSERT INTO `menuconfig_mcf` VALUES (12, 'cvsimport', 'admin', 0, 'CSV Import', 'CSVImport.php', '', 'bAdmin', NULL, 0, 0, NULL, 1, 6);
INSERT INTO `menuconfig_mcf` VALUES (13, 'accessreport', 'admin', 0, 'Access report', 'AccessReport.php', '', 'bAdmin', NULL, 0, 0, NULL, 1, 7);
INSERT INTO `menuconfig_mcf` VALUES (14, 'generalsetting', 'admin', 0, 'Edit General Settings', 'SettingsGeneral.php', '', 'bAdmin', NULL, 0, 0, NULL, 1, 8);
INSERT INTO `menuconfig_mcf` VALUES (15, 'reportsetting', 'admin', 0, 'Edit Report Settings', 'SettingsReport.php', '', 'bAdmin', NULL, 0, 0, NULL, 1, 9);
INSERT INTO `menuconfig_mcf` VALUES (16, 'userdefault', 'admin', 0, 'Edit User Default Settings', 'SettingsUser.php', '', 'bAdmin', NULL, 0, 0, NULL, 1, 10);
INSERT INTO `menuconfig_mcf` VALUES (17, 'envelopmgr', 'admin', 0, 'Envelope Manager', 'ManageEnvelopes.php', '', 'bAdmin', NULL, 0, 0, NULL, 1, 11);
INSERT INTO `menuconfig_mcf` VALUES (18, 'register', 'admin', 0, 'Please select this option to register ChurchInfo after configuring.', 'Register.php', '', 'bAdmin', NULL, 0, 0, NULL, 1, 12);
INSERT INTO `menuconfig_mcf` VALUES (19, 'people', 'root', 1, 'People/Families', '', 'People/Families', 'bAll', NULL, 0, 0, NULL, 1, 3);
INSERT INTO `menuconfig_mcf` VALUES (20, 'newperson', 'people', 0, 'Add New Person', 'PersonEditor.php', '', 'bAddRecords', NULL, 0, 0, NULL, 1, 1);
INSERT INTO `menuconfig_mcf` VALUES (21, 'viewperson', 'people', 0, 'View All Persons', 'SelectList.php?mode=person', '', 'bAll', NULL, 0, 0, NULL, 1, 2);
INSERT INTO `menuconfig_mcf` VALUES (22, 'classes', 'people', 0, 'Classification Manager', 'OptionManager.php?mode=classes', '', 'bMenuOptions', NULL, 0, 0, NULL, 1, 3);
INSERT INTO `menuconfig_mcf` VALUES (23, 'separator1', 'people', 0, '---------------------------', '', '', 'bAll', NULL, 0, 0, NULL, 1, 4);
INSERT INTO `menuconfig_mcf` VALUES (24, 'volunteeropportunity', 'people', 0, 'Edit volunteer opportunities', 'VolunteerOpportunityEditor.php', '', 'bAll', NULL, 0, 0, NULL, 1, 5);
INSERT INTO `menuconfig_mcf` VALUES (25, 'separator2', 'people', 0, '---------------------------', '', '', 'bAll', NULL, 0, 0, NULL, 1, 6);
INSERT INTO `menuconfig_mcf` VALUES (26, 'newfamily', 'people', 0, 'Add New Family', 'FamilyEditor.php', '', 'bAddRecords', NULL, 0, 0, NULL, 1, 7);
INSERT INTO `menuconfig_mcf` VALUES (27, 'viewfamily', 'people', 0, 'View All Families', 'SelectList.php?mode=family', '', 'bAll', NULL, 0, 0, NULL, 1, 8);
INSERT INTO `menuconfig_mcf` VALUES (28, 'familygeotools', 'people', 0, 'Family Geographic Utilties', 'GeoPage.php', '', 'bAll', NULL, 0, 0, NULL, 1, 9);
INSERT INTO `menuconfig_mcf` VALUES (29, 'familymap', 'people', 0, 'Family Map', 'MapUsingGoogle.php?GroupID=-1', '', 'bAll', NULL, 0, 0, NULL, 1, 10);
INSERT INTO `menuconfig_mcf` VALUES (30, 'rolemanager', 'people', 0, 'Family Roles Manager', 'OptionManager.php?mode=famroles', '', 'bMenuOptions', NULL, 0, 0, NULL, 1, 11);
INSERT INTO `menuconfig_mcf` VALUES (31, 'events', 'root', 1, 'Events', '', 'Events', 'bAll', NULL, 0, 0, NULL, 1, 4);
INSERT INTO `menuconfig_mcf` VALUES (32, 'listevent', 'events', 0, 'List Church Events', 'ListEvents.php', 'List Church Events', 'bAll', NULL, 0, 0, NULL, 1, 1);
INSERT INTO `menuconfig_mcf` VALUES (33, 'addevent', 'events', 0, 'Add Church Event', 'EventNames.php', 'Add Church Event', 'bAll', NULL, 0, 0, NULL, 1, 2);
INSERT INTO `menuconfig_mcf` VALUES (34, 'eventype', 'events', 0, 'List Event Types', 'EventNames.php', '', 'bAdmin', NULL, 0, 0, NULL, 1, 3);
INSERT INTO `menuconfig_mcf` VALUES (35, 'deposit', 'root', 1, 'Deposit', '', '', 'bFinance', NULL, 0, 0, NULL, 1, 5);
INSERT INTO `menuconfig_mcf` VALUES (36, 'newdeposit', 'deposit', 0, 'Create New Deposit', 'DepositSlipEditor.php?DepositType=Bank', '', 'bFinance', NULL, 0, 0, NULL, 1, 1);
INSERT INTO `menuconfig_mcf` VALUES (37, 'viewdeposit', 'deposit', 0, 'View All Deposits', 'FindDepositSlip.php', '', 'bFinance', NULL, 0, 0, NULL, 1, 2);
INSERT INTO `menuconfig_mcf` VALUES (38, 'depositreport', 'deposit', 0, 'Deposit Reports', 'FinancialReports.php', '', 'bFinance', NULL, 0, 0, NULL, 1, 3);
INSERT INTO `menuconfig_mcf` VALUES (39, 'separator3', 'deposit', 0, '---------------------------', '', '', 'bFinance', NULL, 0, 0, NULL, 1, 4);
INSERT INTO `menuconfig_mcf` VALUES (40, 'depositslip', 'deposit', 0, 'Edit Deposit Slip', 'DepositSlipEditor.php', '', 'bFinance', 'iCurrentDeposit', 1, 1, 'DepositSlipID', 1, 5);
INSERT INTO `menuconfig_mcf` VALUES (41, 'cart', 'root', 1, 'Cart', '', '', 'bAll', NULL, 0, 0, NULL, 1, 6);
INSERT INTO `menuconfig_mcf` VALUES (42, 'viewcart', 'cart', 0, 'List Cart Items', 'CartView.php', '', 'bAll', NULL, 0, 0, NULL, 1, 1);
INSERT INTO `menuconfig_mcf` VALUES (43, 'emptycart', 'cart', 0, 'Empty Cart', 'CartView.php?Action=EmptyCart', '', 'bAll', NULL, 0, 0, NULL, 1, 2);
INSERT INTO `menuconfig_mcf` VALUES (44, 'carttogroup', 'cart', 0, 'Empty Cart to Group', 'CartToGroup.php', '', 'bManageGroups', NULL, 0, 0, NULL, 1, 3);
INSERT INTO `menuconfig_mcf` VALUES (45, 'carttofamily', 'cart', 0, 'Empty Cart to Family', 'CartToFamily.php', '', 'bAddRecords', NULL, 0, 0, NULL, 1, 4);
INSERT INTO `menuconfig_mcf` VALUES (46, 'carttoevent', 'cart', 0, 'Empty Cart to Event', 'CartToEvent.php', 'Empty Cart contents to Event', 'bAll', NULL, 0, 0, NULL, 1, 5);
INSERT INTO `menuconfig_mcf` VALUES (47, 'report', 'root', 1, 'Data/Reports', '', '', 'bAll', NULL, 0, 0, NULL, 1, 7);
INSERT INTO `menuconfig_mcf` VALUES (48, 'cvsexport', 'report', 0, 'CSV Export Records', 'CSVExport.php', '', 'bAll', NULL, 0, 0, NULL, 1, 1);
INSERT INTO `menuconfig_mcf` VALUES (49, 'querymenu', 'report', 0, 'Query Menu', 'QueryList.php', '', 'bAll', NULL, 0, 0, NULL, 1, 2);
INSERT INTO `menuconfig_mcf` VALUES (50, 'reportmenu', 'report', 0, 'Reports Menu', 'ReportList.php', '', 'bAll', NULL, 0, 0, NULL, 1, 3);
INSERT INTO `menuconfig_mcf` VALUES (51, 'groups', 'root', 1, 'Groups', '', '', 'bAll', NULL, 0, 0, NULL, 1, 8);
INSERT INTO `menuconfig_mcf` VALUES (52, 'listgroups', 'groups', 0, 'List Groups', 'GroupList.php', '', 'bAll', NULL, 0, 0, NULL, 1, 1);
INSERT INTO `menuconfig_mcf` VALUES (53, 'newgroup', 'groups', 0, 'Add a New Group', 'GroupEditor.php', '', 'bManageGroups', NULL, 0, 0, NULL, 1, 2);
INSERT INTO `menuconfig_mcf` VALUES (54, 'editgroup', 'groups', 0, 'Edit Group Types', 'OptionManager.php?mode=grptypes', '', 'bMenuOptions', NULL, 0, 0, NULL, 1, 3);
INSERT INTO `menuconfig_mcf` VALUES (55, 'assigngroup', 'group', 0, 'Group Assignment Helper', 'SelectList.php?mode=groupassign', '', 'bAll', NULL, 0, 0, NULL, 1, 4);
INSERT INTO `menuconfig_mcf` VALUES (56, 'properties', 'root', 1, 'Properties', '', '', 'bAll', NULL, 0, 0, NULL, 1, 9);
INSERT INTO `menuconfig_mcf` VALUES (57, 'peopleproperty', 'properties', 0, 'People Properties', 'PropertyList.php?Type=p', '', 'bAll', NULL, 0, 0, NULL, 1, 1);
INSERT INTO `menuconfig_mcf` VALUES (58, 'familyproperty', 'properties', 0, 'Family Properties', 'PropertyList.php?Type=f', '', 'bAll', NULL, 0, 0, NULL, 1, 2);
INSERT INTO `menuconfig_mcf` VALUES (59, 'groupproperty', 'properties', 0, 'Group Properties', 'PropertyList.php?Type=g', '', 'bAll', NULL, 0, 0, NULL, 1, 3);
INSERT INTO `menuconfig_mcf` VALUES (60, 'propertytype', 'properties', 0, 'Property Types', 'PropertyTypeList.php', '', 'bAll', NULL, 0, 0, NULL, 1, 4);
INSERT INTO `menuconfig_mcf` VALUES (61, 'booking', 'root', 1, 'Booking', '', 'Resources Reservation', 'bAll', NULL, 0, 0, NULL, 1, 10);
INSERT INTO `menuconfig_mcf` VALUES (62, 'overview', 'booking', 0, 'Reservation', 'mrbs/index.php', '', 'bAll', NULL, 0, 0, NULL, 1, 1);
INSERT INTO `menuconfig_mcf` VALUES (63, 'bookingreport', 'booking', 0, 'Reports', 'mrbs/report.php', '', 'bAll', NULL, 0, 0, NULL, 1, 2);
INSERT INTO `menuconfig_mcf` VALUES (64, 'help', 'root', 1, 'Help', '', '', 'bAll', NULL, 0, 0, NULL, 1, 127);
INSERT INTO `menuconfig_mcf` VALUES (65, 'about', 'help', 0, 'About ChurchInfo', 'Help.php?page=About', '', 'bAll', NULL, 0, 0, NULL, 1, 1);
INSERT INTO `menuconfig_mcf` VALUES (66, 'wiki', 'help', 0, 'Wiki Documentation', 'JumpToWiki.php', '', 'bAll', NULL, 0, 0, NULL, 1, 2);
INSERT INTO `menuconfig_mcf` VALUES (67, 'helppeople', 'help', 0, 'People', 'Help.php?page=People', '', 'bAll', NULL, 0, 0, NULL, 1, 3);
INSERT INTO `menuconfig_mcf` VALUES (68, 'helpfamily', 'help', 0, 'Families', 'Help.php?page=Family', '', 'bAll', NULL, 0, 0, NULL, 1, 4);
INSERT INTO `menuconfig_mcf` VALUES (69, 'helpgeofeature', 'help', 0, 'Geographic features', 'Help.php?page=Geographic', '', 'bAll', NULL, 0, 0, NULL, 1, 5);
INSERT INTO `menuconfig_mcf` VALUES (70, 'helpgroups', 'help', 0, 'Groups', 'Help.php?page=Groups', '', 'bAll', NULL, 0, 0, NULL, 1, 6);
INSERT INTO `menuconfig_mcf` VALUES (71, 'helpfinance', 'help', 0, 'Finances', 'Help.php?page=Finances', '', 'bAll', NULL, 0, 0, NULL, 1, 7);
INSERT INTO `menuconfig_mcf` VALUES (72, 'helpreports', 'help', 0, 'Reports', 'Help.php?page=Reports', '', 'bAll', NULL, 0, 0, NULL, 1, 8);
INSERT INTO `menuconfig_mcf` VALUES (73, 'helpadmin', 'help', 0, 'Administration', 'Help.php?page=Admin', '', 'bAll', NULL, 0, 0, NULL, 1, 9);
INSERT INTO `menuconfig_mcf` VALUES (74, 'helpcart', 'help', 0, 'Cart', 'Help.php?page=Cart', '', 'bAll', NULL, 0, 0, NULL, 1, 10);
INSERT INTO `menuconfig_mcf` VALUES (75, 'helpproperty', 'help', 0, 'Properties', 'Help.php?page=Properties', '', 'bAll', NULL, 0, 0, NULL, 1, 11);
INSERT INTO `menuconfig_mcf` VALUES (76, 'helpnotes', 'help', 0, 'Notes', 'Help.php?page=Notes', '', 'bAll', NULL, 0, 0, NULL, 1, 12);
INSERT INTO `menuconfig_mcf` VALUES (77, 'helpcustomfields', 'help', 0, 'Custom Fields', 'Help.php?page=Custom', '', 'bAll', NULL, 0, 0, NULL, 1, 13);
INSERT INTO `menuconfig_mcf` VALUES (78, 'helpclassification', 'help', 0, 'Classifications', 'Help.php?page=Class', '', 'bAll', NULL, 0, 0, NULL, 1, 14);
INSERT INTO `menuconfig_mcf` VALUES (79, 'helpcanvass', 'help', 0, 'Canvass Support', 'Help.php?page=Canvass', '', 'bAll', NULL, 0, 0, NULL, 1, 15);
INSERT INTO `menuconfig_mcf` VALUES (80, 'helpevents', 'help', 0, 'Events', 'Help.php?page=Events', '', 'bAll', NULL, 0, 0, NULL, 1, 16);

-- *************************************************************************
-- Nativation Menu Definition Control ends here
-- *************************************************************************

-- *************************************************************************
-- The following SQL parameterizes Event time period from hard-coding inside program
-- *************************************************************************
INSERT INTO `config_cfg` VALUES (61, 'iEventPeriodStartHr', '7', 'number', '7', 'Church Event Valid Period Start Hour (0-23)', 'General');
INSERT INTO `config_cfg` VALUES (62, 'iEventPeriodEndHr', '18', 'number', '18', 'Church Event Valid Period End Hour (0-23, must be greater than iEventStartHr)', 'General');
INSERT INTO `config_cfg` VALUES (63, 'iEventPeriodIntervalMin', '15', 'number', '15', 'Event Period interval (in minutes)', 'General');

-- *************************************************************************
-- Event Time Period parameterization change ends here
-- *************************************************************************
