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
--      The SQL script below will migrate your database from version 1.2.6 to 1.2.7.
--      There is no script to go back to 1.2.6.  If you need to roll back to 1.2.6 your
--      best bet is to restore your MySQL backup and install 1.2.6 PHP code.
--
--
-- New table to define Event Count Names 
CREATE TABLE IF NOT EXISTS `event_count_names` (
`count_id` int( 5 ) NOT NULL AUTO_INCREMENT ,
`event_type_id` smallint( 5 ) NOT NULL default '0',
`count_name` varchar( 20 ) NOT NULL default '',
`notes` varchar( 20 ) NOT NULL default '',
UNIQUE KEY `count_id` ( `count_id` ) ,
UNIQUE KEY `event_type_id` ( `event_type_id` , `count_name` )
) TYPE=MyISAM;

-- New table to track Event Counts
CREATE TABLE IF NOT EXISTS `event_counts` (
`event_id` int( 5 ) NOT NULL default '0',
`count_id` int( 5 ) NOT NULL default '0',
`count_name` varchar( 20 ) default NULL ,
`count_count` int( 6 ) default NULL ,
`notes` varchar( 20 ) default NULL ,
PRIMARY KEY ( `event_id` , `count_id` )
) TYPE=MyISAM;

-- Extend the table events_event to include event_type_name column
ALTER TABLE `events_event` 
	ADD COLUMN `event_type_name` varchar(40) NOT NULL default '' 
	AFTER `inactive`;

-- Fill in the new column with data from event_types
UPDATE `events_event`,`event_types` SET events_event.event_type_name=event_types.type_name WHERE events_event.event_type=event_types.type_id;

-- Extend the table event_types
ALTER TABLE `event_types`
  ADD COLUMN `def_start_time` time NOT NULL default '00:00:00' AFTER `type_name`,
  ADD COLUMN `def_recur_type` enum( 'none', 'weekly', 'monthly', 'yearly' ) NOT NULL default 'none' AFTER `def_start_time`,
  ADD COLUMN `def_recur_DOW` enum( 'Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday' ) NOT NULL default 'Sunday' AFTER `def_recur_type`,
  ADD COLUMN `def_recur_DOM` char( 2 ) NOT NULL default '0' AFTER `def_recur_DOW`,
  ADD COLUMN `def_recur_DOY` date NOT NULL default '0000-00-00' AFTER `def_recur_DOM`,
  ADD COLUMN `active` int( 1 ) NOT NULL default '1' AFTER `def_recur_DOY`;

-- New Table to keep track of emails that are ready to be sent
CREATE TABLE IF NOT EXISTS `email_recipient_pending_erp` (
  `erp_id` smallint(5) unsigned NOT NULL DEFAULT '0',
  `erp_usr_id` mediumint(9) unsigned NOT NULL DEFAULT '0',
  `erp_num_attempt` smallint(5) unsigned NOT NULL DEFAULT '0',
  `erp_email_address` varchar(50) NOT NULL DEFAULT ''
) TYPE=MyISAM;

-- New Table to keep track of email subject and text
-- Also keeps a total of how many have been sent and how many
-- still need to be sent ... allows pausing the job and resuming 
-- at a later date
CREATE TABLE IF NOT EXISTS `email_message_pending_emp` (
  `emp_usr_id` mediumint(9) unsigned NOT NULL DEFAULT '0',
  `emp_num_sent` smallint(5) unsigned NOT NULL DEFAULT '0',
  `emp_num_left` smallint(5) unsigned NOT NULL DEFAULT '0',
  `emp_last_sent_addr` varchar(50) NOT NULL DEFAULT '',
  `emp_last_sent_time` datetime NOT NULL DEFAULT '2000-01-01 00:00:00',
  `emp_last_attempt_addr` varchar(50) NOT NULL DEFAULT '',
  `emp_last_attempt_time` datetime NOT NULL DEFAULT '2000-01-01 00:00:00',
  `emp_subject` varchar(80) NOT NULL DEFAULT '',
  `emp_message` text NOT NULL DEFAULT ''
) TYPE=MyISAM;

-- New table to keep track of version information
CREATE TABLE IF NOT EXISTS `version_ver` (
  `ver_ID` mediumint(9) unsigned NOT NULL auto_increment,
  `ver_version` varchar(50) NOT NULL default '',
  `ver_date` datetime default NULL,
  PRIMARY KEY  (`ver_ID`),
  UNIQUE KEY `ver_version` (`ver_version`)
) TYPE=MyISAM;

INSERT IGNORE INTO `version_ver` (`ver_version`, `ver_date`) VALUES ('1.2.7',NOW());

-- New table for user settings and permissions
CREATE TABLE IF NOT EXISTS `userconfig_ucfg` (
  `ucfg_per_id` mediumint(9) unsigned NOT NULL,
  `ucfg_id` int(11) NOT NULL default '0',
  `ucfg_name` varchar(50) NOT NULL default '',
  `ucfg_value` text default NULL,
  `ucfg_type` enum('text','number','date','boolean','textarea') NOT NULL default 'text',
  `ucfg_tooltip` text NOT NULL,
  `ucfg_permission` enum('FALSE','TRUE') NOT NULL default 'FALSE',
  PRIMARY KEY  (`ucfg_per_ID`,`ucfg_id`)
) TYPE=MyISAM;

