-- phpMyAdmin SQL Dump
-- version 2.9.2
-- http://www.phpmyadmin.net
-- 
-- Host: localhost
-- Generation Time: Jun 25, 2007 at 04:27 PM
-- Server version: 5.0.33
-- PHP Version: 5.2.1
-- 
-- Database: `churchinfo`

-- Adding MRBS configuration constants into config_cfg database
DELETE FROM `config_cfg` WHERE cfg_id > 2000;

INSERT INTO `config_cfg` VALUES (2001, 'sMBRSDBPrefix', 'mrbs_', 'text', 'mrbs_', 'Prefix for table names.  This will allow multiple installations where only one database is available.', 'General', 'MRBS');
INSERT INTO `config_cfg` VALUES (2002, 'sMRBSAdminName', 'MRBS Admin', 'text', 'MRBS Admin', 'Site Identification: Admin Name', 'General', 'MRBS');
INSERT INTO `config_cfg` VALUES (2003, 'sMRBSAdminEmail', 'MRBSAdmin@yourdomain.org', 'text', 'admin_email@your.org', 'Site Identification: Admin Email', 'General', 'MRBS');
INSERT INTO `config_cfg` VALUES (2004, 'sMRBSPathName', 'mrbs', 'text', 'mrbs', 'Location of MRBS Installation. It is also recommended that you set this if you intend to use email notifications, to ensure that the correct URL is displayed in the notification.', 'General', 'MRBS');
INSERT INTO `config_cfg` VALUES (2005, 'sMRBS_eable_periods', '', 'boolean', '0', 'Use "clock" based intervals (FALSE) or user defined periods (TRUE).  If user-defined periods are used then sMRBS_resolution, sMRBS_morningstarts, sMRBS_eveningends, sMRBS_morningstart_min and sMRBS_eveningends_min are ignored.', 'General', 'MRBS');
INSERT INTO `config_cfg` VALUES (2006, 'sMRBS_resolution', '1800', 'number', '1800', 'Resolution - what blocks can be booked, in seconds.', 'General', 'MRBS');
INSERT INTO `config_cfg` VALUES (2007, 'sMRBS_morningstarts', '7', 'number', '7', 'Start of day, integer hours only, 0-23.\r\nsMRBS_morningstarts must be < sMRBS_eveningends. See also sMRBS_eveningends.', 'General', 'MRBS');
INSERT INTO `config_cfg` VALUES (2008, 'sMRBS_eveningends', '19', 'number', '19', 'endof day, integer hours only, 0-23.\r\nsMRBS_eveningends must be > sMRBS_eveningends. See also sMRBS_morningstarts.', 'General', 'MRBS');
INSERT INTO `config_cfg` VALUES (2009, 'sMRBS_morningstart_min', '0', 'number', '0', 'Minutes to add to sMRBS_morningstarts to get to the real start of the day. Be sure to consider the value of sMRBS_eveningends_min if you change this, so that you do not cause a day to finish before the start of the last period. ', 'General', 'MRBS');
INSERT INTO `config_cfg` VALUES (2010, 'sMRBS_eveningends_min', '0', 'number', '0', 'Minutes to add to sMRBS_eveningends hours to get the real end of the day.', 'General', 'MRBS');
INSERT INTO `config_cfg` VALUES (2011, 'sMRBSPeriods', '', 'text', 'Period&nbsp;&nbsp;1,Period&nbsp;&nbsp;2 or 09:15&nbsp;&nbsp;-&nbsp;&nbsp;09:50,09:55&nbsp;&nbsp;-&nbsp;&nbsp;10:35', 'Define the name or description for your periods in chronological order', 'General', 'MRBS');
INSERT INTO `config_cfg` VALUES (2012, 'sMRBS_weekstarts', '0', 'number', '0', 'Start of week: 0 for Sunday, 1 for Monday, 2 Ffor Tuesday etc.', 'General', 'MRBS');
INSERT INTO `config_cfg` VALUES (2013, 'sMRBS_dateformat', '0', 'number', '0', 'Trailer date format: 0 to show dates as "Jul 10", 1 for "10 Jul"', 'General', 'MRBS');
INSERT INTO `config_cfg` VALUES (2014, 'sMRBS_24hrs_format', '1', 'number', '1', 'Time format in pages. 0 to show dates in 12 hour format, 1 to show them in 24 hour format', 'General', 'MRBS');
INSERT INTO `config_cfg` VALUES (2015, 'sMRBS_default_rpt_days', '60', 'number', '60', 'Default report span in days', 'General', 'MRBS');
INSERT INTO `config_cfg` VALUES (2016, 'sMRBS_search_count', '20', 'number', '20', 'Results per page for searching', 'General', 'MRBS');
INSERT INTO `config_cfg` VALUES (2017, 'sMRBS_refresh_rate', '0', 'number', '0', 'Page refresh time (in seconds). Set to 0 to disable', 'General', 'MRBS');
INSERT INTO `config_cfg` VALUES (2018, 'sMRBS_area_list_fmt', 'list', 'text', 'list', 'should areas be shown as a list or a drop-down select box? (list / select)', 'General', 'MRBS');
INSERT INTO `config_cfg` VALUES (2019, 'sMRBS_mon_v_entries_dtl', 'both', 'text', 'both', 'Entries in monthly view can be shown as start/end slot, brief description or\r\nboth. Set to "description" for brief description, "slot" for time slot and "both" for both. Default is "both", but 6 entries per day are shown instead of 12.\r\n', 'General', 'MRBS');
INSERT INTO `config_cfg` VALUES (2020, 'bMRBSViewWeekNumber', '', 'boolean', '0', 'To view weeks in the bottom (trailer.inc) as week numbers (42) instead of \'first day of the week\' (13 Oct), set this to TRUE', 'General', 'MRBS');
INSERT INTO `config_cfg` VALUES (2021, 'bMRBSTimesRightSide', '', 'boolean', '0', 'To display times on right side in day and week view, set to TRUE.', 'General', 'MRBS');
INSERT INTO `config_cfg` VALUES (2022, 'bMRBSJavascriptCursor', '1', 'boolean', '1', 'Control the active cursor in day/week/month views', 'General', 'MRBS');
INSERT INTO `config_cfg` VALUES (2023, 'bMRBSShowPlusLink', '1', 'boolean', '1', 'Always show the (+) link', 'General', 'MRBS');
INSERT INTO `config_cfg` VALUES (2024, 'sMRBSHighlight_Method', 'hybrid', 'text', 'hybrid', '"bgcolor", "class", or "hybrid"', 'General', 'MRBS');
INSERT INTO `config_cfg` VALUES (2025, 'sMRBSDefaultView', 'day', 'text', 'day', 'Define default starting view (month, week or day)', 'General', 'MRBS');
INSERT INTO `config_cfg` VALUES (2026, 'bMRBSMailAdminOnBooking', '', 'boolean', '0', 'Set to TRUE if you want to be notified when entries are booked. Default is FALSE', 'General', 'MRBS');
INSERT INTO `config_cfg` VALUES (2027, 'bMRBSMailAreaAdminOnBooking', '', 'boolean', '0', 'Set to TRUE if you want AREA ADMIN to be notified when entries are booked. Default is FALSE. Area admin emails are set in room_area admin page.', 'General', 'MRBS');
INSERT INTO `config_cfg` VALUES (2028, 'bMRBSMailRoomAdminOnBooking', '', 'boolean', '0', 'Set to TRUE if you want ROOM ADMIN to be notified when entries are booked. Default is FALSE. Room admin emails are set in room_area admin page.', 'General', 'MRBS');
INSERT INTO `config_cfg` VALUES (2029, 'bMRBSMailAdminOnDelete', '', 'boolean', '0', 'Set to TRUE if you want ADMIN to be notified when entries are deleted. Email will be sent to mrbs admin, area admin and room admin as per above settings, as well as to booker if bMRBSMailAdminAll is TRUE (see below).', 'General', 'MRBS');
INSERT INTO `config_cfg` VALUES (2030, 'bMRBSMailAdminAll', '', 'boolean', '0', 'Set to TRUE if you want to be notified on every change (i.e, on new entries) but also each time they are edited. Default is FALSE (only new entries)', 'General', 'MRBS');
INSERT INTO `config_cfg` VALUES (2031, 'bMRBSMailDetails', '', 'boolean', '0', 'Set to TRUE is you want to show entry details in email, otherwise only a link to view_entry is provided. Irrelevant for deleted entries. Default is FALSE.', 'General', 'MRBS');
INSERT INTO `config_cfg` VALUES (2032, 'bMRBSMailBooker', '', 'boolean', '0', 'Set to TRUE if you want BOOKER to receive a copy of his entries as well any changes (depends of MAIL_ADMIN_ALL, see below). Default is FALSE. To know how to set mrbs to send emails to users/bookers, see INSTALL.', 'General', 'MRBS');
INSERT INTO `config_cfg` VALUES (2033, 'sMRBSMailDomain', '', 'text', '', 'If MAIL_BOOKER is set to TRUE (see above) and you use an authentication scheme other than \'auth_db\', you need to provide the mail domain that will be appended to the username to produce a valid email address (ie. "@domain.com").', 'General', 'MRBS');
INSERT INTO `config_cfg` VALUES (2034, 'sMRBSMailUserSfx', '', 'text', '', 'If you use MAIL_DOMAIN above and username returned by mrbs contains extra strings appended like domain name (\'username.domain\'), you need to provide this extra string here so that it will be removed from the username.', 'General', 'MRBS');
INSERT INTO `config_cfg` VALUES (2035, 'sMRBSAdminBackend', 'smtp', 'text', 'smtp', 'Set the name of the Backend used to transport your mails. Either "smtp" or "sendmail". Default is \'smtp\'. See INSTALL for more details.', 'General', 'MRBS');
INSERT INTO `config_cfg` VALUES (2036, 'sMRBSSendMailPath', '/usr/bin/sendmail', 'text', '/usr/bin/sendmail', 'Set the path of the Sendmail program (only used with "sendmail" backend).', 'General', 'MRBS');
INSERT INTO `config_cfg` VALUES (2037, 'sMRBSSendMailArgs', '', 'text', '', 'Set additional Sendmail parameters (only used with "sendmail" backend).  (example "-t -i").', 'General', 'MRBS');
INSERT INTO `config_cfg` VALUES (2038, 'sRMBSLanguage', 'en', 'text', 'en', 'Set the language used for emails (choose an available lang.* file)', 'General', 'MRBS');
INSERT INTO `config_cfg` VALUES (2039, 'sRMBSEmailRecipient', '', 'text', '', 'Set the recipient email. Default is sMRBSAdminEmail defined above. Separate addresses with \',\'', 'General', 'MRBS');
INSERT INTO `config_cfg` VALUES (2040, 'sRMBSEmailCCList', '', 'text', '', 'Set email address of the Carbon Copy field. Default is \'\'. Separate addresses with \',\'', 'General', 'MRBS');
INSERT INTO `config_cfg` VALUES (2041, 'sRMBSEmailSubject', 'Entry added/changed for (your company)', 'texxt', 'Entry added/changed for (your company)', 'Set the content of the Subject field for added/changed entries.', 'General', 'MRBS');
INSERT INTO `config_cfg` VALUES (2042, 'sRMBSEmailDeleteSubject', 'Entry deleted for (your company)', 'text', 'Entry deleted for (your company)', 'Set the content of the Subject field for deleted fields.', 'General', 'MRBS');
INSERT INTO `config_cfg` VALUES (2043, 'sRMBSEntryAddEmailContent', 'A new entry has been booked, here are the details:', 'text', 'A new entry has been booked, here are the details:', 'Set the content of the message when a new entry is booked. What you type here will be added at the top of the message body.', 'General', 'MRBS');
INSERT INTO `config_cfg` VALUES (2044, 'sRMBSEntryChangedEmailContent', 'An entry has been modified, here are the details:', 'text', 'An entry has been modified, here are the details:', 'Set the content of the message when an entry is modified. What you type here will be added at the top of the message body.', 'General', 'MRBS');
INSERT INTO `config_cfg` VALUES (2045, 'sRMBSEntryDeleteEmailContent', 'An entry has been deleted, here are the details:', 'text', 'An entry has been deleted, here are the details:', 'Set the content of the message when an entry is deleted. What you type here will be added at the top of the message body.', 'General', 'MRBS');

