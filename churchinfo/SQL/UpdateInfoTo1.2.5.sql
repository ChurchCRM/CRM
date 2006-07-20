CREATE TABLE IF NOT EXISTS `autopayment_aut` (
  `aut_ID` mediumint(9) unsigned NOT NULL auto_increment,
  `aut_FamID` mediumint(9) unsigned NOT NULL default '0',
  `aut_EnableBankDraft` tinyint(1) unsigned NOT NULL default '0',
  `aut_EnableCreditCard` tinyint(1) unsigned NOT NULL default '0',
  `aut_NextPayDate` date default NULL,
  `aut_FYID` mediumint(9) NOT NULL default '9',
  `aut_Amount` decimal(6,2) NOT NULL default '0.00',
  `aut_Interval` tinyint(3) NOT NULL default '1',
  `aut_Fund` mediumint(6) NOT NULL default '0',
  `aut_FirstName` varchar(50) default NULL,
  `aut_LastName` varchar(50) default NULL,
  `aut_Address1` varchar(255) default NULL,
  `aut_Address2` varchar(255) default NULL,
  `aut_City` varchar(50) default NULL,
  `aut_State` varchar(50) default NULL,
  `aut_Zip` varchar(50) default NULL,
  `aut_Country` varchar(50) default NULL,
  `aut_Phone` varchar(30) default NULL,
  `aut_Email` varchar(100) default NULL,
  `aut_CreditCard` varchar(50) default NULL,
  `aut_ExpMonth` varchar(2) default NULL,
  `aut_ExpYear` varchar(4) default NULL,
  `aut_BankName` varchar(50) default NULL,
  `aut_Route` varchar(30) default NULL,
  `aut_Account` varchar(30) default NULL,
  `aut_DateLastEdited` datetime default NULL,
  `aut_EditedBy` smallint(5) unsigned default '0',
  `aut_Serial` mediumint(9) NOT NULL default '1',
  PRIMARY KEY  (`aut_ID`),
  UNIQUE KEY `aut_ID` (`aut_ID`)
) TYPE=MyISAM  ;

CREATE TABLE IF NOT EXISTS `canvassdata_can` (
  `can_ID` mediumint(9) unsigned NOT NULL auto_increment,
  `can_famID` mediumint(9) NOT NULL default '0',
  `can_Canvasser` mediumint(9) NOT NULL default '0',
  `can_FYID` mediumint(9) default NULL,
  `can_date` date default NULL,
  `can_Positive` text collate latin1_general_ci,
  `can_Critical` text collate latin1_general_ci,
  `can_Insightful` text collate latin1_general_ci,
  `can_Financial` text collate latin1_general_ci,
  `can_Suggestion` text collate latin1_general_ci,
  `can_NotInterested` tinyint(1) NOT NULL default '0',
  `can_WhyNotInterested` text collate latin1_general_ci,
  PRIMARY KEY  (`can_ID`),
  UNIQUE KEY `can_ID` (`can_ID`)
) TYPE=MyISAM  ;

CREATE TABLE IF NOT EXISTS `config_cfg` (
  `cfg_id` int(11) NOT NULL default '0',
  `cfg_name` varchar(50) NOT NULL default '',
  `cfg_value` varchar(255) default NULL,
  `cfg_type` enum('text','number','date','boolean') NOT NULL default 'text',
  `cfg_default` varchar(255) NOT NULL default '',
  `cfg_tooltip` text NOT NULL,
  `cfg_section` varchar(50) NOT NULL default '',
  PRIMARY KEY  (`cfg_id`)
) TYPE=MyISAM  ;