-- Create temporary table of user id's
DROP TABLE IF EXISTS `user_id_tmp`;
CREATE TABLE IF NOT EXISTS `user_id_tmp` (
  `tmp_user_id` mediumint(9) unsigned NOT NULL,
  PRIMARY KEY (`tmp_user_id`)
) TYPE=MyISAM;

-- Load Temporary table with list of user id's. (add 0 default) and (skip 1 admin)
INSERT IGNORE INTO `user_id_tmp` (`tmp_user_id`) VALUES (0);
INSERT IGNORE INTO `user_id_tmp` (`tmp_user_id`) 
SELECT `usr_per_ID` FROM `user_usr` WHERE `usr_per_ID`>1 ORDER BY `usr_per_ID`;

-- Store sFromEmailAddress and sFromName in variables.
SELECT cfg_value FROM `config_cfg` WHERE `cfg_name`='sFromEmailAddress' INTO @fromaddress;
SELECT cfg_value FROM `config_cfg` WHERE `cfg_name`='sFromName' INTO @fromname;

-- Add default permissions for users
INSERT IGNORE INTO `userconfig_ucfg` (`ucfg_per_id`, `ucfg_id`, `ucfg_name`, `ucfg_value`,
`ucfg_type`, `ucfg_tooltip`, `ucfg_permission`)
SELECT `tmp_user_id`,0,'bEmailMailto','1',
'boolean','User permission to send email via mailto: links','TRUE'
FROM `user_id_tmp` ORDER BY `tmp_user_id`;
INSERT IGNORE INTO `userconfig_ucfg` (`ucfg_per_id`, `ucfg_id`, `ucfg_name`, `ucfg_value`,
`ucfg_type`, `ucfg_tooltip`, `ucfg_permission`)
SELECT `tmp_user_id`,1,'sMailtoDelimiter',',',
'text','Delimiter to separate emails in mailto: links','TRUE'
FROM `user_id_tmp` ORDER BY `tmp_user_id`;
INSERT IGNORE INTO `userconfig_ucfg` (`ucfg_per_id`, `ucfg_id`, `ucfg_name`, `ucfg_value`,
`ucfg_type`, `ucfg_tooltip`, `ucfg_permission`)
SELECT `tmp_user_id`,2,'bSendPHPMail','0',
'boolean','User permission to send email using PHPMailer','FALSE'
FROM `user_id_tmp` ORDER BY `tmp_user_id`;
INSERT IGNORE INTO `userconfig_ucfg` (`ucfg_per_id`, `ucfg_id`, `ucfg_name`, `ucfg_value`,
`ucfg_type`, `ucfg_tooltip`, `ucfg_permission`)
SELECT `tmp_user_id`,3,'sFromEmailAddress',@fromaddress,
'text','Reply email address: PHPMailer','FALSE'
FROM `user_id_tmp` ORDER BY `tmp_user_id`;
INSERT IGNORE INTO `userconfig_ucfg` (`ucfg_per_id`, `ucfg_id`, `ucfg_name`, `ucfg_value`,
`ucfg_type`, `ucfg_tooltip`, `ucfg_permission`)
SELECT `tmp_user_id`,4,'sFromName',@fromname,
'text','Name that appears in From field: PHPMailer','FALSE'
FROM `user_id_tmp` ORDER BY `tmp_user_id`;
INSERT IGNORE INTO `userconfig_ucfg` (`ucfg_per_id`, `ucfg_id`, `ucfg_name`, `ucfg_value`,
`ucfg_type`, `ucfg_tooltip`, `ucfg_permission`)
SELECT `tmp_user_id`,5,'bCreateDirectory','0',
'boolean','User permission to create directories','FALSE'
FROM `user_id_tmp` ORDER BY `tmp_user_id`;
INSERT IGNORE INTO `userconfig_ucfg` (`ucfg_per_id`, `ucfg_id`, `ucfg_name`, `ucfg_value`,
`ucfg_type`, `ucfg_tooltip`, `ucfg_permission`)
SELECT `tmp_user_id`,6,'bExportCSV','0',
'boolean','User permission to export CSV files','FALSE'
FROM `user_id_tmp` ORDER BY `tmp_user_id`;
INSERT IGNORE INTO `userconfig_ucfg` (`ucfg_per_id`, `ucfg_id`, `ucfg_name`, `ucfg_value`,
`ucfg_type`, `ucfg_tooltip`, `ucfg_permission`)
SELECT `tmp_user_id`,7,'bUSAddressVerification','0',
'boolean','User permission to use IST Address Verification','FALSE'
FROM `user_id_tmp` ORDER BY `tmp_user_id`;


-- No longer need temporary table
DROP TABLE IF EXISTS `user_id_tmp`;