--
-- Create MRBS tables:
CREATE TABLE mrbs_area
(
  id               int NOT NULL auto_increment,
  area_name        varchar(30),
  area_admin_email text,

  PRIMARY KEY (id)
);

CREATE TABLE mrbs_room
(
  id               int NOT NULL auto_increment,
  area_id          int DEFAULT '0' NOT NULL,
  room_name        varchar(25) DEFAULT '' NOT NULL,
  description      varchar(60),
  capacity         int DEFAULT '0' NOT NULL,
  room_admin_email text,

  PRIMARY KEY (id)
);

CREATE TABLE mrbs_entry
(
  id          int NOT NULL auto_increment,
  start_time  int DEFAULT '0' NOT NULL,
  end_time    int DEFAULT '0' NOT NULL,
  entry_type  int DEFAULT '0' NOT NULL,
  repeat_id   int DEFAULT '0' NOT NULL,
  room_id     int DEFAULT '1' NOT NULL,
  timestamp   timestamp,
  create_by   varchar(80) DEFAULT '' NOT NULL,
  name        varchar(80) DEFAULT '' NOT NULL,
  type        char DEFAULT 'E' NOT NULL,
  description text,

  PRIMARY KEY (id),
  KEY idxStartTime (start_time),
  KEY idxEndTime   (end_time)
);