INSERT IGNORE INTO `config_cfg` VALUES (1, 'sWEBCALENDARDB', '', 'text', '', 'WebCalendar database name', 'General');
INSERT IGNORE INTO `config_cfg` VALUES (2, 'aHTTPports', '80,8000,8080', 'text', '80,8000,8080', 'Ports on which the web server may run.  Defaults are fine for most people.', 'General');
INSERT IGNORE INTO `config_cfg` VALUES (3, 'aHTTPSports', '443', 'text', '443', 'Ports on which the SSL web server may run.  Defaults are fine for most people.', 'General');
INSERT IGNORE INTO `config_cfg` VALUES (5, 'debug', '1', 'boolean', '1', 'Set debug mode\r\nThis may be helpful for when you''re first setting up ChurchInfo, but you should\r\nprobably turn it off for maximum security otherwise.  If you are having trouble,\r\nplease enable this so that you''ll know what the errors are.  This is especially\r\nimportant if you need to report a problem on the help forums.', 'General');
INSERT IGNORE INTO `config_cfg` VALUES (6, 'sJPGRAPH_PATH', 'Include/jpgraph-1.13/src', 'text', 'Include/jpgraph-1.13/src', 'JPGraph library', 'General');
INSERT IGNORE INTO `config_cfg` VALUES (7, 'sFPDF_PATH', 'Include/fpdf', 'text', 'Include/fpdf', 'FPDF library', 'General');
INSERT IGNORE INTO `config_cfg` VALUES (8, 'sPHPMAILER_PATH', 'Include/phpmailer', 'text', 'Include/phpmailer', 'phpmailer library', 'General');
INSERT IGNORE INTO `config_cfg` VALUES (9, 'sDirClassifications', '1,2,4,5', 'text', '1,2,4,5', 'Include only these classifications in the directory, comma seperated', 'General');
INSERT IGNORE INTO `config_cfg` VALUES (10, 'sDirRoleHead', '1,7', 'text', '1,7', 'These are the family role numbers designated as head of house', 'General');
INSERT IGNORE INTO `config_cfg` VALUES (11, 'sDirRoleSpouse', '2', 'text', '2', 'These are the family role numbers designated as spouse', 'General');
INSERT IGNORE INTO `config_cfg` VALUES (12, 'sDirRoleChild', '3', 'text', '3', 'These are the family role numbers designated as child', 'General');
INSERT IGNORE INTO `config_cfg` VALUES (13, 'sSessionTimeout', '3600', 'number', '3600', 'Session timeout length in seconds\rSet to zero to disable session timeouts.', 'General');
INSERT IGNORE INTO `config_cfg` VALUES (14, 'aFinanceQueries', '28', 'text', '28', 'Queries for which user must have finance permissions to use:', 'General');
INSERT IGNORE INTO `config_cfg` VALUES (15, 'bCSVAdminOnly', '1', 'boolean', '1', 'Should only administrators have access to the CSV export system and directory report?', 'General');
INSERT IGNORE INTO `config_cfg` VALUES (16, 'sDefault_Pass', 'password', 'text', 'password', 'Default password for new users and those with reset passwords', 'General');
INSERT IGNORE INTO `config_cfg` VALUES (17, 'sMinPasswordLength', '6', 'number', '6', 'Minimum length a user may set their password to', 'General');
INSERT IGNORE INTO `config_cfg` VALUES (18, 'sMinPasswordChange', '4', 'number', '4', 'Minimum amount that a new password must differ from the old one (# of characters changed)\rSet to zero to disable this feature', 'General');
INSERT IGNORE INTO `config_cfg` VALUES (19, 'sDisallowedPasswords', 'churchinfo,password,god,jesus,church,christian', 'text', 'churchinfo,password,god,jesus,church,christian', 'A comma-seperated list of disallowed (too obvious) passwords.', 'General');
INSERT IGNORE INTO `config_cfg` VALUES (20, 'iMaxFailedLogins', '50', 'number', '50', 'Maximum number of failed logins to allow before a user account is locked.\rOnce the maximum has been reached, an administrator must re-enable the account.\rThis feature helps to protect against automated password guessing attacks.\rSet to zero to disable this feature.', 'General');
INSERT IGNORE INTO `config_cfg` VALUES (21, 'bToolTipsOn', '', 'boolean', '', 'Turn on or off guided help (Tool Tips).\rThis feature is not complete.  Leave off for now.', 'General');
INSERT IGNORE INTO `config_cfg` VALUES (22, 'iNavMethod', '1', 'number', '1', 'Interface navigation method\r1 = Javascript MenuBar (default)\r2 = Flat Sidebar (alternative for buggy browsers)', 'General');
INSERT IGNORE INTO `config_cfg` VALUES (23, 'bFamListFirstNames', '1', 'boolean', '1', 'Show family member firstnames in Family Listing?', 'General');
INSERT IGNORE INTO `config_cfg` VALUES (24, 'iPDFOutputType', '1', 'number', '1', 'PDF handling mode.\r1 = Save File dialog\r2 = Open in current browser window', 'General');
INSERT IGNORE INTO `config_cfg` VALUES (25, 'sDefaultCity', '', 'text', '', 'Default City', 'General');
INSERT IGNORE INTO `config_cfg` VALUES (26, 'sDefaultState', '', 'text', '', 'Default State - Must be 2-letter abbreviation!', 'General');
INSERT IGNORE INTO `config_cfg` VALUES (27, 'sDefaultCountry', 'United States', 'text', 'United States', 'Default Country', 'General');
INSERT IGNORE INTO `config_cfg` VALUES (28, 'bEmailSend', '', 'boolean', '', 'If you wish to be able to send emails from within ChurchInfo. This requires\reither an SMTP server address to send from or sendmail installed in PHP.', 'General');
INSERT IGNORE INTO `config_cfg` VALUES (29, 'sSendType', 'smtp', 'text', 'smtp', 'The method for sending email. Either "smtp" or "sendmail"', 'General');
INSERT IGNORE INTO `config_cfg` VALUES (30, 'sFromEmailAddress', '', 'text', '', 'The email address that shows up in the "From:" field', 'General');
INSERT IGNORE INTO `config_cfg` VALUES (31, 'sFromName', 'ChurchInfo Webmaster', 'text', 'ChurchInfo Webmaster', 'The name that shows up on email address', 'General');
INSERT IGNORE INTO `config_cfg` VALUES (32, 'sToEmailAddress', '', 'text', '', 'Default account for receiving a copy of all emails', 'General');
INSERT IGNORE INTO `config_cfg` VALUES (33, 'sSMTPHost', '', 'text', '', 'SMTP Server Address (mail.server.com:25)', 'General');
INSERT IGNORE INTO `config_cfg` VALUES (34, 'sSMTPAuth', '1', 'boolean', '1', 'Does your SMTP server require auththentication (username/password)?', 'General');
INSERT IGNORE INTO `config_cfg` VALUES (35, 'sSMTPUser', '', 'text', '', 'SMTP Username', 'General');
INSERT IGNORE INTO `config_cfg` VALUES (36, 'sSMTPPass', '', 'text', '', 'SMTP Password', 'General');
INSERT IGNORE INTO `config_cfg` VALUES (37, 'sWordWrap', '72', 'number', '72', 'Word Wrap point. Default for most email programs is 72', 'General');
INSERT IGNORE INTO `config_cfg` VALUES (38, 'bDefectiveBrowser', '1', 'boolean', '1', 'Are you using any non-standards-compliant "broken" browsers at this installation?\rIf so, enabling this will turn off the CSS tags that make the menubar stay\rat the top of the screen instead of scrolling with the rest of the page.\rIt will also turn off the use of nice, alpha-blended PNG images, which IE still\rdoes not properly handle.\rNOTICE: MS Internet Explorer is currently not standards-compliant enough for\rthese purposes.  Please use a quality web browser such as Netscape 7, Firefox, etc.\r', 'General');
INSERT IGNORE INTO `config_cfg` VALUES (39, 'bShowFamilyData', '1', 'boolean', '1', 'Unavailable person info inherited from assigned family for display?\rThis option causes certain info from a person''s assigned family record to be\rdisplayed IF the corresponding info has NOT been entered for that person. ', 'General');
INSERT IGNORE INTO `config_cfg` VALUES (40, 'bOldVCardVersion', '', 'boolean', '', 'Use vCard 2.1 rather than vCard 3.0 standard.', 'General');
INSERT IGNORE INTO `config_cfg` VALUES (41, 'bEnableBackupUtility', '', 'boolean', '', 'This backup system only works on "UNIX-style" operating systems such as\rGNU/Linux, OSX and the BSD variants (NOT Windows).\rOf course, remember that only your web server needs to running a UNIX-style\rOS for this feature to work.', 'General');
INSERT IGNORE INTO `config_cfg` VALUES (42, 'sGZIPname', 'gzip', 'text', 'gzip', '', 'General');
INSERT IGNORE INTO `config_cfg` VALUES (43, 'sZIPname', 'zip', 'text', 'zip', '', 'General');
INSERT IGNORE INTO `config_cfg` VALUES (44, 'sPGPname', 'gpg', 'text', 'gpg', '', 'General');
INSERT IGNORE INTO `config_cfg` VALUES (45, 'sLanguage', 'en_US', 'text', 'en_US', 'Internationalization (I18n) support\rUS English (en_US), Italian (it_IT), French (fr_FR), and German (de_DE)', 'General');
INSERT IGNORE INTO `config_cfg` VALUES (46, 'iFYMonth', '1', 'number', '1', 'First month of the fiscal year', 'General');
INSERT IGNORE INTO `config_cfg` VALUES (76, 'sXML_RPC_PATH', 'XML/RPC.php', 'text', 'XML/RPC.php', 'Path to RPC.php, required for Lat/Lon address lookup', 'General');
INSERT IGNORE INTO `config_cfg` VALUES (77, 'sGeocoderID', '', 'text', '', 'User ID for rpc.geocoder.us', 'General');
INSERT IGNORE INTO `config_cfg` VALUES (78, 'sGeocoderPW', '', 'text', '', 'Password for rpc.geocoder.us', 'General');
INSERT IGNORE INTO `config_cfg` VALUES (79, 'sGoogleMapKey', '', 'text', '', 'Google map API requires a unique key from http://maps.google.com/apis/maps/signup.html', 'General');
INSERT IGNORE INTO `config_cfg` VALUES (80, 'nChurchLatitude', '', 'number', '', 'Latitude of the church, used to center the Google map', 'General');
INSERT IGNORE INTO `config_cfg` VALUES (81, 'nChurchLongitude', '', 'number', '', 'Longitude of the church, used to center the Google map', 'General');
INSERT IGNORE INTO `config_cfg` VALUES (82, 'bHidePersonAddress', '1', 'boolean', '1', 'Set true to disable entering addresses in Person Editor.  Set false to enable entering addresses in Person Editor.', 'General');
INSERT IGNORE INTO `config_cfg` VALUES (83, 'bHideFriendDate', '0', 'boolean', '0', 'Set true to disable entering Friend Date in Person Editor.  Set false to enable entering Friend Date in Person Editor.', 'General');
INSERT IGNORE INTO `config_cfg` VALUES (84, 'bHideFamilyNewsletter', '0', 'boolean', '0', 'Set true to disable management of newsletter subscriptions in the Family Editor.', 'General');
INSERT IGNORE INTO `config_cfg` VALUES (85, 'bHideWeddingDate', '0', 'boolean', '0', 'Set true to disable entering Wedding Date in Family Editor.  Set false to enable entering Wedding Date in Family Editor.', 'General');
INSERT IGNORE INTO `config_cfg` VALUES (86, 'bHideLatLon', '0', 'boolean', '0', 'Set true to disable entering Latitude and Longitude in Family Editor.  Set false to enable entering Latitude and Longitude in Family Editor.  Lookups are still performed, just not displayed.', 'General');
INSERT IGNORE INTO `config_cfg` VALUES (87, 'bUseDonationEnvelopes', '0', 'boolean', '0', 'Set true to enable use of donation envelopes', 'General');
INSERT IGNORE INTO `config_cfg` VALUES (48, 'leftX', '20', 'number', '20', 'Left Margin (1 = 1/100th inch)', 'ChurchInfoReport');
INSERT IGNORE INTO `config_cfg` VALUES (49, 'incrementY', '4', 'number', '4', 'Line Thickness (1 = 1/100th inch', 'ChurchInfoReport');
INSERT IGNORE INTO `config_cfg` VALUES (50, 'sChurchName', 'Some Church', 'text', '', 'Church Name', 'ChurchInfoReport');
INSERT IGNORE INTO `config_cfg` VALUES (51, 'sChurchAddress', '100 Main St', 'text', '', 'Church Address', 'ChurchInfoReport');
INSERT IGNORE INTO `config_cfg` VALUES (52, 'sChurchCity', 'Wall', 'text', '', 'Church City', 'ChurchInfoReport');
INSERT IGNORE INTO `config_cfg` VALUES (53, 'sChurchState', 'SD', 'text', '', 'Church State', 'ChurchInfoReport');
INSERT IGNORE INTO `config_cfg` VALUES (54, 'sChurchZip', '11111', 'text', '', 'Church Zip', 'ChurchInfoReport');
INSERT IGNORE INTO `config_cfg` VALUES (55, 'sChurchPhone', '123-456-7890', 'text', '', 'Church Phone', 'ChurchInfoReport');
INSERT IGNORE INTO `config_cfg` VALUES (56, 'sChurchEmail', 'church@church.org', 'text', '', 'Church Email', 'ChurchInfoReport');
INSERT IGNORE INTO `config_cfg` VALUES (57, 'sHomeAreaCode', '111', 'text', '', 'Home area code of the church', 'ChurchInfoReport');
INSERT IGNORE INTO `config_cfg` VALUES (58, 'sTaxReport1', 'This letter shows our record of your payments for', 'text', 'This letter shows our record of your payments for', 'Verbage for top line of tax report. Dates will be appended to the end of this line.', 'ChurchInfoReport');
INSERT IGNORE INTO `config_cfg` VALUES (59, 'sTaxReport2', 'Thank you for your help in making a difference for the cause of Christ. We greatly appreciate your gift and covet your prayers!', 'text', 'Thank you for your help in making a difference for the cause of Christ. We greatly appreciate your gift and covet your prayers!', 'Verbage for bottom line of tax report.', 'ChurchInfoReport');
INSERT IGNORE INTO `config_cfg` VALUES (60, 'sTaxReport3', 'If you have any questions or corrections to make to this report, please contact the church at the above number during business hours, 9am to 4pm, M-F.', 'text', 'If you have any questions or corrections to make to this report, please contact the church at the above number during business hours, 9am to 4pm, M-F.', 'Verbage for bottom line of tax report.', 'ChurchInfoReport');
INSERT IGNORE INTO `config_cfg` VALUES (61, 'sTaxSigner', 'Elder Joe Smith', 'text', '', 'Tax Report signer', 'ChurchInfoReport');
INSERT IGNORE INTO `config_cfg` VALUES (62, 'sReminder1', 'This letter shows our record of your pledge and payments for fiscal year', 'text', 'This letter shows our record of your pledge and payments for fiscal year', 'Verbage for the pledge reminder report', 'ChurchInfoReport');
INSERT IGNORE INTO `config_cfg` VALUES (63, 'sReminderSigner', 'Elder Joe Smith', 'text', '', 'Pledge Reminder Signer', 'ChurchInfoReport');
INSERT IGNORE INTO `config_cfg` VALUES (64, 'sReminderNoPledge', 'Pledges: We do not have record of a pledge for from you for this fiscal year.', 'text', 'Pledges: We do not have record of a pledge for from you for this fiscal year.', 'Verbage for the pledge reminder report - No record of a pledge', 'ChurchInfoReport');
INSERT IGNORE INTO `config_cfg` VALUES (65, 'sReminderNoPlayments', 'Payments: We do not have record of a pledge for from you for this fiscal year.', 'text', 'Payments: We do not have record of a pledge for from you for this fiscal year.', 'Verbage for the pledge reminder report - No record of payments', 'ChurchInfoReport');
INSERT IGNORE INTO `config_cfg` VALUES (66, 'sConfirm1', 'This letter shows the information we have in our database with respect to your family.  Please review, mark-up as necessary, and return this form to the church office.', 'text', 'This letter shows the information we have in our database with respect to your family.  Please review, mark-up as necessary, and return this form to the church office.', 'Verbage for the database information confirmation and correction report', 'ChurchInfoReport');
INSERT IGNORE INTO `config_cfg` VALUES (67, 'sConfirm2', 'Thank you very much for helping us to update this information.  If you want on-line access to the church database please provide your email address and a desired password and we will send instructions.', 'text', 'Thank you very much for helping us to update this information.  If you want on-line access to the church database please provide your email address and a desired password and we will send instructions.', 'Verbage for the database information confirmation and correction report', 'ChurchInfoReport');
INSERT IGNORE INTO `config_cfg` VALUES (68, 'sConfirm3', 'Email _____________________________________ Password ________________', 'text', 'Email _____________________________________ Password ________________', 'Verbage for the database information confirmation and correction report', 'ChurchInfoReport');
INSERT IGNORE INTO `config_cfg` VALUES (69, 'sConfirm4', '[  ] I no longer want to be associated with the church (check here to be removed from our records).', 'text', '[  ] I no longer want to be associated with the church (check here to be removed from our records).', 'Verbage for the database information confirmation and correction report', 'ChurchInfoReport');
INSERT IGNORE INTO `config_cfg` VALUES (70, 'sConfirmSigner', 'Elder Joe Smith', 'text', '', 'Database information confirmation and correction report signer', 'ChurchInfoReport');
INSERT IGNORE INTO `config_cfg` VALUES (71, 'sPledgeSummary1', 'Summary of pledges and payments for the fiscal year', 'text', 'Summary of pledges and payments for the fiscal year', 'Verbage for the pledge summary report', 'ChurchInfoReport');
INSERT IGNORE INTO `config_cfg` VALUES (72, 'sPledgeSummary2', 'as of', 'text', ' as of', 'Verbage for the pledge summary report', 'ChurchInfoReport');
INSERT IGNORE INTO `config_cfg` VALUES (73, 'sDirectoryDisclaimer1', 'Every effort was made to insure the accuracy of this directory.  If there are any errors or omissions, please contact the church office.This directory is for the use of the people of', 'text', 'Every effort was made to insure the accuracy of this directory.  If there are any errors or omissions, please contact the church office.\n\nThis directory is for the use of the people of', 'Verbage for the directory report', 'ChurchInfoReport');
INSERT IGNORE INTO `config_cfg` VALUES (74, 'sDirectoryDisclaimer2', ', and the information contained in it may not be used for business or commercial purposes.', 'text', ', and the information contained in it may not be used for business or commercial purposes.', 'Verbage for the directory report', 'ChurchInfoReport');
INSERT IGNORE INTO `config_cfg` VALUES (75, 'bDirLetterHead', '../Images/church_letterhead.jpg', 'text', '../Images/church_letterhead.jpg', 'Church Letterhead path and file', 'ChurchInfoReport');
INSERT IGNORE INTO `config_cfg` VALUES (100, 'bRegistered', '0', 'boolean', '0', 'ChurchInfo has been registered.  The ChurchInfo team uses registration information to track usage.  This information is kept confidential and never released or sold.  If this field is true the registration option in the admin menu changes to update registration.', 'General');
INSERT IGNORE INTO `config_cfg` VALUES (88, 'sHeader', '', 'text', '', 'Enter in HTML code which will be displayed as a header at the top of each page. Be sure to close your tags! There is a 255 character limit. Note: You must REFRESH YOUR BROWSER A SECOND TIME in order the new header.', 'General');