-- Add permissions for Admin
INSERT IGNORE INTO `userconfig_ucfg` (ucfg_per_ID, ucfg_id, ucfg_name, ucfg_value,
ucfg_type, ucfg_tooltip, ucfg_permission)
VALUES (1,0,'bEmailMailto','1',
'boolean','User permission to send email via mailto: links','TRUE');
INSERT IGNORE INTO `userconfig_ucfg` (ucfg_per_ID, ucfg_id, ucfg_name, ucfg_value,
ucfg_type, ucfg_tooltip, ucfg_permission)
VALUES (1,1,'sMailtoDelimiter',',',
'text','user permission to send email via mailto: links','TRUE');
INSERT IGNORE INTO `userconfig_ucfg` (ucfg_per_id, ucfg_id, ucfg_name, ucfg_value,
ucfg_type, ucfg_tooltip, ucfg_permission)
VALUES (1,2,'bSendPHPMail','1',
'boolean','User permission to send email using PHPMailer','TRUE');
INSERT IGNORE INTO `userconfig_ucfg` (ucfg_per_id, ucfg_id, ucfg_name, ucfg_value,
ucfg_type, ucfg_tooltip, ucfg_permission)
VALUES (1,3,'sFromEmailAddress',@fromaddress,
'text','Reply email address: PHPMailer','TRUE');
INSERT IGNORE INTO `userconfig_ucfg` (ucfg_per_id, ucfg_id, ucfg_name, ucfg_value,
ucfg_type, ucfg_tooltip, ucfg_permission)
VALUES (1,4,'sFromName',@fromname,
'text','Name that appears in From field: PHPMailer','TRUE');
INSERT IGNORE INTO `userconfig_ucfg` (ucfg_per_id, ucfg_id, ucfg_name, ucfg_value,
ucfg_type, ucfg_tooltip, ucfg_permission)
VALUES (1,5,'bCreateDirectory','1',
'boolean','User permission to create directories','TRUE');
INSERT IGNORE INTO `userconfig_ucfg` (ucfg_per_id, ucfg_id, ucfg_name, ucfg_value,
ucfg_type, ucfg_tooltip, ucfg_permission)
VALUES (1,6,'bExportCSV','1',
'boolean','User permission to export CSV files','TRUE');
INSERT IGNORE INTO `userconfig_ucfg` (ucfg_per_id, ucfg_id, ucfg_name, ucfg_value,
ucfg_type, ucfg_tooltip, ucfg_permission)
VALUES (1,7,'bUSAddressVerification','1',
'boolean','User permission to use IST Address Verification','TRUE');

-- Fix a typo
UPDATE IGNORE `config_cfg` 
SET `cfg_name`='sReminderNoPayments' WHERE `cfg_name`='sReminderNoPlayments';

-- Renumber config values to match those of a fresh install.
-- Helpfull in keeping consistency between upgrades and new installations.
-- 1 thru 1000 is for 'General'
-- 1001 thru 2000 is for 'ChurchInfoReport'
-- 2001 thru 3000 is for future use
--
-- Step 1) Copy current config_cfg table into temporary table
DROP TABLE IF EXISTS `tempconfig_tcfg`;
CREATE TABLE `tempconfig_tcfg` (
  `tcfg_id` int(11) NOT NULL default '0',
  `tcfg_name` varchar(50) NOT NULL default '',
  `tcfg_value` text default NULL,
  `tcfg_type` enum('text','number','date','boolean','textarea') NOT NULL default 'text',
  `tcfg_default` text NOT NULL default '',
  `tcfg_tooltip` text NOT NULL,
  `tcfg_section` varchar(50) NOT NULL default '',
  PRIMARY KEY  (`tcfg_id`),
  UNIQUE KEY `tcfg_name` (`tcfg_name`),
  KEY `tcfg_id` (`tcfg_id`)
) TYPE=MyISAM;

INSERT INTO `tempconfig_tcfg` 
SELECT `cfg_id`,`cfg_name`,`cfg_value`,`cfg_type`,`cfg_default`,`cfg_tooltip`,`cfg_section`
FROM `config_cfg` ORDER BY `cfg_id`;

-- Step 2) Make sure `tempconfig_tcfg` matches `config_cfg` or exit with error
--         This is to make darn sure we can restore `config_cfg`  

-- CHECKSUM TABLE tempconfig_tcfg EXTENDED;
-- CHECKSUM TABLE config_cfg EXTENDED;

-- Step 3) Drop the config table and make a new empty table
DROP TABLE IF EXISTS `config_cfg`;
CREATE TABLE `config_cfg` (
  `cfg_id` int(11) NOT NULL default '0',
  `cfg_name` varchar(50) NOT NULL default '',
  `cfg_value` text default NULL,
  `cfg_type` enum('text','number','date','boolean','textarea') NOT NULL default 'text',
  `cfg_default` text NOT NULL default '',
  `cfg_tooltip` text NOT NULL,
  `cfg_section` varchar(50) NOT NULL default '',
  PRIMARY KEY  (`cfg_id`),
  UNIQUE KEY `cfg_name` (`cfg_name`),
  KEY `cfg_id` (`cfg_id`)
) TYPE=MyISAM;