CREATE TABLE mrbs_repeat
(
  id          int NOT NULL auto_increment,
  start_time  int DEFAULT '0' NOT NULL,
  end_time    int DEFAULT '0' NOT NULL,
  rep_type    int DEFAULT '0' NOT NULL,
  end_date    int DEFAULT '0' NOT NULL,
  rep_opt     varchar(32) DEFAULT '' NOT NULL,
  room_id     int DEFAULT '1' NOT NULL,
  timestamp   timestamp,
  create_by   varchar(80) DEFAULT '' NOT NULL,
  name        varchar(80) DEFAULT '' NOT NULL,
  type        char DEFAULT 'E' NOT NULL,
  description text,
  rep_num_weeks smallint NULL,
  
  PRIMARY KEY (id)
);

--
-- Add Menu entries
INSERT INTO `menuconfig_mcf` VALUES (61, 'booking', 'root', 1, 'Booking', '', 'Resources Reservation', 'bAll', NULL, 0, 0, NULL, 1, 10);
INSERT INTO `menuconfig_mcf` VALUES (62, 'overview', 'booking', 0, 'Reservation', 'mrbs/index.php', '', 'bAll', NULL, 0, 0, NULL, 1, 1);
INSERT INTO `menuconfig_mcf` VALUES (63, 'bookingreport', 'booking', 0, 'Reports', 'mrbs/report.php', '', 'bAll', NULL, 0, 0, NULL, 1, 2);
INSERT INTO `menuconfig_mcf` VALUES (84, 'MRBSSeeting', 'admin', 0, 'MRBS Seeting', 'SettingsGeneral.php?Cat=MRBS', '', 'bAdmin', NULL, 0, 0, NULL, 1, 14);