CREATE TABLE IF NOT EXISTS `deposit_dep` (
  `dep_ID` mediumint(9) unsigned NOT NULL auto_increment,
  `dep_Date` date default NULL,
  `dep_Comment` text collate latin1_general_ci,
  `dep_EnteredBy` mediumint(9) unsigned default NULL,
  `dep_Closed` tinyint(1) NOT NULL default '0',
  `dep_Type` enum('Bank','CreditCard','BankDraft') NOT NULL default 'Bank',
  PRIMARY KEY  (`dep_ID`)
) TYPE=MyISAM PACK_KEYS=0 ;

CREATE TABLE IF NOT EXISTS `event_attend` (
  `event_id` int(11) NOT NULL default '0',
  `person_id` int(11) NOT NULL default '0'
) TYPE=MyISAM;

CREATE TABLE IF NOT EXISTS `event_types` (
  `type_id` int(11) NOT NULL auto_increment,
  `type_name` varchar(255) NOT NULL default '',
  PRIMARY KEY  (`type_id`),
  UNIQUE KEY `event_name` (`type_name`)
) TYPE=MyISAM;

INSERT IGNORE INTO `event_types` VALUES (1, 'Church Service');
INSERT IGNORE INTO `event_types` VALUES (2, 'Sunday School');

CREATE TABLE IF NOT EXISTS `events_event` (
  `event_id` int(11) NOT NULL auto_increment,
  `event_type` int(11) NOT NULL default '0',
  `event_title` varchar(255) NOT NULL default '',
  `event_desc` varchar(255) default NULL,
  `event_text` text collate latin1_general_ci,
  `event_start` datetime NOT NULL default '0000-00-00 00:00:00',
  `event_end` datetime NOT NULL default '0000-00-00 00:00:00',
  `inactive` int(1) NOT NULL default '0',
  PRIMARY KEY  (`event_id`),
  FULLTEXT KEY `event_txt` (`event_text`)
) TYPE=MyISAM;

