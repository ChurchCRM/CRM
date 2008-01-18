--        It sql creates necessary tables and rows for MRBS package integration.
--        Run this if you want to integrate RMBS with Churchinfo.
--        You should have updated to release 2.1.10 to enable databse driven menu definition before
--        you can access to MRBS on the menu.
--

-- *****************************************************************************
--  The following adds control parameters for MRBS plugin. 
-- *****************************************************************************

--  Insert rows to table `config_cfg`
-- 
INSERT INTO `config_cfg` VALUES (2001, 'sMRBS_eable_periods', '', 'boolean', '0', 'Use "clock" based intervals (FALSE) or user defined periods (TRUE).  If user-defined periods are used then sMRBS_resolution, sMRBS_morningstarts, sMRBS_eveningends, sMRBS_morningstart_min and sMRBS_eveningends_min are ignored.', 'General', NULL);
INSERT INTO `config_cfg` VALUES (2002, 'sMRBS_resolution', '1800', 'number', '1800', 'Resolution - what blocks can be booked, in seconds.', 'General', NULL);
INSERT INTO `config_cfg` VALUES (2003, 'sMRBS_morningstarts', '7', 'number', '7', 'Start of day, integer hours only, 0-23.\r\nsMRBS_morningstarts must be < sMRBS_eveningends. See also sMRBS_eveningends.', 'General', NULL);
INSERT INTO `config_cfg` VALUES (2004, 'sMRBS_eveningends', '19', 'number', '19', 'endof day, integer hours only, 0-23.\r\nsMRBS_eveningends must be > sMRBS_eveningends. See also sMRBS_morningstarts.', 'General', NULL);
INSERT INTO `config_cfg` VALUES (2005, 'sMRBS_morningstart_min', '0', 'number', '0', 'Minutes to add to sMRBS_morningstarts to get to the real start of the day. Be sure to consider the value of sMRBS_eveningends_min if you change this, so that you do not cause a day to finish before the start of the last period. ', 'General', NULL);
INSERT INTO `config_cfg` VALUES (2006, 'sMRBS_eveningends_min', '0', 'number', '0', 'Minutes to add to sMRBS_eveningends hours to get the real end of the day.', 'General', NULL);
INSERT INTO `config_cfg` VALUES (2007, 'sMRBS_weekstarts', '0', 'number', '0', 'Start of week: 0 for Sunday, 1 for Monday, 2 Ffor Tuesday etc.', 'General', NULL);
INSERT INTO `config_cfg` VALUES (2008, 'sMRBS_dateformat', '0', 'number', '0', 'Trailer date format: 0 to show dates as "Jul 10", 1 for "10 Jul"', 'General', NULL);
INSERT INTO `config_cfg` VALUES (2009, 'sMRBS_24hrs_format', '1', 'number', '1', 'Time format in pages. 0 to show dates in 12 hour format, 1 to show them in 24 hour format', 'General', NULL);
INSERT INTO `config_cfg` VALUES (2010, 'sMRBS_default_rpt_days', '60', 'number', '60', 'Default report span in days', 'General', NULL);
INSERT INTO `config_cfg` VALUES (2011, 'sMRBS_search_count', '20', 'number', '20', 'Results per page for searching', 'General', NULL);
INSERT INTO `config_cfg` VALUES (2012, 'sMRBS_refresh_rate', '0', 'number', '0', 'Page refresh time (in seconds). Set to 0 to disable', 'General', NULL);
INSERT INTO `config_cfg` VALUES (2013, 'sMRBS_area_list_fmt', 'list', 'text', 'list', 'should areas be shown as a list or a drop-down select box? (list / select)', 'General', NULL);
INSERT INTO `config_cfg` VALUES (2014, 'sMRBS_mon_v_entries_dtl', 'both', 'text', 'both', 'Entries in monthly view can be shown as start/end slot, brief description or\r\nboth. Set to "description" for brief description, "slot" for time slot and "both" for both. Default is "both", but 6 entries per day are shown instead of 12.\r\n', 'General', NULL);

--
-- Insert rows to Table userconfig_ucfg
-- to add access control to mrbs for admin
--

INSERT INTO `userconfig_ucfg` VALUES (0, 8, 'bEditMRBSBooking', '', 'boolean', 'Reserve resources in MRBS system, modify reservations and delete self reservations', 'TRUE', '');
INSERT INTO `userconfig_ucfg` VALUES (0, 9, 'bAddMRBSResource', '', 'boolean', 'Add, modify and delete resources in MRBS, modify other user''s reservations.', 'TRUE', '');

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
-- The following SQL adds Nativation Menu definition information for MRBS
-- *************************************************************************


INSERT INTO `menuconfig_mcf` VALUES (61, 'booking', 'root', 1, 'Booking', '', 'Resources Reservation', 'bAll', NULL, 0, 0, NULL, 1, 10);
INSERT INTO `menuconfig_mcf` VALUES (62, 'overview', 'booking', 0, 'Reservation', 'mrbs/index.php', '', 'bAll', NULL, 0, 0, NULL, 1, 1);
INSERT INTO `menuconfig_mcf` VALUES (63, 'bookingreport', 'booking', 0, 'Reports', 'mrbs/report.php', '', 'bAll', NULL, 0, 0, NULL, 1, 2);