-- Step 4) Copy data into the config table in the desired order
INSERT INTO `config_cfg`
SELECT 1,`tcfg_name`,`tcfg_value`,`tcfg_type`,`tcfg_default`,`tcfg_tooltip`,`tcfg_section`
FROM `tempconfig_tcfg` WHERE `tcfg_name`='sWEBCALENDARDB';
INSERT INTO `config_cfg`
SELECT 2,`tcfg_name`,`tcfg_value`,`tcfg_type`,`tcfg_default`,`tcfg_tooltip`,`tcfg_section`
FROM `tempconfig_tcfg` WHERE `tcfg_name`='debug';
INSERT INTO `config_cfg`
SELECT 3,`tcfg_name`,`tcfg_value`,`tcfg_type`,`tcfg_default`,`tcfg_tooltip`,`tcfg_section`
FROM `tempconfig_tcfg` WHERE `tcfg_name`='sJPGRAPH_PATH';
INSERT INTO `config_cfg`
SELECT 4,`tcfg_name`,`tcfg_value`,`tcfg_type`,`tcfg_default`,`tcfg_tooltip`,`tcfg_section`
FROM `tempconfig_tcfg` WHERE `tcfg_name`='sFPDF_PATH';
INSERT INTO `config_cfg`
SELECT 5,`tcfg_name`,`tcfg_value`,`tcfg_type`,`tcfg_default`,`tcfg_tooltip`,`tcfg_section`
FROM `tempconfig_tcfg` WHERE `tcfg_name`='sDirClassifications';
INSERT INTO `config_cfg`
SELECT 6,`tcfg_name`,`tcfg_value`,`tcfg_type`,`tcfg_default`,`tcfg_tooltip`,`tcfg_section`
FROM `tempconfig_tcfg` WHERE `tcfg_name`='sDirRoleHead';
INSERT INTO `config_cfg`
SELECT 7,`tcfg_name`,`tcfg_value`,`tcfg_type`,`tcfg_default`,`tcfg_tooltip`,`tcfg_section`
FROM `tempconfig_tcfg` WHERE `tcfg_name`='sDirRoleSpouse';
INSERT INTO `config_cfg`
SELECT 8,`tcfg_name`,`tcfg_value`,`tcfg_type`,`tcfg_default`,`tcfg_tooltip`,`tcfg_section`
FROM `tempconfig_tcfg` WHERE `tcfg_name`='sDirRoleChild';
INSERT INTO `config_cfg`
SELECT 9,`tcfg_name`,`tcfg_value`,`tcfg_type`,`tcfg_default`,`tcfg_tooltip`,`tcfg_section`
FROM `tempconfig_tcfg` WHERE `tcfg_name`='sSessionTimeout';
INSERT INTO `config_cfg`
SELECT 10,`tcfg_name`,`tcfg_value`,`tcfg_type`,`tcfg_default`,`tcfg_tooltip`,`tcfg_section`
FROM `tempconfig_tcfg` WHERE `tcfg_name`='aFinanceQueries';
INSERT INTO `config_cfg`
SELECT 11,`tcfg_name`,`tcfg_value`,`tcfg_type`,`tcfg_default`,`tcfg_tooltip`,`tcfg_section`
FROM `tempconfig_tcfg` WHERE `tcfg_name`='bCSVAdminOnly';
INSERT INTO `config_cfg`
SELECT 12,`tcfg_name`,`tcfg_value`,`tcfg_type`,`tcfg_default`,`tcfg_tooltip`,`tcfg_section`
FROM `tempconfig_tcfg` WHERE `tcfg_name`='sDefault_Pass';
INSERT INTO `config_cfg`
SELECT 13,`tcfg_name`,`tcfg_value`,`tcfg_type`,`tcfg_default`,`tcfg_tooltip`,`tcfg_section`
FROM `tempconfig_tcfg` WHERE `tcfg_name`='sMinPasswordLength';
INSERT INTO `config_cfg`
SELECT 14,`tcfg_name`,`tcfg_value`,`tcfg_type`,`tcfg_default`,`tcfg_tooltip`,`tcfg_section`
FROM `tempconfig_tcfg` WHERE `tcfg_name`='sMinPasswordChange';
INSERT INTO `config_cfg`
SELECT 15,`tcfg_name`,`tcfg_value`,`tcfg_type`,`tcfg_default`,`tcfg_tooltip`,`tcfg_section`
FROM `tempconfig_tcfg` WHERE `tcfg_name`='sDisallowedPasswords';
INSERT INTO `config_cfg`
SELECT 16,`tcfg_name`,`tcfg_value`,`tcfg_type`,`tcfg_default`,`tcfg_tooltip`,`tcfg_section`
FROM `tempconfig_tcfg` WHERE `tcfg_name`='iMaxFailedLogins';
INSERT INTO `config_cfg`
SELECT 17,`tcfg_name`,`tcfg_value`,`tcfg_type`,`tcfg_default`,`tcfg_tooltip`,`tcfg_section`
FROM `tempconfig_tcfg` WHERE `tcfg_name`='bToolTipsOn';
INSERT INTO `config_cfg`
SELECT 18,`tcfg_name`,`tcfg_value`,`tcfg_type`,`tcfg_default`,`tcfg_tooltip`,`tcfg_section`
FROM `tempconfig_tcfg` WHERE `tcfg_name`='iNavMethod';
INSERT INTO `config_cfg`
SELECT 19,`tcfg_name`,`tcfg_value`,`tcfg_type`,`tcfg_default`,`tcfg_tooltip`,`tcfg_section`
FROM `tempconfig_tcfg` WHERE `tcfg_name`='bFamListFirstNames';
INSERT INTO `config_cfg`
SELECT 20,`tcfg_name`,`tcfg_value`,`tcfg_type`,`tcfg_default`,`tcfg_tooltip`,`tcfg_section`
FROM `tempconfig_tcfg` WHERE `tcfg_name`='iPDFOutputType';
INSERT INTO `config_cfg`
SELECT 21,`tcfg_name`,`tcfg_value`,`tcfg_type`,`tcfg_default`,`tcfg_tooltip`,`tcfg_section`
FROM `tempconfig_tcfg` WHERE `tcfg_name`='sDefaultCity';
INSERT INTO `config_cfg`
SELECT 22,`tcfg_name`,`tcfg_value`,`tcfg_type`,`tcfg_default`,`tcfg_tooltip`,`tcfg_section`
FROM `tempconfig_tcfg` WHERE `tcfg_name`='sDefaultState';
INSERT INTO `config_cfg`
SELECT 23,`tcfg_name`,`tcfg_value`,`tcfg_type`,`tcfg_default`,`tcfg_tooltip`,`tcfg_section`
FROM `tempconfig_tcfg` WHERE `tcfg_name`='sDefaultCountry';
INSERT INTO `config_cfg`
SELECT 24,`tcfg_name`,`tcfg_value`,`tcfg_type`,`tcfg_default`,`tcfg_tooltip`,`tcfg_section`
FROM `tempconfig_tcfg` WHERE `tcfg_name`='bEmailSend';
INSERT INTO `config_cfg`
SELECT 25,`tcfg_name`,`tcfg_value`,`tcfg_type`,`tcfg_default`,`tcfg_tooltip`,`tcfg_section`
FROM `tempconfig_tcfg` WHERE `tcfg_name`='sSendType';
INSERT INTO `config_cfg`
SELECT 26,`tcfg_name`,`tcfg_value`,`tcfg_type`,`tcfg_default`,`tcfg_tooltip`,`tcfg_section`
FROM `tempconfig_tcfg` WHERE `tcfg_name`='sToEmailAddress';
INSERT INTO `config_cfg`
SELECT 27,`tcfg_name`,`tcfg_value`,`tcfg_type`,`tcfg_default`,`tcfg_tooltip`,`tcfg_section`
FROM `tempconfig_tcfg` WHERE `tcfg_name`='sSMTPHost';
INSERT INTO `config_cfg`
SELECT 28,`tcfg_name`,`tcfg_value`,`tcfg_type`,`tcfg_default`,`tcfg_tooltip`,`tcfg_section`
FROM `tempconfig_tcfg` WHERE `tcfg_name`='sSMTPAuth';
INSERT INTO `config_cfg`
SELECT 29,`tcfg_name`,`tcfg_value`,`tcfg_type`,`tcfg_default`,`tcfg_tooltip`,`tcfg_section`
FROM `tempconfig_tcfg` WHERE `tcfg_name`='sSMTPUser';
INSERT INTO `config_cfg`
SELECT 30,`tcfg_name`,`tcfg_value`,`tcfg_type`,`tcfg_default`,`tcfg_tooltip`,`tcfg_section`
FROM `tempconfig_tcfg` WHERE `tcfg_name`='sSMTPPass';
INSERT INTO `config_cfg`
SELECT 31,`tcfg_name`,`tcfg_value`,`tcfg_type`,`tcfg_default`,`tcfg_tooltip`,`tcfg_section`
FROM `tempconfig_tcfg` WHERE `tcfg_name`='sWordWrap';
INSERT INTO `config_cfg`
SELECT 32,`tcfg_name`,`tcfg_value`,`tcfg_type`,`tcfg_default`,`tcfg_tooltip`,`tcfg_section`
FROM `tempconfig_tcfg` WHERE `tcfg_name`='bDefectiveBrowser';
INSERT INTO `config_cfg`
SELECT 33,`tcfg_name`,`tcfg_value`,`tcfg_type`,`tcfg_default`,`tcfg_tooltip`,`tcfg_section`
FROM `tempconfig_tcfg` WHERE `tcfg_name`='bShowFamilyData';
INSERT INTO `config_cfg`
SELECT 34,`tcfg_name`,`tcfg_value`,`tcfg_type`,`tcfg_default`,`tcfg_tooltip`,`tcfg_section`
FROM `tempconfig_tcfg` WHERE `tcfg_name`='bOldVCardVersion';
INSERT INTO `config_cfg`
SELECT 35,`tcfg_name`,`tcfg_value`,`tcfg_type`,`tcfg_default`,`tcfg_tooltip`,`tcfg_section`
FROM `tempconfig_tcfg` WHERE `tcfg_name`='bEnableBackupUtility';
INSERT INTO `config_cfg`
SELECT 36,`tcfg_name`,`tcfg_value`,`tcfg_type`,`tcfg_default`,`tcfg_tooltip`,`tcfg_section`
FROM `tempconfig_tcfg` WHERE `tcfg_name`='sGZIPname';
INSERT INTO `config_cfg`
SELECT 37,`tcfg_name`,`tcfg_value`,`tcfg_type`,`tcfg_default`,`tcfg_tooltip`,`tcfg_section`
FROM `tempconfig_tcfg` WHERE `tcfg_name`='sZIPname';
INSERT INTO `config_cfg`
SELECT 38,`tcfg_name`,`tcfg_value`,`tcfg_type`,`tcfg_default`,`tcfg_tooltip`,`tcfg_section`
FROM `tempconfig_tcfg` WHERE `tcfg_name`='sPGPname';
INSERT INTO `config_cfg`
SELECT 39,`tcfg_name`,`tcfg_value`,`tcfg_type`,`tcfg_default`,`tcfg_tooltip`,`tcfg_section`
FROM `tempconfig_tcfg` WHERE `tcfg_name`='sLanguage';
INSERT INTO `config_cfg`
SELECT 40,`tcfg_name`,`tcfg_value`,`tcfg_type`,`tcfg_default`,`tcfg_tooltip`,`tcfg_section`
FROM `tempconfig_tcfg` WHERE `tcfg_name`='iFYMonth';
INSERT INTO `config_cfg`
SELECT 41,`tcfg_name`,`tcfg_value`,`tcfg_type`,`tcfg_default`,`tcfg_tooltip`,`tcfg_section`
FROM `tempconfig_tcfg` WHERE `tcfg_name`='sXML_RPC_PATH';
INSERT INTO `config_cfg`
SELECT 42,`tcfg_name`,`tcfg_value`,`tcfg_type`,`tcfg_default`,`tcfg_tooltip`,`tcfg_section`
FROM `tempconfig_tcfg` WHERE `tcfg_name`='sGeocoderID';
INSERT INTO `config_cfg`
SELECT 43,`tcfg_name`,`tcfg_value`,`tcfg_type`,`tcfg_default`,`tcfg_tooltip`,`tcfg_section`
FROM `tempconfig_tcfg` WHERE `tcfg_name`='sGeocoderPW';
INSERT INTO `config_cfg`
SELECT 44,`tcfg_name`,`tcfg_value`,`tcfg_type`,`tcfg_default`,`tcfg_tooltip`,`tcfg_section`
FROM `tempconfig_tcfg` WHERE `tcfg_name`='sGoogleMapKey';
INSERT INTO `config_cfg`
SELECT 45,`tcfg_name`,`tcfg_value`,`tcfg_type`,`tcfg_default`,`tcfg_tooltip`,`tcfg_section`
FROM `tempconfig_tcfg` WHERE `tcfg_name`='nChurchLatitude';
INSERT INTO `config_cfg`
SELECT 46,`tcfg_name`,`tcfg_value`,`tcfg_type`,`tcfg_default`,`tcfg_tooltip`,`tcfg_section`
FROM `tempconfig_tcfg` WHERE `tcfg_name`='nChurchLongitude';
INSERT INTO `config_cfg`
SELECT 47,`tcfg_name`,`tcfg_value`,`tcfg_type`,`tcfg_default`,`tcfg_tooltip`,`tcfg_section`
FROM `tempconfig_tcfg` WHERE `tcfg_name`='bHidePersonAddress';
INSERT INTO `config_cfg`
SELECT 48,`tcfg_name`,`tcfg_value`,`tcfg_type`,`tcfg_default`,`tcfg_tooltip`,`tcfg_section`
FROM `tempconfig_tcfg` WHERE `tcfg_name`='bHideFriendDate';
INSERT INTO `config_cfg`
SELECT 49,`tcfg_name`,`tcfg_value`,`tcfg_type`,`tcfg_default`,`tcfg_tooltip`,`tcfg_section`
FROM `tempconfig_tcfg` WHERE `tcfg_name`='bHideFamilyNewsletter';
INSERT INTO `config_cfg`
SELECT 50,`tcfg_name`,`tcfg_value`,`tcfg_type`,`tcfg_default`,`tcfg_tooltip`,`tcfg_section`
FROM `tempconfig_tcfg` WHERE `tcfg_name`='bHideWeddingDate';
INSERT INTO `config_cfg`
SELECT 51,`tcfg_name`,`tcfg_value`,`tcfg_type`,`tcfg_default`,`tcfg_tooltip`,`tcfg_section`
FROM `tempconfig_tcfg` WHERE `tcfg_name`='bHideLatLon';
INSERT INTO `config_cfg`
SELECT 52,`tcfg_name`,`tcfg_value`,`tcfg_type`,`tcfg_default`,`tcfg_tooltip`,`tcfg_section`
FROM `tempconfig_tcfg` WHERE `tcfg_name`='bUseDonationEnvelopes';
INSERT INTO `config_cfg`
SELECT 53,`tcfg_name`,`tcfg_value`,`tcfg_type`,`tcfg_default`,`tcfg_tooltip`,`tcfg_section`
FROM `tempconfig_tcfg` WHERE `tcfg_name`='sHeader';
INSERT INTO `config_cfg`
SELECT 54,`tcfg_name`,`tcfg_value`,`tcfg_type`,`tcfg_default`,`tcfg_tooltip`,`tcfg_section`
FROM `tempconfig_tcfg` WHERE `tcfg_name`='sISTusername';
INSERT INTO `config_cfg`
SELECT 55,`tcfg_name`,`tcfg_value`,`tcfg_type`,`tcfg_default`,`tcfg_tooltip`,`tcfg_section`
FROM `tempconfig_tcfg` WHERE `tcfg_name`='sISTpassword';
INSERT INTO `config_cfg`
SELECT 999,`tcfg_name`,`tcfg_value`,`tcfg_type`,`tcfg_default`,`tcfg_tooltip`,`tcfg_section`
FROM `tempconfig_tcfg` WHERE `tcfg_name`='bRegistered';
INSERT INTO `config_cfg`
SELECT 1001,`tcfg_name`,`tcfg_value`,`tcfg_type`,`tcfg_default`,`tcfg_tooltip`,`tcfg_section`
FROM `tempconfig_tcfg` WHERE `tcfg_name`='leftX';
INSERT INTO `config_cfg`
SELECT 1002,`tcfg_name`,`tcfg_value`,`tcfg_type`,`tcfg_default`,`tcfg_tooltip`,`tcfg_section`
FROM `tempconfig_tcfg` WHERE `tcfg_name`='incrementY';
INSERT INTO `config_cfg`
SELECT 1003,`tcfg_name`,`tcfg_value`,`tcfg_type`,`tcfg_default`,`tcfg_tooltip`,`tcfg_section`
FROM `tempconfig_tcfg` WHERE `tcfg_name`='sChurchName';
INSERT INTO `config_cfg`
SELECT 1004,`tcfg_name`,`tcfg_value`,`tcfg_type`,`tcfg_default`,`tcfg_tooltip`,`tcfg_section`
FROM `tempconfig_tcfg` WHERE `tcfg_name`='sChurchAddress';
INSERT INTO `config_cfg`
SELECT 1005,`tcfg_name`,`tcfg_value`,`tcfg_type`,`tcfg_default`,`tcfg_tooltip`,`tcfg_section`
FROM `tempconfig_tcfg` WHERE `tcfg_name`='sChurchCity';
INSERT INTO `config_cfg`
SELECT 1006,`tcfg_name`,`tcfg_value`,`tcfg_type`,`tcfg_default`,`tcfg_tooltip`,`tcfg_section`
FROM `tempconfig_tcfg` WHERE `tcfg_name`='sChurchState';
INSERT INTO `config_cfg`
SELECT 1007,`tcfg_name`,`tcfg_value`,`tcfg_type`,`tcfg_default`,`tcfg_tooltip`,`tcfg_section`
FROM `tempconfig_tcfg` WHERE `tcfg_name`='sChurchZip';
INSERT INTO `config_cfg`
SELECT 1008,`tcfg_name`,`tcfg_value`,`tcfg_type`,`tcfg_default`,`tcfg_tooltip`,`tcfg_section`
FROM `tempconfig_tcfg` WHERE `tcfg_name`='sChurchPhone';
INSERT INTO `config_cfg`
SELECT 1009,`tcfg_name`,`tcfg_value`,`tcfg_type`,`tcfg_default`,`tcfg_tooltip`,`tcfg_section`
FROM `tempconfig_tcfg` WHERE `tcfg_name`='sChurchEmail';
INSERT INTO `config_cfg`
SELECT 1010,`tcfg_name`,`tcfg_value`,`tcfg_type`,`tcfg_default`,`tcfg_tooltip`,`tcfg_section`
FROM `tempconfig_tcfg` WHERE `tcfg_name`='sHomeAreaCode';
INSERT INTO `config_cfg`
SELECT 1011,`tcfg_name`,`tcfg_value`,`tcfg_type`,`tcfg_default`,`tcfg_tooltip`,`tcfg_section`
FROM `tempconfig_tcfg` WHERE `tcfg_name`='sTaxReport1';
INSERT INTO `config_cfg`
SELECT 1012,`tcfg_name`,`tcfg_value`,`tcfg_type`,`tcfg_default`,`tcfg_tooltip`,`tcfg_section`
FROM `tempconfig_tcfg` WHERE `tcfg_name`='sTaxReport2';
INSERT INTO `config_cfg`
SELECT 1013,`tcfg_name`,`tcfg_value`,`tcfg_type`,`tcfg_default`,`tcfg_tooltip`,`tcfg_section`
FROM `tempconfig_tcfg` WHERE `tcfg_name`='sTaxReport3';
INSERT INTO `config_cfg`
SELECT 1014,`tcfg_name`,`tcfg_value`,`tcfg_type`,`tcfg_default`,`tcfg_tooltip`,`tcfg_section`
FROM `tempconfig_tcfg` WHERE `tcfg_name`='sTaxSigner';
INSERT INTO `config_cfg`
SELECT 1015,`tcfg_name`,`tcfg_value`,`tcfg_type`,`tcfg_default`,`tcfg_tooltip`,`tcfg_section`
FROM `tempconfig_tcfg` WHERE `tcfg_name`='sReminder1';
INSERT INTO `config_cfg`
SELECT 1016,`tcfg_name`,`tcfg_value`,`tcfg_type`,`tcfg_default`,`tcfg_tooltip`,`tcfg_section`
FROM `tempconfig_tcfg` WHERE `tcfg_name`='sReminderSigner';
INSERT INTO `config_cfg`
SELECT 1017,`tcfg_name`,`tcfg_value`,`tcfg_type`,`tcfg_default`,`tcfg_tooltip`,`tcfg_section`
FROM `tempconfig_tcfg` WHERE `tcfg_name`='sReminderNoPledge';
INSERT INTO `config_cfg`
SELECT 1018,`tcfg_name`,`tcfg_value`,`tcfg_type`,`tcfg_default`,`tcfg_tooltip`,`tcfg_section`
FROM `tempconfig_tcfg` WHERE `tcfg_name`='sReminderNoPayments';
INSERT INTO `config_cfg`
SELECT 1019,`tcfg_name`,`tcfg_value`,`tcfg_type`,`tcfg_default`,`tcfg_tooltip`,`tcfg_section`
FROM `tempconfig_tcfg` WHERE `tcfg_name`='sConfirm1';
INSERT INTO `config_cfg`
SELECT 1020,`tcfg_name`,`tcfg_value`,`tcfg_type`,`tcfg_default`,`tcfg_tooltip`,`tcfg_section`
FROM `tempconfig_tcfg` WHERE `tcfg_name`='sConfirm2';
INSERT INTO `config_cfg`
SELECT 1021,`tcfg_name`,`tcfg_value`,`tcfg_type`,`tcfg_default`,`tcfg_tooltip`,`tcfg_section`
FROM `tempconfig_tcfg` WHERE `tcfg_name`='sConfirm3';
INSERT INTO `config_cfg`
SELECT 1022,`tcfg_name`,`tcfg_value`,`tcfg_type`,`tcfg_default`,`tcfg_tooltip`,`tcfg_section`
FROM `tempconfig_tcfg` WHERE `tcfg_name`='sConfirm4';
INSERT INTO `config_cfg`
SELECT 1023,`tcfg_name`,`tcfg_value`,`tcfg_type`,`tcfg_default`,`tcfg_tooltip`,`tcfg_section`
FROM `tempconfig_tcfg` WHERE `tcfg_name`='sConfirm5';
INSERT INTO `config_cfg`
SELECT 1024,`tcfg_name`,`tcfg_value`,`tcfg_type`,`tcfg_default`,`tcfg_tooltip`,`tcfg_section`
FROM `tempconfig_tcfg` WHERE `tcfg_name`='sConfirm6';
INSERT INTO `config_cfg`
SELECT 1025,`tcfg_name`,`tcfg_value`,`tcfg_type`,`tcfg_default`,`tcfg_tooltip`,`tcfg_section`
FROM `tempconfig_tcfg` WHERE `tcfg_name`='sConfirmSigner';
INSERT INTO `config_cfg`
SELECT 1026,`tcfg_name`,`tcfg_value`,`tcfg_type`,`tcfg_default`,`tcfg_tooltip`,`tcfg_section`
FROM `tempconfig_tcfg` WHERE `tcfg_name`='sPledgeSummary1';
INSERT INTO `config_cfg`
SELECT 1027,`tcfg_name`,`tcfg_value`,`tcfg_type`,`tcfg_default`,`tcfg_tooltip`,`tcfg_section`
FROM `tempconfig_tcfg` WHERE `tcfg_name`='sPledgeSummary2';
INSERT INTO `config_cfg`
SELECT 1028,`tcfg_name`,`tcfg_value`,`tcfg_type`,`tcfg_default`,`tcfg_tooltip`,`tcfg_section`
FROM `tempconfig_tcfg` WHERE `tcfg_name`='sDirectoryDisclaimer1';
INSERT INTO `config_cfg`
SELECT 1029,`tcfg_name`,`tcfg_value`,`tcfg_type`,`tcfg_default`,`tcfg_tooltip`,`tcfg_section`
FROM `tempconfig_tcfg` WHERE `tcfg_name`='sDirectoryDisclaimer2';
INSERT INTO `config_cfg`
SELECT 1030,`tcfg_name`,`tcfg_value`,`tcfg_type`,`tcfg_default`,`tcfg_tooltip`,`tcfg_section`
FROM `tempconfig_tcfg` WHERE `tcfg_name`='bDirLetterHead';

DROP TABLE IF EXISTS `tempconfig_tcfg`;