CREATE TABLE IF NOT EXISTS `person2volunteeropp_p2vo` (
  `p2vo_ID` mediumint(9) NOT NULL auto_increment,
  `p2vo_per_ID` mediumint(9) default NULL,
  `p2vo_vol_ID` mediumint(9) default NULL,
  PRIMARY KEY  (`p2vo_ID`),
  UNIQUE KEY `p2vo_ID` (`p2vo_ID`)
) TYPE=MyISAM  ;

ALTER TABLE `family_fam` ADD COLUMN `fam_scanCheck` text;
ALTER TABLE `family_fam` ADD COLUMN `fam_scanCredit` text;
ALTER TABLE `family_fam` ADD COLUMN `fam_SendNewsLetter` enum('FALSE','TRUE') NOT NULL default 'FALSE';
ALTER TABLE `family_fam` ADD COLUMN `fam_DateDeactivated` date default NULL;
ALTER TABLE `family_fam` ADD COLUMN `fam_OkToCanvass` enum('FALSE','TRUE') NOT NULL default 'FALSE';
ALTER TABLE `family_fam` ADD COLUMN `fam_Canvasser` smallint(5) unsigned NOT NULL default '0';
ALTER TABLE `family_fam` ADD COLUMN `fam_Latitude` double default NULL;
ALTER TABLE `family_fam` ADD COLUMN `fam_Longitude` double default NULL;
ALTER TABLE `family_fam` ADD COLUMN `fam_Envelope` mediumint(9) NOT NULL default '0';

