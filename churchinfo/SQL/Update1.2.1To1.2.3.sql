
CREATE TABLE IF NOT EXISTS `config_cfg` (
  `cfg_id` int(11) NOT NULL default '0',
  `cfg_name` varchar(50) NOT NULL default '',
  `cfg_value` varchar(255) default NULL,
  `cfg_type` enum('text','number','date','boolean') NOT NULL default 'text',
  `cfg_default` varchar(255) NOT NULL default '',
  `cfg_tooltip` text NOT NULL,
  `cfg_section` varchar(50) NOT NULL default '',
  PRIMARY KEY  (`cfg_id`)
) TYPE=MyISAM;


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


ALTER TABLE  `pledge_plg` ADD  `plg_NonDeductible` DECIMAL( 8, 2 ) NOT NULL;

CREATE TABLE IF NOT EXISTS event_attend (
  event_id int(11) NOT NULL default '0',
  person_id int(11) NOT NULL default '0'
) TYPE=MyISAM;

CREATE TABLE IF NOT EXISTS event_types (
  type_id int(11) NOT NULL auto_increment,
  type_name varchar(255) NOT NULL default '',
  PRIMARY KEY  (type_id),
  UNIQUE KEY event_name (type_name)
) TYPE=MyISAM;

INSERT INTO `event_types` VALUES (1, 'Church Service');
INSERT INTO `event_types` VALUES (2, 'Sunday School');

CREATE TABLE IF NOT EXISTS events_event (
  event_id int(11) NOT NULL auto_increment,
  event_type int(11) NOT NULL default '0',
  event_title varchar(255) NOT NULL default '',
  event_desc varchar(255) default NULL,
  event_text text,
  event_start datetime NOT NULL default '0000-00-00 00:00:00',
  event_end datetime NOT NULL default '0000-00-00 00:00:00',
  inactive int(1) NOT NULL default '0',
  PRIMARY KEY  (event_id),
  FULLTEXT KEY event_txt (event_text)
) TYPE=MyISAM;