CREATE TABLE IF NOT EXISTS `pledge_plg` (
  `plg_plgID` mediumint(9) NOT NULL auto_increment,
  `plg_FamID` mediumint(9) default NULL,
  `plg_FYID` mediumint(9) default NULL,
  `plg_date` date default NULL,
  `plg_amount` decimal(8,2) default NULL,
  `plg_schedule` enum('Monthly','Quarterly','Once','Other') default NULL,
  `plg_method` enum('CREDITCARD','CHECK','CASH','BANKDRAFT') default NULL,
  `plg_comment` text collate latin1_general_ci,
  `plg_DateLastEdited` date NOT NULL default '0000-00-00',
  `plg_EditedBy` mediumint(9) NOT NULL default '0',
  `plg_PledgeOrPayment` enum('Pledge','Payment') NOT NULL default 'Pledge',
  `plg_fundID` tinyint(3) unsigned default NULL,
  `plg_depID` mediumint(9) unsigned default NULL,
  `plg_CheckNo` bigint(16) unsigned default NULL,
  `plg_Problem` tinyint(1) default NULL,
  `plg_scanString` text collate latin1_general_ci,
  `plg_aut_ID` mediumint(9) NOT NULL default '0',
  `plg_aut_Cleared` tinyint(1) NOT NULL default '0',
  `plg_aut_ResultID` mediumint(9) NOT NULL default '0',
  `plg_NonDeductible` decimal(8,2) NOT NULL,
  PRIMARY KEY  (`plg_plgID`)
) TYPE=MyISAM  ;

INSERT IGNORE INTO `query_qry` VALUES (16, 'SELECT per_ID as AddToCart, CONCAT(\'<a href=PersonView.php?PersonID=\',per_ID,\'>\',per_FirstName,\' \',per_LastName,\'</a>\') AS Name FROM person_per LEFT JOIN person2group2role_p2g2r ON per_id = p2g2r_per_ID LEFT JOIN group_grp a ON grp_ID = p2g2r_grp_ID LEFT JOIN list_lst b ON lst_ID = grp_RoleListID AND p2g2r_rle_ID = lst_OptionID WHERE lst_OptionName = \'Teacher\'', 'Find Teachers', 'Find all people assigned to Sunday school classes as teachers', 1);
INSERT IGNORE INTO `query_qry` VALUES (17, 'SELECT per_ID as AddToCart, CONCAT(\'<a href=PersonView.php?PersonID=\',per_ID,\'>\',per_FirstName,\' \',per_LastName,\'</a>\') AS Name FROM person_per LEFT JOIN person2group2role_p2g2r ON per_id = p2g2r_per_ID LEFT JOIN group_grp a ON grp_ID = p2g2r_grp_ID LEFT JOIN list_lst b ON lst_ID = grp_RoleListID AND p2g2r_rle_ID = lst_OptionID WHERE lst_OptionName = \'Student\'', 'Find Students', 'Find all people assigned to Sunday school classes as students', 1);
INSERT IGNORE INTO `query_qry` VALUES (18,'SELECT per_ID as AddToCart, per_BirthDay as Day, CONCAT(per_FirstName,\' \',per_LastName) AS Name FROM person_per WHERE per_cls_ID=1 AND per_BirthMonth=~birthmonth~ ORDER BY per_BirthDay','Birthdays','Members with birthdays in a particular month',0);
INSERT IGNORE INTO `query_qry` VALUES (19, 'SELECT per_ID as AddToCart, CONCAT(\'<a href=PersonView.php?PersonID=\',per_ID,\'>\',per_FirstName,\' \',per_LastName,\'</a>\') AS Name FROM person_per LEFT JOIN person2group2role_p2g2r ON per_id = p2g2r_per_ID LEFT JOIN group_grp a ON grp_ID = p2g2r_grp_ID LEFT JOIN list_lst b ON lst_ID = grp_RoleListID AND p2g2r_rle_ID = lst_OptionID WHERE lst_OptionName = \'Student\' AND grp_ID = ~group~ ORDER BY per_LastName', 'Class Students', 'Find students for a particular class', 1);
INSERT IGNORE INTO `query_qry` VALUES (20, 'SELECT per_ID as AddToCart, CONCAT(\'<a href=PersonView.php?PersonID=\',per_ID,\'>\',per_FirstName,\' \',per_LastName,\'</a>\') AS Name FROM person_per LEFT JOIN person2group2role_p2g2r ON per_id = p2g2r_per_ID LEFT JOIN group_grp a ON grp_ID = p2g2r_grp_ID LEFT JOIN list_lst b ON lst_ID = grp_RoleListID AND p2g2r_rle_ID = lst_OptionID WHERE lst_OptionName = \'Teacher\' AND grp_ID = ~group~ ORDER BY per_LastName', 'Class Teachers', 'Find teachers for a particular class', 1);
INSERT IGNORE INTO `query_qry` VALUES (21, 'SELECT per_ID as AddToCart, CONCAT(\'<a href=PersonView.php?PersonID=\',per_ID,\'>\',per_FirstName,\' \',per_LastName,\'</a>\') AS Name FROM person_per LEFT JOIN person2group2role_p2g2r ON per_id = p2g2r_per_ID WHERE p2g2r_grp_ID=~group~ ORDER BY per_LastName', 'Registered students', 'Find Registered students', 1);
INSERT IGNORE INTO `query_qry` VALUES (22,'SELECT per_ID as AddToCart, DAYOFMONTH(per_MembershipDate) as Day, per_MembershipDate AS DATE, CONCAT(per_FirstName,\' \',per_LastName) AS Name FROM person_per WHERE per_cls_ID=1 AND MONTH(per_MembershipDate)=~membermonth~ ORDER BY per_MembershipDate','Membership anniversaries','Members who joined in a particular month',0);
INSERT IGNORE INTO `query_qry` VALUES (23,'SELECT usr_per_ID as AddToCart, CONCAT(a.per_FirstName,\' \',a.per_LastName) AS Name FROM user_usr LEFT JOIN person_per a ON per_ID=usr_per_ID ORDER BY per_LastName','Select database users','People who are registered as database users',0);
INSERT IGNORE INTO `query_qry` VALUES (24,'SELECT per_ID as AddToCart, CONCAT(\'<a href=PersonView.php?PersonID=\',per_ID,\'>\',per_FirstName,\' \',per_LastName,\'</a>\') AS Name FROM person_per WHERE per_cls_id =1','Select all members','People who are members',0);
INSERT IGNORE INTO `query_qry` VALUES (25, 'SELECT per_ID as AddToCart, CONCAT(\'<a href=PersonView.php?PersonID=\',per_ID,\'>\',per_FirstName,\' \',per_LastName,\'</a>\') AS Name FROM person_per LEFT JOIN person2volunteeropp_p2vo ON per_id = p2vo_per_ID WHERE p2vo_vol_ID = ~volopp~ ORDER BY per_LastName', 'Volunteers', 'Find volunteers for a particular opportunity', 1);
INSERT IGNORE INTO `query_qry` VALUES (26,'SELECT per_ID as AddToCart, CONCAT(per_FirstName,\' \',per_LastName) AS Name FROM person_per WHERE DATE_SUB(NOW(),INTERVAL ~friendmonths~ MONTH)<per_FriendDate ORDER BY per_MembershipDate','Recent friends','Friends who signed up in previous months',0);
INSERT IGNORE INTO `query_qry` VALUES (27,'SELECT per_ID as AddToCart, CONCAT(per_FirstName,\' \',per_LastName) AS Name FROM person_per inner join family_fam on per_fam_ID=fam_ID where per_fmr_ID<>3 AND fam_OkToCanvass="TRUE" ORDER BY fam_Zip','Families to Canvass','People in families that are ok to canvass.',0);
INSERT IGNORE INTO `query_qry` VALUES (28,'SELECT fam_Name, a.plg_amount as PlgFY1, b.plg_amount as PlgFY2 from family_fam left join pledge_plg a on a.plg_famID = fam_ID and a.plg_FYID=~fyid1~ and a.plg_PledgeOrPayment=\'Pledge\' left join pledge_plg b on b.plg_famID = fam_ID and b.plg_FYID=~fyid2~ and b.plg_PledgeOrPayment=\'Pledge\' order by fam_Name','Pledge comparison','Compare pledges between two fiscal years',1);

INSERT IGNORE INTO `queryparameteroptions_qpo` VALUES (10, 27, '2004/2005', '9');
INSERT IGNORE INTO `queryparameteroptions_qpo` VALUES (11, 27, '2005/2006', '10');
INSERT IGNORE INTO `queryparameteroptions_qpo` VALUES (12, 27, '2006/2007', '11');
INSERT IGNORE INTO `queryparameteroptions_qpo` VALUES (13, 27, '2007/2008', '12');

INSERT IGNORE INTO `queryparameteroptions_qpo` VALUES (14, 28, '2004/2005', '9');
INSERT IGNORE INTO `queryparameteroptions_qpo` VALUES (15, 28, '2005/2006', '10');
INSERT IGNORE INTO `queryparameteroptions_qpo` VALUES (16, 28, '2006/2007', '11');
INSERT IGNORE INTO `queryparameteroptions_qpo` VALUES (17, 28, '2007/2008', '12');

INSERT IGNORE INTO `queryparameters_qrp` VALUES (18,18,0,'','Month','The birthday month for which you would like records returned.','birthmonth','1',1,0,'',12,1,1,2);
INSERT IGNORE INTO `queryparameters_qrp` VALUES (19,19,2,'SELECT grp_ID AS Value, grp_Name AS Display FROM group_grp ORDER BY grp_Type','Class','The sunday school class for which you would like records returned.','group','1',1,0,'',12,1,1,2);
INSERT IGNORE INTO `queryparameters_qrp` VALUES (20,20,2,'SELECT grp_ID AS Value, grp_Name AS Display FROM group_grp ORDER BY grp_Type','Class','The sunday school class for which you would like records returned.','group','1',1,0,'',12,1,1,2);
INSERT IGNORE INTO `queryparameters_qrp` VALUES (21,21,2,'SELECT grp_ID AS Value, grp_Name AS Display FROM group_grp ORDER BY grp_Type','Registered students','Group of registered students','group','1',1,0,'',12,1,1,2);
INSERT IGNORE INTO `queryparameters_qrp` VALUES (22,22,0,'','Month','The membership anniversary month for which you would like records returned.','membermonth','1',1,0,'',12,1,1,2);
INSERT IGNORE INTO `queryparameters_qrp` VALUES (25,25,2,'SELECT vol_ID AS Value, vol_Name AS Display FROM volunteeropportunity_vol ORDER BY vol_Name','Volunteer opportunities','Choose a volunteer opportunity','volopp','1',1,0,'',12,1,1,2);

INSERT IGNORE INTO `queryparameters_qrp` VALUES (26,26,0,'','Months','Number of months since becoming a friend','friendmonths','1',1,0,'',24,1,1,2);

INSERT IGNORE INTO `queryparameters_qrp` VALUES (27,28,1,'','First Fiscal Year','First fiscal year for comparison','fyid1','9',1,0,'',12,9,0,0);
INSERT IGNORE INTO `queryparameters_qrp` VALUES (28,28,1,'','Second Fiscal Year','Second fiscal year for comparison','fyid2','9',1,0,'',12,9,0,0);

INSERT IGNORE INTO `query_qry` VALUES (100, 'SELECT a.per_ID as AddToCart, CONCAT(''<a href=PersonView.php?PersonID='',a.per_ID,''>'',a.per_FirstName,'' '',a.per_LastName,''</a>'') AS Name FROM person_per AS a LEFT JOIN person2volunteeropp_p2vo p2v1 ON (a.per_id = p2v1.p2vo_per_ID AND p2v1.p2vo_vol_ID = ~volopp1~) LEFT JOIN person2volunteeropp_p2vo p2v2 ON (a.per_id = p2v2.p2vo_per_ID AND p2v2.p2vo_vol_ID = ~volopp2~) WHERE p2v1.p2vo_per_ID=p2v2.p2vo_per_ID ORDER BY per_LastName', 'Volunteers', 'Find volunteers for who match two specific opportunity codes', 1);
INSERT IGNORE INTO `queryparameters_qrp` VALUES (100, 100, 2, 'SELECT vol_ID AS Value, vol_Name AS Display FROM volunteeropportunity_vol ORDER BY vol_Name', 'Volunteer opportunities', 'First volunteer opportunity choice', 'volopp1', '1', 1, 0, '', 12, 1, 1, 2);
INSERT IGNORE INTO `queryparameters_qrp` VALUES (101, 100, 2, 'SELECT vol_ID AS Value, vol_Name AS Display FROM volunteeropportunity_vol ORDER BY vol_Name', 'Volunteer opportunities', 'Second volunteer opportunity choice', 'volopp2', '1', 1, 0, '', 12, 1, 1, 2);

CREATE TABLE IF NOT EXISTS `result_res` (
  `res_ID` mediumint(9) NOT NULL auto_increment,
  `res_echotype1` text NOT NULL,
  `res_echotype2` text NOT NULL,
  `res_echotype3` text NOT NULL,
  `res_authorization` text NOT NULL,
  `res_order_number` text NOT NULL,
  `res_reference` text NOT NULL,
  `res_status` text NOT NULL,
  `res_avs_result` text NOT NULL,
  `res_security_result` text NOT NULL,
  `res_mac` text NOT NULL,
  `res_decline_code` text NOT NULL,
  `res_tran_date` text NOT NULL,
  `res_merchant_name` text NOT NULL,
  `res_version` text NOT NULL,
  `res_EchoServer` text NOT NULL,
  PRIMARY KEY  (`res_ID`)
) TYPE=MyISAM  ;

ALTER TABLE `user_usr` ADD COLUMN `usr_showPledges` tinyint(1) NOT NULL default '0';
ALTER TABLE `user_usr` ADD COLUMN `usr_showPayments` tinyint(1) NOT NULL default '0';
ALTER TABLE `user_usr` ADD COLUMN `usr_showSince` date NOT NULL default '0000-00-00';
ALTER TABLE `user_usr` ADD COLUMN `usr_defaultFY` mediumint(9) NOT NULL default '10';
ALTER TABLE `user_usr` ADD COLUMN `usr_currentDeposit` mediumint(9) NOT NULL default '0';
ALTER TABLE `user_usr` ADD COLUMN `usr_UserName` varchar(32) default NULL;
ALTER TABLE `user_usr` ADD COLUMN `usr_EditSelf` tinyint(3) unsigned NOT NULL default '0';
ALTER TABLE `user_usr` ADD COLUMN `usr_CalStart` date default NULL;
ALTER TABLE `user_usr` ADD COLUMN `usr_CalEnd` date default NULL;
ALTER TABLE `user_usr` ADD COLUMN `usr_CalNoSchool1` date default NULL;
ALTER TABLE `user_usr` ADD COLUMN `usr_CalNoSchool2` date default NULL;
ALTER TABLE `user_usr` ADD COLUMN `usr_CalNoSchool3` date default NULL;
ALTER TABLE `user_usr` ADD COLUMN `usr_CalNoSchool4` date default NULL;
ALTER TABLE `user_usr` ADD COLUMN `usr_CalNoSchool5` date default NULL;
ALTER TABLE `user_usr` ADD COLUMN `usr_CalNoSchool6` date default NULL;
ALTER TABLE `user_usr` ADD COLUMN `usr_CalNoSchool7` date default NULL;
ALTER TABLE `user_usr` ADD COLUMN `usr_CalNoSchool8` date default NULL;
ALTER TABLE `user_usr` ADD COLUMN `usr_SearchFamily` tinyint(3) default NULL;
ALTER TABLE `user_usr` ADD COLUMN `usr_Canvasser` tinyint(3) NOT NULL default '0';
ALTER TABLE `user_usr` ADD UNIQUE KEY `usr_UserName` (`usr_UserName`);

CREATE TABLE IF NOT EXISTS `volunteeropportunity_vol` (
  `vol_ID` tinyint(3) NOT NULL auto_increment,
  `vol_Active` enum('true','false') NOT NULL default 'true',
  `vol_Name` varchar(30) default NULL,
  `vol_Description` varchar(100) default NULL,
  PRIMARY KEY  (`vol_ID`),
  UNIQUE KEY `vol_ID` (`vol_ID`)
) TYPE=MyISAM  ;


CREATE TABLE IF NOT EXISTS `whycame_why` (
  `why_ID` mediumint(9) NOT NULL auto_increment,
  `why_per_ID` mediumint(9) NOT NULL default '0',
  `why_join` text NOT NULL,
  `why_come` text NOT NULL,
  `why_suggest` text NOT NULL,
  `why_hearOfUs` text NOT NULL,
  PRIMARY KEY  (`why_ID`)
) TYPE=MyISAM  ;
