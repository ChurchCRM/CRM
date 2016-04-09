-- Install Version 2.0.0
-- --------------------------------------------------------

--
-- Table structure for table `autopayment_aut`
--

CREATE TABLE `autopayment_aut` (
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
  `aut_CreditCardVanco` varchar(50) default NULL,
  `aut_AccountVanco` varchar(50) default NULL,
  PRIMARY KEY  (`aut_ID`),
  UNIQUE KEY `aut_ID` (`aut_ID`)
) ENGINE=MyISAM CHARACTER SET utf8 COLLATE utf8_unicode_ci AUTO_INCREMENT=1 ;

--
-- Dumping data for table `autopayment_aut`
--


-- --------------------------------------------------------

--
-- Table structure for table `canvassdata_can`
--

CREATE TABLE `canvassdata_can` (
  `can_ID` mediumint(9) unsigned NOT NULL auto_increment,
  `can_famID` mediumint(9) NOT NULL default '0',
  `can_Canvasser` mediumint(9) NOT NULL default '0',
  `can_FYID` mediumint(9) default NULL,
  `can_date` date default NULL,
  `can_Positive` text,
  `can_Critical` text,
  `can_Insightful` text,
  `can_Financial` text,
  `can_Suggestion` text,
  `can_NotInterested` tinyint(1) NOT NULL default '0',
  `can_WhyNotInterested` text,
  PRIMARY KEY  (`can_ID`),
  UNIQUE KEY `can_ID` (`can_ID`)
) ENGINE=MyISAM CHARACTER SET utf8 COLLATE utf8_unicode_ci AUTO_INCREMENT=1 ;

--
-- Dumping data for table `canvassdata_can`
--


-- --------------------------------------------------------

--
-- Table structure for table `config_cfg`
--

CREATE TABLE `config_cfg` (
  `cfg_id` int(11) NOT NULL default '0',
  `cfg_name` varchar(50) NOT NULL default '',
  `cfg_value` text,
  `cfg_type` enum('text','number','date','boolean','textarea') NOT NULL default 'text',
  `cfg_default` text NOT NULL,
  `cfg_tooltip` text NOT NULL,
  `cfg_section` varchar(50) NOT NULL default '',
  `cfg_category` varchar(20) default NULL,
  PRIMARY KEY  (`cfg_id`),
  UNIQUE KEY `cfg_name` (`cfg_name`),
  KEY `cfg_id` (`cfg_id`)
) ENGINE=MyISAM CHARACTER SET utf8 COLLATE utf8_unicode_ci;

--
-- Dumping data for table `config_cfg`
--

INSERT INTO `config_cfg` (`cfg_id`, `cfg_name`, `cfg_value`, `cfg_type`, `cfg_default`, `cfg_tooltip`, `cfg_section`, `cfg_category`) VALUES
(1, 'sWEBCALENDARDB', '', 'text', '', 'WebCalendar database name', 'General', NULL),
(2, 'debug', '1', 'boolean', '1', 'Set debug mode\r\nThis may be helpful for when you''re first setting up ChurchCRM, but you should\r\nprobably turn it off for maximum security otherwise.  If you are having trouble,\r\nplease enable this so that you''ll know what the errors are.  This is especially\r\nimportant if you need to report a problem on the help forums.', 'General', NULL),
(3, 'sJPGRAPH_PATH', 'Include/jpgraph-1.13/src', 'text', 'Include/jpgraph-1.13/src', 'JPGraph library', 'General', NULL),
(4, 'sFPDF_PATH', 'vendor/fpdf17', 'text', 'vendor/fpdf17', 'FPDF library', 'General', NULL),
(5, 'sDirClassifications', '1,2,4,5', 'text', '1,2,4,5', 'Include only these classifications in the directory, comma seperated', 'General', NULL),
(6, 'sDirRoleHead', '1,7', 'text', '1,7', 'These are the family role numbers designated as head of house', 'General', NULL),
(7, 'sDirRoleSpouse', '2', 'text', '2', 'These are the family role numbers designated as spouse', 'General', NULL),
(8, 'sDirRoleChild', '3', 'text', '3', 'These are the family role numbers designated as child', 'General', NULL),
(9, 'sSessionTimeout', '3600', 'number', '3600', 'Session timeout length in seconds\rSet to zero to disable session timeouts.', 'General', NULL),
(10, 'aFinanceQueries', '28,30,31,32', 'text', '28', 'Queries for which user must have finance permissions to use:', 'General', NULL),
(11, 'bCSVAdminOnly', '1', 'boolean', '1', 'Should only administrators have access to the CSV export system and directory report?', 'General', NULL),
(12, 'sDefault_Pass', 'password', 'text', 'password', 'Default password for new users and those with reset passwords', 'General', NULL),
(13, 'sMinPasswordLength', '6', 'number', '6', 'Minimum length a user may set their password to', 'General', NULL),
(14, 'sMinPasswordChange', '4', 'number', '4', 'Minimum amount that a new password must differ from the old one (# of characters changed)\rSet to zero to disable this feature', 'General', NULL),
(15, 'sDisallowedPasswords', 'churchcrm,password,god,jesus,church,christian', 'text', 'churchcrm,password,god,jesus,church,christian', 'A comma-seperated list of disallowed (too obvious) passwords.', 'General', NULL),
(16, 'iMaxFailedLogins', '50', 'number', '50', 'Maximum number of failed logins to allow before a user account is locked.\rOnce the maximum has been reached, an administrator must re-enable the account.\rThis feature helps to protect against automated password guessing attacks.\rSet to zero to disable this feature.', 'General', NULL),
(20, 'iPDFOutputType', '1', 'number', '1', 'PDF handling mode.\r1 = Save File dialog\r2 = Open in current browser window', 'General', NULL),
(21, 'sDefaultCity', '', 'text', '', 'Default City', 'General', NULL),
(22, 'sDefaultState', '', 'text', '', 'Default State - Must be 2-letter abbreviation!', 'General', NULL),
(23, 'sDefaultCountry', 'United States', 'text', 'United States', 'Default Country', 'General', NULL),
(24, 'bEmailSend', '', 'boolean', '', 'If you wish to be able to send emails from within ChurchCRM. This requires\reither an SMTP server address to send from or sendmail installed in PHP.', 'General', NULL),
(25, 'sSendType', 'smtp', 'text', 'smtp', 'The method for sending email. Either "smtp" or "sendmail"', 'General', NULL),
(26, 'sToEmailAddress', '', 'text', '', 'Default account for receiving a copy of all emails', 'General', NULL),
(27, 'sSMTPHost', '', 'text', '', 'SMTP Server Address (mail.server.com:25)', 'General', NULL),
(28, 'sSMTPAuth', '1', 'boolean', '1', 'Does your SMTP server require auththentication (username/password)?', 'General', NULL),
(29, 'sSMTPUser', '', 'text', '', 'SMTP Username', 'General', NULL),
(30, 'sSMTPPass', '', 'text', '', 'SMTP Password', 'General', NULL),
(33, 'bShowFamilyData', '1', 'boolean', '1', 'Unavailable person info inherited from assigned family for display?\rThis option causes certain info from a person''s assigned family record to be\rdisplayed IF the corresponding info has NOT been entered for that person. ', 'General', NULL),
(36, 'sGZIPname', 'gzip', 'text', 'gzip', '', 'General', NULL),
(37, 'sZIPname', 'zip', 'text', 'zip', '', 'General', NULL),
(38, 'sPGPname', 'gpg', 'text', 'gpg', '', 'General', NULL),
(39, 'sLanguage', 'en_US', 'text', 'en_US', 'Internationalization (I18n) support\rUS English (en_US), Italian (it_IT), French (fr_FR), and German (de_DE)', 'General', NULL),
(40, 'iFYMonth', '1', 'number', '1', 'First month of the fiscal year', 'General', NULL),
(41, 'sXML_RPC_PATH', 'XML/RPC.php', 'text', 'XML/RPC.php', 'Path to RPC.php, required for Lat/Lon address lookup', 'General', NULL),
(42, 'sGeocoderID', '', 'text', '', 'User ID for rpc.geocoder.us', 'General', NULL),
(43, 'sGeocoderPW', '', 'text', '', 'Password for rpc.geocoder.us', 'General', NULL),
(44, 'sGoogleMapKey', '', 'text', '', 'Google map API requires a unique key from http://maps.google.com/apis/maps/signup.html', 'General', NULL),
(45, 'nChurchLatitude', '', 'number', '', 'Latitude of the church, used to center the Google map', 'General', NULL),
(46, 'nChurchLongitude', '', 'number', '', 'Longitude of the church, used to center the Google map', 'General', NULL),
(47, 'bHidePersonAddress', '1', 'boolean', '1', 'Set true to disable entering addresses in Person Editor.  Set false to enable entering addresses in Person Editor.', 'General', NULL),
(48, 'bHideFriendDate', '0', 'boolean', '0', 'Set true to disable entering Friend Date in Person Editor.  Set false to enable entering Friend Date in Person Editor.', 'General', NULL),
(49, 'bHideFamilyNewsletter', '0', 'boolean', '0', 'Set true to disable management of newsletter subscriptions in the Family Editor.', 'General', NULL),
(50, 'bHideWeddingDate', '0', 'boolean', '0', 'Set true to disable entering Wedding Date in Family Editor.  Set false to enable entering Wedding Date in Family Editor.', 'General', NULL),
(51, 'bHideLatLon', '0', 'boolean', '0', 'Set true to disable entering Latitude and Longitude in Family Editor.  Set false to enable entering Latitude and Longitude in Family Editor.  Lookups are still performed, just not displayed.', 'General', NULL),
(52, 'bUseDonationEnvelopes', '0', 'boolean', '0', 'Set true to enable use of donation envelopes', 'General', NULL),
(53, 'sHeader', '', 'textarea', '', 'Enter in HTML code which will be displayed as a header at the top of each page. Be sure to close your tags! Note: You must REFRESH YOUR BROWSER A SECOND TIME to view the new header.', 'General', NULL),
(54, 'sISTusername', 'username', 'text', 'username', 'Intelligent Search Technolgy, Ltd. CorrectAddress Username for https://www.intelligentsearch.com/Hosted/User', 'General', NULL),
(55, 'sISTpassword', '', 'text', '', 'Intelligent Search Technolgy, Ltd. CorrectAddress Password for https://www.intelligentsearch.com/Hosted/User', 'General', NULL),
(56, 'bUseGoogleGeocode', '1', 'boolean', '1', 'Set true to use the Google geocoder.  Set false to use rpc.geocoder.us.', 'General', NULL),
(57, 'iChecksPerDepositForm', '14', 'number', '14', 'Number of checks for Deposit Slip Report', 'General', NULL),
(58, 'bUseScannedChecks', '0', 'boolean', '0', 'Set true to enable use of scanned checks', 'General', NULL),
(61, 'iEventPeriodStartHr', '7', 'number', '7', 'Church Event Valid Period Start Hour (0-23)', 'General', NULL),
(62, 'iEventPeriodEndHr', '18', 'number', '18', 'Church Event Valid Period End Hour (0-23, must be greater than iEventStartHr)', 'General', NULL),
(63, 'iEventPeriodIntervalMin', '15', 'number', '15', 'Event Period interval (in minutes)', 'General', NULL),
(64, 'sDistanceUnit', 'miles', 'text', 'miles', 'Unit used to measure distance, miles or km.', 'General', NULL),
(65, 'sTimeZone', 'America/New_York', 'text', 'America/New_York', 'Time zone- see http://php.net/manual/en/timezones.php for valid choices.', 'General', NULL),
(66, 'sGMapIcons', 'red-dot,green-dot,purple,yellow-dot,blue-dot,orange,yellow,green,blue,red,pink,lightblue', 'text', 'red-dot,green-dot,purple,yellow-dot,blue-dot,orange,yellow,green,blue,red,pink,lightblue', 'Names of markers for Google Maps in order of classification', 'General',NULL),
(67, 'cfgForceUppercaseZip', '0', 'boolean', '0', 'Make user-entered zip/postcodes UPPERCASE when saving to the database. Useful in the UK.', 'General', NULL),
(2000, 'mailChimpApiKey', '', 'text', '', 'see http://kb.mailchimp.com/accounts/management/about-api-keys', 'General', NULL),


-- Save 68, 69 for MRBS if installed
-- Save 70, 71 for WebCalendar if installed
(72, 'bEnableNonDeductible', '0', 'boolean', '0', 'Enable non-deductible payments', 'General', NULL),
(73, 'sElectronicTransactionProcessor', 'Vanco', 'text', 'Vanco', 'Electronic Transaction Processor', 'General', NULL),
(999, 'bRegistered', '0', 'boolean', '0', 'ChurchCRM has been registered.  The ChurchCRM team uses registration information to track usage.  This information is kept confidential and never released or sold.  If this field is true the registration option in the admin menu changes to update registration.', 'General', NULL),
(1001, 'leftX', '20', 'number', '20', 'Left Margin (1 = 1/100th inch)', 'ChurchInfoReport', NULL),
(1002, 'incrementY', '4', 'number', '4', 'Line Thickness (1 = 1/100th inch', 'ChurchInfoReport', NULL),
(1003, 'sChurchName', 'Some Church', 'text', '', 'Church Name', 'ChurchInfoReport', NULL),
(1004, 'sChurchAddress', '100 Main St', 'text', '', 'Church Address', 'ChurchInfoReport', NULL),
(1005, 'sChurchCity', 'Wall', 'text', '', 'Church City', 'ChurchInfoReport', NULL),
(1006, 'sChurchState', 'SD', 'text', '', 'Church State', 'ChurchInfoReport', NULL),
(1007, 'sChurchZip', '11111', 'text', '', 'Church Zip', 'ChurchInfoReport', NULL),
(1008, 'sChurchPhone', '123-456-7890', 'text', '', 'Church Phone', 'ChurchInfoReport', NULL),
(1009, 'sChurchEmail', 'church@church.org', 'text', '', 'Church Email', 'ChurchInfoReport', NULL),
(1010, 'sHomeAreaCode', '111', 'text', '', 'Home area code of the church', 'ChurchInfoReport', NULL),
(1011, 'sTaxReport1', 'This letter shows our record of your payments for', 'text', 'This letter shows our record of your payments for', 'Verbage for top line of tax report. Dates will be appended to the end of this line.', 'ChurchInfoReport', NULL),
(1012, 'sTaxReport2', 'Thank you for your help in making a difference. We greatly appreciate your gift!', 'text', 'Thank you for your help in making a difference. We greatly appreciate your gift!', 'Verbage for bottom line of tax report.', 'ChurchInfoReport', NULL),
(1013, 'sTaxReport3', 'If you have any questions or corrections to make to this report, please contact the church at the above number during business hours, 9am to 4pm, M-F.', 'text', 'If you have any questions or corrections to make to this report, please contact the church at the above number during business hours, 9am to 4pm, M-F.', 'Verbage for bottom line of tax report.', 'ChurchInfoReport', NULL),
(1014, 'sTaxSigner', 'Elder Joe Smith', 'text', '', 'Tax Report signer', 'ChurchInfoReport', NULL),
(1015, 'sReminder1', 'This letter shows our record of your pledge and payments for fiscal year', 'text', 'This letter shows our record of your pledge and payments for fiscal year', 'Verbage for the pledge reminder report', 'ChurchInfoReport', NULL),
(1016, 'sReminderSigner', 'Elder Joe Smith', 'text', '', 'Pledge Reminder Signer', 'ChurchInfoReport', NULL),
(1017, 'sReminderNoPledge', 'Pledges: We do not have record of a pledge for from you for this fiscal year.', 'text', 'Pledges: We do not have record of a pledge for from you for this fiscal year.', 'Verbage for the pledge reminder report - No record of a pledge', 'ChurchInfoReport', NULL),
(1018, 'sReminderNoPayments', 'Payments: We do not have record of a pledge for from you for this fiscal year.', 'text', 'Payments: We do not have record of a pledge for from you for this fiscal year.', 'Verbage for the pledge reminder report - No record of payments', 'ChurchInfoReport', NULL),
(1019, 'sConfirm1', 'This letter shows the information we have in our database with respect to your family.  Please review, mark-up as necessary, and return this form to the church office.', 'text', 'This letter shows the information we have in our database with respect to your family.  Please review, mark-up as necessary, and return this form to the church office.', 'Verbage for the database information confirmation and correction report', 'ChurchInfoReport', NULL),
(1020, 'sConfirm2', 'Thank you very much for helping us to update this information.  If you want on-line access to the church database please provide your email address and a desired password and we will send instructions.', 'text', 'Thank you very much for helping us to update this information.  If you want on-line access to the church database please provide your email address and a desired password and we will send instructions.', 'Verbage for the database information confirmation and correction report', 'ChurchInfoReport', NULL),
(1021, 'sConfirm3', 'Email _____________________________________ Password ________________', 'text', 'Email _____________________________________ Password ________________', 'Verbage for the database information confirmation and correction report', 'ChurchInfoReport', NULL),
(1022, 'sConfirm4', '[  ] I no longer want to be associated with the church (check here to be removed from our records).', 'text', '[  ] I no longer want to be associated with the church (check here to be removed from our records).', 'Verbage for the database information confirmation and correction report', 'ChurchInfoReport', NULL),
(1023, 'sConfirm5', '', 'text', '', 'Verbage for the database information confirmation and correction report', 'ChurchInfoReport', NULL),
(1024, 'sConfirm6', '', 'text', '', 'Verbage for the database information confirmation and correction report', 'ChurchInfoReport', NULL),
(1025, 'sConfirmSigner', 'Elder Joe Smith', 'text', '', 'Database information confirmation and correction report signer', 'ChurchInfoReport', NULL),
(1026, 'sPledgeSummary1', 'Summary of pledges and payments for the fiscal year', 'text', 'Summary of pledges and payments for the fiscal year', 'Verbage for the pledge summary report', 'ChurchInfoReport', NULL),
(1027, 'sPledgeSummary2', 'as of', 'text', ' as of', 'Verbage for the pledge summary report', 'ChurchInfoReport', NULL),
(1028, 'sDirectoryDisclaimer1', 'Every effort was made to insure the accuracy of this directory.  If there are any errors or omissions, please contact the church office.This directory is for the use of the people of', 'text', 'Every effort was made to insure the accuracy of this directory.  If there are any errors or omissions, please contact the church office.\n\nThis directory is for the use of the people of', 'Verbage for the directory report', 'ChurchInfoReport', NULL),
(1029, 'sDirectoryDisclaimer2', ', and the information contained in it may not be used for business or commercial purposes.', 'text', ', and the information contained in it may not be used for business or commercial purposes.', 'Verbage for the directory report', 'ChurchInfoReport', NULL),
(1030, 'bDirLetterHead', '../Images/church_letterhead.jpg', 'text', '../Images/church_letterhead.jpg', 'Church Letterhead path and file', 'ChurchInfoReport', NULL),
(1031, 'sZeroGivers', 'This letter shows our record of your payments for', 'text', 'This letter shows our record of your payments for', 'Verbage for top line of tax report. Dates will be appended to the end of this line.', 'ChurchInfoReport', NULL),
(1032, 'sZeroGivers2', 'Thank you for your help in making a difference. We greatly appreciate your gift!', 'text', 'Thank you for your help in making a difference. We greatly appreciate your gift!', 'Verbage for bottom line of tax report.', 'ChurchInfoReport', NULL),
(1033, 'sZeroGivers3', 'If you have any questions or corrections to make to this report, please contact the church at the above number during business hours, 9am to 4pm, M-F.', 'text', 'If you have any questions or corrections to make to this report, please contact the church at the above number during business hours, 9am to 4pm, M-F.', 'Verbage for bottom line of tax report.', 'ChurchInfoReport', NULL),
(1034, 'sChurchChkAcctNum', '111111111', 'text', '', 'Church Checking Account Number', 'ChurchInfoReport', NULL);

ALTER TABLE `config_cfg`
ADD COLUMN `cfg_order` INT NULL COMMENT '' AFTER `cfg_category`;

UPDATE `config_cfg` SET `cfg_category`='Step1', `cfg_order`='0' WHERE `cfg_id`='1003';
UPDATE `config_cfg` SET `cfg_category`='Step1', `cfg_order`='1' WHERE `cfg_id`='1004';
UPDATE `config_cfg` SET `cfg_category`='Step1', `cfg_order`='2' WHERE `cfg_id`='1005';
UPDATE `config_cfg` SET `cfg_category`='Step1', `cfg_order`='3' WHERE `cfg_id`='1006';
UPDATE `config_cfg` SET `cfg_category`='Step1', `cfg_order`='4' WHERE `cfg_id`='1007';
UPDATE `config_cfg` SET `cfg_category`='Step1', `cfg_order`='5' WHERE `cfg_id`='1008';
UPDATE `config_cfg` SET `cfg_category`='Step1', `cfg_order`='7' WHERE `cfg_id`='1009';
UPDATE `config_cfg` SET `cfg_category`='Step1', `cfg_order`='6' WHERE `cfg_id`='1010';
UPDATE `config_cfg` SET `cfg_category`='Step1', `cfg_order`='8' WHERE `cfg_id`='65';
UPDATE `config_cfg` SET `cfg_category`='Step1', `cfg_order`='9' WHERE `cfg_id`='45';
UPDATE `config_cfg` SET `cfg_category`='Step1', `cfg_order`='10' WHERE `cfg_id`='46';

UPDATE `config_cfg` SET `cfg_category`='Step2', `cfg_order`='0' WHERE `cfg_id`='12';
UPDATE `config_cfg` SET `cfg_category`='Step2', `cfg_order`='1' WHERE `cfg_id`='13';
UPDATE `config_cfg` SET `cfg_category`='Step2', `cfg_order`='3' WHERE `cfg_id`='14';
UPDATE `config_cfg` SET `cfg_category`='Step2', `cfg_order`='4' WHERE `cfg_id`='16';
UPDATE `config_cfg` SET `cfg_category`='Step2', `cfg_order`='5' WHERE `cfg_id`='9';
UPDATE `config_cfg` SET `cfg_category`='Step2', `cfg_order`='6' WHERE `cfg_id`='15';

UPDATE `config_cfg` SET `cfg_category`='Step3', `cfg_order`='1' WHERE `cfg_id`='25';
UPDATE `config_cfg` SET `cfg_category`='Step3', `cfg_order`='2' WHERE `cfg_id`='27';
UPDATE `config_cfg` SET `cfg_category`='Step3', `cfg_order`='3' WHERE `cfg_id`='28';
UPDATE `config_cfg` SET `cfg_category`='Step3', `cfg_order`='4' WHERE `cfg_id`='29';
UPDATE `config_cfg` SET `cfg_category`='Step3', `cfg_order`='5' WHERE `cfg_id`='30';
UPDATE `config_cfg` SET `cfg_category`='Step3', `cfg_order`='7' WHERE `cfg_id`='31';
UPDATE `config_cfg` SET `cfg_category`='Step3', `cfg_order`='0' WHERE `cfg_id`='24';
UPDATE `config_cfg` SET `cfg_category`='Step3', `cfg_order`='6' WHERE `cfg_id`='26';
UPDATE `config_cfg` SET `cfg_category`='Step3', `cfg_order`='8' WHERE `cfg_id`='2000';

UPDATE `config_cfg` SET `cfg_category`='Step4', `cfg_order`='0' WHERE `cfg_id`='5';
UPDATE `config_cfg` SET `cfg_category`='Step4', `cfg_order`='1' WHERE `cfg_id`='6';
UPDATE `config_cfg` SET `cfg_category`='Step4', `cfg_order`='2' WHERE `cfg_id`='7';
UPDATE `config_cfg` SET `cfg_category`='Step4', `cfg_order`='3' WHERE `cfg_id`='8';
UPDATE `config_cfg` SET `cfg_category`='Step4', `cfg_order`='4' WHERE `cfg_id`='21';
UPDATE `config_cfg` SET `cfg_category`='Step4', `cfg_order`='5' WHERE `cfg_id`='22';
UPDATE `config_cfg` SET `cfg_category`='Step4', `cfg_order`='6' WHERE `cfg_id`='23';
UPDATE `config_cfg` SET `cfg_category`='Step4', `cfg_order`='7' WHERE `cfg_id`='33';
UPDATE `config_cfg` SET `cfg_category`='Step4', `cfg_order`='8' WHERE `cfg_id`='47';
UPDATE `config_cfg` SET `cfg_category`='Step4', `cfg_order`='9' WHERE `cfg_id`='48';
UPDATE `config_cfg` SET `cfg_category`='Step4', `cfg_order`='10' WHERE `cfg_id`='49';
UPDATE `config_cfg` SET `cfg_category`='Step4', `cfg_order`='11' WHERE `cfg_id`='50';
UPDATE `config_cfg` SET `cfg_category`='Step4', `cfg_order`='12' WHERE `cfg_id`='51';
UPDATE `config_cfg` SET `cfg_category`='Step4', `cfg_order`='13' WHERE `cfg_id`='67';
UPDATE `config_cfg` SET `cfg_category`='Step4', `cfg_order`='15' WHERE `cfg_id`='19';

UPDATE `config_cfg` SET `cfg_category`='Step5', `cfg_order`='0' WHERE `cfg_id`='2';
UPDATE `config_cfg` SET `cfg_category`='Step5', `cfg_order`='1' WHERE `cfg_id`='35';
UPDATE `config_cfg` SET `cfg_category`='Step5', `cfg_order`='2' WHERE `cfg_id`='999';
UPDATE `config_cfg` SET `cfg_category`='Step5', `cfg_order`='3' WHERE `cfg_id`='39';
UPDATE `config_cfg` SET `cfg_category`='Step5', `cfg_order`='4' WHERE `cfg_id`='4';
UPDATE `config_cfg` SET `cfg_category`='Step5', `cfg_order`='5' WHERE `cfg_id`='3';
UPDATE `config_cfg` SET `cfg_category`='Step5', `cfg_order`='6' WHERE `cfg_id`='41';
UPDATE `config_cfg` SET `cfg_category`='Step5', `cfg_order`='7' WHERE `cfg_id`='36';
UPDATE `config_cfg` SET `cfg_category`='Step5', `cfg_order`='8' WHERE `cfg_id`='37';
UPDATE `config_cfg` SET `cfg_category`='Step5', `cfg_order`='9' WHERE `cfg_id`='38';
UPDATE `config_cfg` SET `cfg_category`='Step5', `cfg_order`='10' WHERE `cfg_id`='34';
UPDATE `config_cfg` SET `cfg_category`='Step5', `cfg_order`='12' WHERE `cfg_id`='64';
UPDATE `config_cfg` SET `cfg_category`='Step5', `cfg_order`='11' WHERE `cfg_id`='11';
UPDATE `config_cfg` SET `cfg_category`='Step5', `cfg_order`='13' WHERE `cfg_id`='1';
UPDATE `config_cfg` SET `cfg_category`='Step5', `cfg_order`='14' WHERE `cfg_id`='53';

UPDATE `config_cfg` SET `cfg_category`='Step6', `cfg_order`='0' WHERE `cfg_id`='44';
UPDATE `config_cfg` SET `cfg_category`='Step6', `cfg_order`='1' WHERE `cfg_id`='56';
UPDATE `config_cfg` SET `cfg_category`='Step6', `cfg_order`='2' WHERE `cfg_id`='66';
UPDATE `config_cfg` SET `cfg_category`='Step6', `cfg_order`='3' WHERE `cfg_id`='54';
UPDATE `config_cfg` SET `cfg_category`='Step6', `cfg_order`='4' WHERE `cfg_id`='55';
UPDATE `config_cfg` SET `cfg_category`='Step6', `cfg_order`='5' WHERE `cfg_id`='42';
UPDATE `config_cfg` SET `cfg_category`='Step6', `cfg_order`='6' WHERE `cfg_id`='43';

UPDATE `config_cfg` SET `cfg_category`='Step7', `cfg_order`='0' WHERE `cfg_id`='1001';
UPDATE `config_cfg` SET `cfg_category`='Step7', `cfg_order`='1' WHERE `cfg_id`='1002';
UPDATE `config_cfg` SET `cfg_category`='Step7', `cfg_order`='2' WHERE `cfg_id`='1011';
UPDATE `config_cfg` SET `cfg_category`='Step7', `cfg_order`='3' WHERE `cfg_id`='1012';
UPDATE `config_cfg` SET `cfg_category`='Step7', `cfg_order`='4' WHERE `cfg_id`='1013';
UPDATE `config_cfg` SET `cfg_category`='Step7', `cfg_order`='5' WHERE `cfg_id`='1014';
UPDATE `config_cfg` SET `cfg_category`='Step7', `cfg_order`='6' WHERE `cfg_id`='1015';
UPDATE `config_cfg` SET `cfg_category`='Step7', `cfg_order`='7' WHERE `cfg_id`='1016';
UPDATE `config_cfg` SET `cfg_category`='Step7', `cfg_order`='8' WHERE `cfg_id`='1017';
UPDATE `config_cfg` SET `cfg_category`='Step7', `cfg_order`='9' WHERE `cfg_id`='1018';
UPDATE `config_cfg` SET `cfg_category`='Step7', `cfg_order`='10' WHERE `cfg_id`='1019';
UPDATE `config_cfg` SET `cfg_category`='Step7', `cfg_order`='11' WHERE `cfg_id`='1020';
UPDATE `config_cfg` SET `cfg_category`='Step7', `cfg_order`='12' WHERE `cfg_id`='1021';
UPDATE `config_cfg` SET `cfg_category`='Step7', `cfg_order`='13' WHERE `cfg_id`='1022';
UPDATE `config_cfg` SET `cfg_category`='Step7', `cfg_order`='14' WHERE `cfg_id`='1023';
UPDATE `config_cfg` SET `cfg_category`='Step7', `cfg_order`='15' WHERE `cfg_id`='1024';
UPDATE `config_cfg` SET `cfg_category`='Step7', `cfg_order`='16' WHERE `cfg_id`='1025';
UPDATE `config_cfg` SET `cfg_category`='Step7', `cfg_order`='17' WHERE `cfg_id`='1026';
UPDATE `config_cfg` SET `cfg_category`='Step7', `cfg_order`='18' WHERE `cfg_id`='1027';
UPDATE `config_cfg` SET `cfg_category`='Step7', `cfg_order`='19' WHERE `cfg_id`='1028';
UPDATE `config_cfg` SET `cfg_category`='Step7', `cfg_order`='20' WHERE `cfg_id`='1029';
UPDATE `config_cfg` SET `cfg_category`='Step7', `cfg_order`='21' WHERE `cfg_id`='1030';
UPDATE `config_cfg` SET `cfg_category`='Step7', `cfg_order`='22' WHERE `cfg_id`='1031';
UPDATE `config_cfg` SET `cfg_category`='Step7', `cfg_order`='23' WHERE `cfg_id`='1032';
UPDATE `config_cfg` SET `cfg_category`='Step7', `cfg_order`='24' WHERE `cfg_id`='1033';
UPDATE `config_cfg` SET `cfg_category`='Step7', `cfg_order`='25' WHERE `cfg_id`='1034';

UPDATE `config_cfg` SET `cfg_category`='Step8', `cfg_order`='0' WHERE `cfg_id`='20';
UPDATE `config_cfg` SET `cfg_category`='Step8', `cfg_order`='1' WHERE `cfg_id`='40';
UPDATE `config_cfg` SET `cfg_category`='Step8', `cfg_order`='2' WHERE `cfg_id`='52';
UPDATE `config_cfg` SET `cfg_category`='Step8', `cfg_order`='3' WHERE `cfg_id`='57';
UPDATE `config_cfg` SET `cfg_category`='Step8', `cfg_order`='4' WHERE `cfg_id`='58';
UPDATE `config_cfg` SET `cfg_category`='Step8', `cfg_order`='5' WHERE `cfg_id`='73';
UPDATE `config_cfg` SET `cfg_category`='Step8', `cfg_order`='6' WHERE `cfg_id`='61';
UPDATE `config_cfg` SET `cfg_category`='Step8', `cfg_order`='7' WHERE `cfg_id`='62';
UPDATE `config_cfg` SET `cfg_category`='Step8', `cfg_order`='8' WHERE `cfg_id`='63';
UPDATE `config_cfg` SET `cfg_category`='Step8', `cfg_order`='9' WHERE `cfg_id`='72';
UPDATE `config_cfg` SET `cfg_category`='Step8', `cfg_order`='10' WHERE `cfg_id`='10';

UPDATE `config_cfg` SET `cfg_type`='textarea' WHERE `cfg_id`='1011';
UPDATE `config_cfg` SET `cfg_type`='textarea' WHERE `cfg_id`='1012';
UPDATE `config_cfg` SET `cfg_type`='textarea' WHERE `cfg_id`='1013';
UPDATE `config_cfg` SET `cfg_type`='textarea' WHERE `cfg_id`='1015';
UPDATE `config_cfg` SET `cfg_type`='textarea' WHERE `cfg_id`='1017';
UPDATE `config_cfg` SET `cfg_type`='textarea' WHERE `cfg_id`='1018';
UPDATE `config_cfg` SET `cfg_type`='textarea' WHERE `cfg_id`='1019';
UPDATE `config_cfg` SET `cfg_type`='textarea' WHERE `cfg_id`='1020';
UPDATE `config_cfg` SET `cfg_type`='textarea' WHERE `cfg_id`='1021';
UPDATE `config_cfg` SET `cfg_type`='textarea' WHERE `cfg_id`='1022';
UPDATE `config_cfg` SET `cfg_type`='textarea' WHERE `cfg_id`='1023';
UPDATE `config_cfg` SET `cfg_type`='textarea' WHERE `cfg_id`='1024';
UPDATE `config_cfg` SET `cfg_type`='textarea' WHERE `cfg_id`='1026';
UPDATE `config_cfg` SET `cfg_type`='textarea' WHERE `cfg_id`='1027';
UPDATE `config_cfg` SET `cfg_type`='textarea' WHERE `cfg_id`='1028';
UPDATE `config_cfg` SET `cfg_type`='textarea' WHERE `cfg_id`='1029';
UPDATE `config_cfg` SET `cfg_type`='textarea' WHERE `cfg_id`='1031';
UPDATE `config_cfg` SET `cfg_type`='textarea' WHERE `cfg_id`='1032';
UPDATE `config_cfg` SET `cfg_type`='textarea' WHERE `cfg_id`='1033';
-- --------------------------------------------------------

--
-- Table structure for table `deposit_dep`
--

CREATE TABLE `deposit_dep` (
  `dep_ID` mediumint(9) unsigned NOT NULL auto_increment,
  `dep_Date` date default NULL,
  `dep_Comment` text,
  `dep_EnteredBy` mediumint(9) unsigned default NULL,
  `dep_Closed` tinyint(1) NOT NULL default '0',
  `dep_Type` enum('Bank','CreditCard','BankDraft','eGive') NOT NULL default 'Bank',
  PRIMARY KEY  (`dep_ID`)
) ENGINE=MyISAM CHARACTER SET utf8 COLLATE utf8_unicode_ci PACK_KEYS=0 AUTO_INCREMENT=1 ;

--
-- Dumping data for table `deposit_dep`
--


-- --------------------------------------------------------

--
-- Table structure for table `donationfund_fun`
--

CREATE TABLE `donationfund_fun` (
  `fun_ID` tinyint(3) NOT NULL auto_increment,
  `fun_Active` enum('true','false') NOT NULL default 'true',
  `fun_Name` varchar(30) default NULL,
  `fun_Description` varchar(100) default NULL,
  PRIMARY KEY  (`fun_ID`),
  UNIQUE KEY `fun_ID` (`fun_ID`)
) ENGINE=MyISAM CHARACTER SET utf8 COLLATE utf8_unicode_ci  AUTO_INCREMENT=2 ;

--
-- Dumping data for table `donationfund_fun`
--

INSERT INTO `donationfund_fun` (`fun_ID`, `fun_Active`, `fun_Name`, `fun_Description`) VALUES
(1, 'true', 'Pledges', 'Pledge income for the operating budget');

-- --------------------------------------------------------

--
-- Table structure for table `email_message_pending_emp`
--

CREATE TABLE `email_message_pending_emp` (
  `emp_usr_id` mediumint(9) unsigned NOT NULL default '0',
  `emp_to_send` smallint(5) unsigned NOT NULL default '0',
  `emp_subject` varchar(128) NOT NULL,
  `emp_message` text NOT NULL,
  `emp_attach_name` text NULL,
  `emp_attach` tinyint(1)
) ENGINE=MyISAM CHARACTER SET utf8 COLLATE utf8_unicode_ci;

--
-- Dumping data for table `email_message_pending_emp`
--


-- --------------------------------------------------------

--
-- Table structure for table `email_recipient_pending_erp`
--

CREATE TABLE `email_recipient_pending_erp` (
  `erp_id` smallint(5) unsigned NOT NULL default '0',
  `erp_usr_id` mediumint(9) unsigned NOT NULL default '0',
  `erp_num_attempt` smallint(5) unsigned NOT NULL default '0',
  `erp_failed_time` datetime default NULL,
  `erp_email_address` varchar(50) NOT NULL default ''
) ENGINE=MyISAM CHARACTER SET utf8 COLLATE utf8_unicode_ci;

--
-- Dumping data for table `email_recipient_pending_erp`
--


-- --------------------------------------------------------

--
-- Table structure for table `eventcountnames_evctnm`
--

CREATE TABLE `eventcountnames_evctnm` (
  `evctnm_countid` int(5) NOT NULL auto_increment,
  `evctnm_eventtypeid` smallint(5) NOT NULL default '0',
  `evctnm_countname` varchar(20) NOT NULL default '',
  `evctnm_notes` varchar(20) NOT NULL default '',
  UNIQUE KEY `evctnm_countid` (`evctnm_countid`),
  UNIQUE KEY `evctnm_eventtypeid` (`evctnm_eventtypeid`,`evctnm_countname`)
) ENGINE=MyISAM CHARACTER SET utf8 COLLATE utf8_unicode_ci  AUTO_INCREMENT=7 ;

--
-- Dumping data for table `eventcountnames_evctnm`
--

INSERT INTO `eventcountnames_evctnm` (`evctnm_countid`, `evctnm_eventtypeid`, `evctnm_countname`, `evctnm_notes`) VALUES
(1, 1, 'Total', ''),
(2, 1, 'Members', ''),
(3, 1, 'Visitors', ''),
(4, 2, 'Total', ''),
(5, 2, 'Members', ''),
(6, 2, 'Visitors', '');

-- --------------------------------------------------------

--
-- Table structure for table `eventcounts_evtcnt`
--

CREATE TABLE `eventcounts_evtcnt` (
  `evtcnt_eventid` int(5) NOT NULL default '0',
  `evtcnt_countid` int(5) NOT NULL default '0',
  `evtcnt_countname` varchar(20) default NULL,
  `evtcnt_countcount` int(6) default NULL,
  `evtcnt_notes` varchar(20) default NULL,
  PRIMARY KEY  (`evtcnt_eventid`,`evtcnt_countid`)
) ENGINE=MyISAM CHARACTER SET utf8 COLLATE utf8_unicode_ci;

--
-- Dumping data for table `eventcounts_evtcnt`
--


-- --------------------------------------------------------

--
-- Table structure for table `events_event`
--

CREATE TABLE `events_event` (
  `event_id` int(11) NOT NULL auto_increment,
  `event_type` int(11) NOT NULL default '0',
  `event_title` varchar(255) NOT NULL default '',
  `event_desc` varchar(255) default NULL,
  `event_text` text,
  `event_start` datetime NOT NULL default '0000-00-00 00:00:00',
  `event_end` datetime NOT NULL default '0000-00-00 00:00:00',
  `inactive` int(1) NOT NULL default '0',
  `event_typename` varchar(40) NOT NULL default '',
  PRIMARY KEY  (`event_id`),
  FULLTEXT KEY `event_txt` (`event_text`)
) ENGINE=MyISAM CHARACTER SET utf8 COLLATE utf8_unicode_ci AUTO_INCREMENT=1 ;

--
-- Dumping data for table `events_event`
--


-- --------------------------------------------------------

--
-- Table structure for table `event_attend`
--

CREATE TABLE `event_attend` (
  `event_id` int(11) NOT NULL default '0',
  `person_id` int(11) NOT NULL default '0',
  `checkin_date` datetime default NULL,
  `checkin_id` int(11) default NULL,
  `checkout_date` datetime default NULL,
  `checkout_id` int(11) default NULL,
  UNIQUE KEY `event_id` (`event_id`,`person_id`)
) ENGINE=MyISAM CHARACTER SET utf8 COLLATE utf8_unicode_ci;

--
-- Dumping data for table `event_attend`
--


-- --------------------------------------------------------

--
-- Table structure for table `event_types`
--

CREATE TABLE `event_types` (
  `type_id` int(11) NOT NULL auto_increment,
  `type_name` varchar(255) NOT NULL default '',
  `type_defstarttime` time NOT NULL default '00:00:00',
  `type_defrecurtype` enum('none','weekly','monthly','yearly') NOT NULL default 'none',
  `type_defrecurDOW` enum('Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday') NOT NULL default 'Sunday',
  `type_defrecurDOM` char(2) NOT NULL default '0',
  `type_defrecurDOY` date NOT NULL default '0000-00-00',
  `type_active` int(1) NOT NULL default '1',
  PRIMARY KEY  (`type_id`)
) ENGINE=MyISAM CHARACTER SET utf8 COLLATE utf8_unicode_ci  AUTO_INCREMENT=3 ;

--
-- Dumping data for table `event_types`
--

INSERT INTO `event_types` (`type_id`, `type_name`, `type_defstarttime`, `type_defrecurtype`, `type_defrecurDOW`, `type_defrecurDOM`, `type_defrecurDOY`, `type_active`) VALUES
(1, 'Church Service', '10:30:00', 'weekly', 'Sunday', '', '0000-00-00', 1),
(2, 'Sunday School', '09:30:00', 'weekly', 'Sunday', '', '0000-00-00', 1);

-- --------------------------------------------------------

--
-- Table structure for table `family_custom`
--

CREATE TABLE `family_custom` (
  `fam_ID` mediumint(9) NOT NULL default '0',
  PRIMARY KEY  (`fam_ID`)
) ENGINE=MyISAM CHARACTER SET utf8 COLLATE utf8_unicode_ci;

--
-- Dumping data for table `family_custom`
--


-- --------------------------------------------------------

--
-- Table structure for table `family_custom_master`
--

CREATE TABLE `family_custom_master` (
  `fam_custom_Order` smallint(6) NOT NULL default '0',
  `fam_custom_Field` varchar(5) NOT NULL default '',
  `fam_custom_Name` varchar(40) NOT NULL default '',
  `fam_custom_Special` mediumint(8) unsigned default NULL,
  `fam_custom_Side` enum('left','right') NOT NULL default 'left',
  `fam_custom_FieldSec` tinyint(4) NOT NULL default '1',
  `type_ID` tinyint(4) NOT NULL default '0'
) ENGINE=MyISAM CHARACTER SET utf8 COLLATE utf8_unicode_ci;

--
-- Dumping data for table `family_custom_master`
--


-- --------------------------------------------------------

--
-- Table structure for table `family_fam`
--

CREATE TABLE `family_fam` (
  `fam_ID` mediumint(9) unsigned NOT NULL auto_increment,
  `fam_Name` varchar(50) default NULL,
  `fam_Address1` varchar(255) default NULL,
  `fam_Address2` varchar(255) default NULL,
  `fam_City` varchar(50) default NULL,
  `fam_State` varchar(50) default NULL,
  `fam_Zip` varchar(50) default NULL,
  `fam_Country` varchar(50) default NULL,
  `fam_HomePhone` varchar(30) default NULL,
  `fam_WorkPhone` varchar(30) default NULL,
  `fam_CellPhone` varchar(30) default NULL,
  `fam_Email` varchar(100) default NULL,
  `fam_WeddingDate` date default NULL,
  `fam_DateEntered` datetime NOT NULL default '0000-00-00 00:00:00',
  `fam_DateLastEdited` datetime default NULL,
  `fam_EnteredBy` smallint(5) unsigned NOT NULL default '0',
  `fam_EditedBy` smallint(5) unsigned default '0',
  `fam_scanCheck` text,
  `fam_scanCredit` text,
  `fam_SendNewsLetter` enum('FALSE','TRUE') NOT NULL default 'FALSE',
  `fam_DateDeactivated` date default NULL,
  `fam_OkToCanvass` enum('FALSE','TRUE') NOT NULL default 'FALSE',
  `fam_Canvasser` smallint(5) unsigned NOT NULL default '0',
  `fam_Latitude` double default NULL,
  `fam_Longitude` double default NULL,
  `fam_Envelope` mediumint(9) NOT NULL default '0',
  PRIMARY KEY  (`fam_ID`),
  KEY `fam_ID` (`fam_ID`)
) ENGINE=MyISAM CHARACTER SET utf8 COLLATE utf8_unicode_ci AUTO_INCREMENT=1 ;

--
-- Dumping data for table `family_fam`
--


-- --------------------------------------------------------

--
-- Table structure for table `groupprop_master`
--

CREATE TABLE `groupprop_master` (
  `grp_ID` mediumint(9) unsigned NOT NULL default '0',
  `prop_ID` tinyint(3) unsigned NOT NULL default '0',
  `prop_Field` varchar(5) NOT NULL default '0',
  `prop_Name` varchar(40) default NULL,
  `prop_Description` varchar(60) default NULL,
  `type_ID` smallint(5) unsigned NOT NULL default '0',
  `prop_Special` mediumint(9) unsigned default NULL,
  `prop_PersonDisplay` enum('false','true') NOT NULL default 'false'
) ENGINE=MyISAM CHARACTER SET utf8 COLLATE utf8_unicode_ci COMMENT='Group-specific properties order, name, description, type';

--
-- Dumping data for table `groupprop_master`
--


-- --------------------------------------------------------

--
-- Table structure for table `group_grp`
--

CREATE TABLE `group_grp` (
  `grp_ID` mediumint(8) unsigned NOT NULL auto_increment,
  `grp_Type` tinyint(4) NOT NULL default '0',
  `grp_RoleListID` mediumint(8) unsigned NOT NULL default '0',
  `grp_DefaultRole` mediumint(9) NOT NULL default '0',
  `grp_Name` varchar(50) NOT NULL default '',
  `grp_Description` text,
  `grp_hasSpecialProps` enum('true','false') NOT NULL default 'false',
  PRIMARY KEY  (`grp_ID`),
  UNIQUE KEY `grp_ID` (`grp_ID`),
  KEY `grp_ID_2` (`grp_ID`)
) ENGINE=MyISAM CHARACTER SET utf8 COLLATE utf8_unicode_ci AUTO_INCREMENT=1 ;

--
-- Dumping data for table `group_grp`
--


-- --------------------------------------------------------

--
-- Table structure for table `istlookup_lu`
--

CREATE TABLE `istlookup_lu` (
  `lu_fam_ID` mediumint(9) NOT NULL default '0',
  `lu_LookupDateTime` datetime NOT NULL default '0000-00-00 00:00:00',
  `lu_DeliveryLine1` varchar(255) default NULL,
  `lu_DeliveryLine2` varchar(255) default NULL,
  `lu_City` varchar(50) default NULL,
  `lu_State` varchar(50) default NULL,
  `lu_ZipAddon` varchar(50) default NULL,
  `lu_Zip` varchar(10) default NULL,
  `lu_Addon` varchar(10) default NULL,
  `lu_LOTNumber` varchar(10) default NULL,
  `lu_DPCCheckdigit` varchar(10) default NULL,
  `lu_RecordType` varchar(10) default NULL,
  `lu_LastLine` varchar(255) default NULL,
  `lu_CarrierRoute` varchar(10) default NULL,
  `lu_ReturnCodes` varchar(10) default NULL,
  `lu_ErrorCodes` varchar(10) default NULL,
  `lu_ErrorDesc` varchar(255) default NULL,
  PRIMARY KEY  (`lu_fam_ID`)
) ENGINE=MyISAM CHARACTER SET utf8 COLLATE utf8_unicode_ci COMMENT='US Address Verification Lookups From Intelligent Search Tech';

--
-- Dumping data for table `istlookup_lu`
--


-- --------------------------------------------------------

--
-- Table structure for table `list_lst`
--

CREATE TABLE `list_lst` (
  `lst_ID` mediumint(8) unsigned NOT NULL default '0',
  `lst_OptionID` mediumint(8) unsigned NOT NULL default '0',
  `lst_OptionSequence` tinyint(3) unsigned NOT NULL default '0',
  `lst_OptionName` varchar(50) NOT NULL default ''
) ENGINE=MyISAM CHARACTER SET utf8 COLLATE utf8_unicode_ci;

--
-- Dumping data for table `list_lst`
--

INSERT INTO `list_lst` (`lst_ID`, `lst_OptionID`, `lst_OptionSequence`, `lst_OptionName`) VALUES
(1, 1, 1, 'Member'),
(1, 2, 2, 'Regular Attender'),
(1, 3, 3, 'Guest'),
(1, 5, 4, 'Non-Attender'),
(1, 4, 5, 'Non-Attender (staff)'),
(2, 1, 1, 'Head of Household'),
(2, 2, 2, 'Spouse'),
(2, 3, 3, 'Child'),
(2, 4, 4, 'Other Relative'),
(2, 5, 5, 'Non Relative'),
(3, 1, 1, 'Ministry'),
(3, 2, 2, 'Team'),
(3, 3, 3, 'Bible Study'),
(3, 4, 4, 'Sunday School Class'),
(4, 1, 1, 'True / False'),
(4, 2, 2, 'Date'),
(4, 3, 3, 'Text Field (50 char)'),
(4, 4, 4, 'Text Field (100 char)'),
(4, 5, 5, 'Text Field (Long)'),
(4, 6, 6, 'Year'),
(4, 7, 7, 'Season'),
(4, 8, 8, 'Number'),
(4, 9, 9, 'Person from Group'),
(4, 10, 10, 'Money'),
(4, 11, 11, 'Phone Number'),
(4, 12, 12, 'Custom Drop-Down List'),
(5, 1, 1, 'bAll'),
(5, 2, 2, 'bAdmin'),
(5, 3, 3, 'bAddRecords'),
(5, 4, 4, 'bEditRecords'),
(5, 5, 5, 'bDeleteRecords'),
(5, 6, 6, 'bMenuOptions'),
(5, 7, 7, 'bManageGroups'),
(5, 8, 8, 'bFinance'),
(5, 9, 9, 'bNotes'),
(5, 10, 10, 'bCommunication'),
(5, 11, 11, 'bCanvasser'),
(10, 1, 1, 'Teacher'),
(10, 2, 2, 'Student'),
(11, 1, 1, 'Member'),
(12, 1, 1, 'Teacher'),
(12, 2, 2, 'Student');

-- --------------------------------------------------------

--
-- Table structure for table `menuconfig_mcf`
--

CREATE TABLE `menuconfig_mcf` (
  `mid` int(11) NOT NULL auto_increment,
  `name` varchar(20) NOT NULL,
  `parent` varchar(20) NOT NULL,
  `ismenu` tinyint(1) NOT NULL,
  `content_english` varchar(100) NOT NULL,
  `content` varchar(100) NULL,
  `uri` varchar(255) NOT NULL,
  `statustext` varchar(255) NOT NULL,
  `security_grp` varchar(50) NOT NULL,
  `session_var` varchar(50) default NULL,
  `session_var_in_text` tinyint(1) NOT NULL,
  `session_var_in_uri` tinyint(1) NOT NULL,
  `url_parm_name` varchar(50) default NULL,
  `active` tinyint(1) NOT NULL,
  `sortorder` tinyint(3) NOT NULL,
  `icon` varchar(50) DEFAULT NULL,
  PRIMARY KEY  (`mid`)
) ENGINE=MyISAM CHARACTER SET utf8 COLLATE utf8_unicode_ci  AUTO_INCREMENT=102 ;

--
-- Dumping data for table `menuconfig_mcf`
--


INSERT INTO `menuconfig_mcf` (`mid`, `name`, `parent`, `ismenu`, `content_english`, `content`, `uri`, `statustext`, `security_grp`, `session_var`, `session_var_in_text`, `session_var_in_uri`, `url_parm_name`, `active`, `sortorder`, `icon`) VALUES
(1, 'root', '', 1, 'Main', 'Main', '', '', 'bAll', NULL, 0, 0, NULL, 1, 0, NULL),
(2, 'calendar', 'root', 0, 'Calendar', 'Calendar', 'calendar.php', '', 'bAll', NULL, 0, 0, NULL, 1, 1, 'fa-calendar'),

(10, 'people', 'root', 1, 'Members', 'Members', '', 'Members', 'bAll', NULL, 0, 0, NULL, 1, 2, 'fa-users'),
(11, 'membdash', 'people', 0, 'Dashboard', 'Dashboard', 'MembersDashboard.php', '', 'bAddRecords', NULL, 0, 0, NULL, 1, 1, NULL),
(12, 'newperson', 'people', 0, 'Add New Person', 'Add New Person', 'PersonEditor.php', '', 'bAddRecords', NULL, 0, 0, NULL, 1, 2, NULL),
(13, 'viewperson', 'people', 0, 'View All Persons', 'View All Persons', 'SelectList.php?mode=person', '', 'bAll', NULL, 0, 0, NULL, 1, 3, NULL),
(14, 'newfamily', 'people', 0, 'Add New Family', 'Add New Family', 'FamilyEditor.php', '', 'bAddRecords', NULL, 0, 0, NULL, 1, 4, NULL),
(15, 'viewfamily', 'people', 0, 'View All Families', 'View All Families', 'FamilyList.php', '', 'bAll', NULL, 0, 0, NULL, 1, 5, NULL),

(20, 'groups', 'root', 1, 'Groups', 'Groups', '', '', 'bAll', NULL, 0, 0, NULL, 1, 3, 'fa-tag'),
(21, 'listgroups', 'groups', 0, 'List Groups', 'List Groups', 'GroupList.php', '', 'bAll', NULL, 0, 0, NULL, 1, 1, NULL),

(23, 'editgroup', 'groups', 0, 'Edit Group Types', 'Edit Group Types', 'OptionManager.php?mode=grptypes', '', 'bMenuOptions', NULL, 0, 0, NULL, 1, 3, NULL),
(24, 'assigngroup', 'groups', 0, 'Group Assignment Helper', 'Group Assignment Helper', 'SelectList.php?mode=groupassign', '', 'bAll', NULL, 0, 0, NULL, 1, 4, NULL),

(30, 'sundayschool', 'root', 1, 'Sunday School', 'Sunday School', '', '', 'bAll', NULL, 0, 0, NULL, 1, 4, 'fa-child'),
(31, 'sundayschool-dash', 'sundayschool', 0, 'Dashboard', 'Dashboard', 'sundayschool/SundaySchoolDashboard.php', '', 'bAll', NULL, 0, 0, NULL, 1, 2, NULL),

(40, 'mailchimp', 'root', 0, 'MailChimp', 'MailChimp', 'mailchimp/MailChimpDashboard.php', '', 'bAll', NULL, 0, 0, NULL, 1, 5, 'fa-envelope'),

(50, 'events', 'root', 1, 'Events', 'Events', '', 'Events', 'bAll', NULL, 0, 0, NULL, 1,6, 'fa-ticket'),
(51, 'listevent', 'events', 0, 'List Church Events', 'List Church Events', 'ListEvents.php', 'List Church Events', 'bAll', NULL, 0, 0, NULL, 1, 1, NULL),
(52, 'addevent', 'events', 0, 'Add Church Event', 'Add Church Event', 'EventEditor.php', 'Add Church Event', 'bAll', NULL, 0, 0, NULL, 1, 2, NULL),
(53, 'eventype', 'events', 0, 'List Event Types', 'List Event Types', 'EventNames.php', '', 'bAdmin', NULL, 0, 0, NULL, 1, 3, NULL),
(54, 'eventcheckin', 'events', 0, 'Check-in and Check-out', 'Check-in and Check-out', 'Checkin.php', '', 'bAll', NULL, 0, 0, NULL, 1, 4, NULL),

(60, 'deposit', 'root', 1, 'Deposit', 'Deposit', '', '', 'bFinance', NULL, 0, 0, NULL, 1, 7, 'fa-bank'),
(61, 'envelopmgr', 'deposit', 0, 'Envelope Manager', 'Envelope Manager', 'ManageEnvelopes.php', '', 'bAdmin', NULL, 0, 0, NULL, 1, 1, NULL),
(62, 'newdeposit', 'deposit', 0, 'Create New Deposit', 'Create New Deposit', 'DepositSlipEditor.php?DepositType=Bank', '', 'bFinance', NULL, 0, 0, NULL, 1, 2, NULL),
(63, 'viewdeposit', 'deposit', 0, 'View All Deposits', 'View All Deposits', 'FindDepositSlip.php', '', 'bFinance', NULL, 0, 0, NULL, 1, 3, NULL),
(64, 'depositreport', 'deposit', 0, 'Deposit Reports', 'Deposit Reports', 'FinancialReports.php', '', 'bFinance', NULL, 0, 0, NULL, 1, 4, NULL),
(65, 'depositslip', 'deposit', 0, 'Edit Deposit Slip', 'Edit Deposit Slip', 'DepositSlipEditor.php', '', 'bFinance', 'iCurrentDeposit', 1, 1, 'DepositSlipID', 1, 5, NULL),

(70, 'fundraiser', 'root', 1, 'Fundraiser', 'Fundraiser', '', '', 'bAll', NULL, 0, 0, NULL, 1, 8, 'fa-money'),
(71, 'viewfundraiser', 'fundraiser', 0, 'View All Fundraisers', 'View All Fundraisers', 'FindFundRaiser.php', '', 'bAll', NULL, 0, 0, NULL, 1, 1, NULL),
(72, 'newfundraiser', 'fundraiser', 0, 'Create New Fundraiser', 'Create New Fundraiser', 'FundRaiserEditor.php?FundRaiserID=-1', '', 'bAll', NULL, 0, 0, NULL, 1, 2, NULL),
(73, 'editfundraiser', 'fundraiser', 0, 'Edit Fundraiser', 'Edit Fundraiser', 'FundRaiserEditor.php', '', 'bAll', 'iCurrentFundraiser', 1, 1, 'FundRaiserID', 1, 3, NULL),
(74, 'viewbuyers', 'fundraiser', 0, 'View Buyers', 'View Buyers', 'PaddleNumList.php', '', 'bAll', 'iCurrentFundraiser', 1, 1, 'FundRaiserID', 1, 4, NULL),
(75, 'adddonors', 'fundraiser', 0, 'Add Donors to Buyer List', 'Add Donors to Buyer List', 'AddDonors.php', '', 'bAll', 'iCurrentFundraiser', 1, 1, 'FundRaiserID', 1, 5, NULL),

(80, 'report', 'root', 1, 'Data/Reports', 'Data/Reports', '', '', 'bAll', NULL, 0, 0, NULL, 1, 9, 'fa-file-pdf-o'),
(81, 'reportmenu', 'report', 0, 'Reports Menu', 'Reports Menu', 'ReportList.php', '', 'bAll', NULL, 0, 0, NULL, 1, 1, NULL),
(82, 'querymenu', 'report', 0, 'Query Menu', 'Query Menu', 'QueryList.php', '', 'bAll', NULL, 0, 0, NULL, 1, 2, NULL),
(83, 'cvsexport', 'report', 0, 'CSV Export Records', 'CSV Export Records', 'CSVExport.php', '', 'bAll', NULL, 0, 0, NULL, 1, 3, NULL),

(90, 'properties', 'root', 1, 'Properties', 'Properties', '', '', 'bAll', NULL, 0, 0, NULL, 1, 10, 'fa-cogs'),
(91, 'peopleproperty', 'properties', 0, 'People Properties', 'People Properties', 'PropertyList.php?Type=p', '', 'bAll', NULL, 0, 0, NULL, 1, 1, NULL),
(92, 'familyproperty', 'properties', 0, 'Family Properties', 'Family Properties', 'PropertyList.php?Type=f', '', 'bAll', NULL, 0, 0, NULL, 1, 2, NULL),
(93, 'groupproperty', 'properties', 0, 'Group Properties', 'Group Properties', 'PropertyList.php?Type=g', '', 'bAll', NULL, 0, 0, NULL, 1, 3, NULL),
(94, 'propertytype', 'properties', 0, 'Property Types', 'Property Types', 'PropertyTypeList.php', '', 'bAll', NULL, 0, 0, NULL, 1, 4, NULL),

(100, 'generalsetting', 'admin', 0, 'Edit General Settings', 'Edit General Settings', 'SystemSettings.php', '', 'bAdmin', NULL, 0, 0, NULL, 1, 1, NULL),
(101, 'editusers', 'admin', 0, 'System Users', 'System Users', 'UserList.php', '', 'bAdmin', NULL, 0, 0, NULL, 1, 2, NULL),
(102, 'accessreport', 'admin', 0, 'Access report', 'Access report', 'AccessReport.php', '', 'bAdmin', NULL, 0, 0, NULL, 1, 3, NULL),
(104, 'customfamilyfld', 'admin', 0, 'Edit Custom Family Fields', 'Edit Custom Family Fields', 'FamilyCustomFieldsEditor.php', '', 'bAdmin', NULL, 0, 0, NULL, 1, 5, NULL),
(105, 'custompersonfld', 'admin', 0, 'Edit Custom Person Fields', 'Edit Custom Person Fields', 'PersonCustomFieldsEditor.php', '', 'bAdmin', NULL, 0, 0, NULL, 1, 6, NULL),
(106, 'donationfund', 'admin', 0, 'Edit Donation Funds', 'Edit Donation Funds', 'DonationFundEditor.php', '', 'bAdmin', NULL, 0, 0, NULL, 1, 7, NULL),
(107, 'dbbackup', 'admin', 0, 'Backup Database', 'Backup Database', 'BackupDatabase.php', '', 'bAdmin', NULL, 0, 0, NULL, 1, 8, NULL),
(108, 'dbrestore', 'admin', 0, 'Restore Database', 'Restore Database', 'RestoreDatabase.php', '', 'bAdmin', NULL, 0, 0, NULL, 1, 9, NULL),
(109, 'cvsimport', 'admin', 0, 'CSV Import', 'CSV Import', 'CSVImport.php', '', 'bAdmin', NULL, 0, 0, NULL, 1, 10, NULL),
(110, 'seeddata', 'admin', 0, 'Generate Seed Data', 'Generate Seed Data', 'GenerateSeedData.php', '', 'bAdmin', NULL, 0, 0, NULL, 1, 11, NULL),
(111, 'register', 'admin', 0, 'Register ChurchCRM', 'Update ChurchCRM Registration', 'Register.php', '', 'bAdmin', NULL, 0, 0, NULL, 1, 12, NULL);


-- --------------------------------------------------------

--
-- Table structure for table `note_nte`
--

CREATE TABLE `note_nte` (
  `nte_ID` mediumint(8) unsigned NOT NULL auto_increment,
  `nte_per_ID` mediumint(8) unsigned NOT NULL default '0',
  `nte_fam_ID` mediumint(8) unsigned NOT NULL default '0',
  `nte_Private` mediumint(8) unsigned NOT NULL default '0',
  `nte_Text` text,
  `nte_DateEntered` datetime NOT NULL default '0000-00-00 00:00:00',
  `nte_DateLastEdited` datetime default NULL,
  `nte_EnteredBy` mediumint(8) unsigned NOT NULL default '0',
  `nte_EditedBy` mediumint(8) unsigned NOT NULL default '0',
  PRIMARY KEY  (`nte_ID`)
) ENGINE=MyISAM CHARACTER SET utf8 COLLATE utf8_unicode_ci AUTO_INCREMENT=1 ;

--
-- Dumping data for table `note_nte`
--


-- --------------------------------------------------------

--
-- Table structure for table `person2group2role_p2g2r`
--

CREATE TABLE `person2group2role_p2g2r` (
  `p2g2r_per_ID` mediumint(8) unsigned NOT NULL default '0',
  `p2g2r_grp_ID` mediumint(8) unsigned NOT NULL default '0',
  `p2g2r_rle_ID` mediumint(8) unsigned NOT NULL default '0',
  PRIMARY KEY  (`p2g2r_per_ID`,`p2g2r_grp_ID`),
  KEY `p2g2r_per_ID` (`p2g2r_per_ID`,`p2g2r_grp_ID`,`p2g2r_rle_ID`)
) ENGINE=MyISAM CHARACTER SET utf8 COLLATE utf8_unicode_ci;

--
-- Dumping data for table `person2group2role_p2g2r`
--


-- --------------------------------------------------------

--
-- Table structure for table `person2volunteeropp_p2vo`
--

CREATE TABLE `person2volunteeropp_p2vo` (
  `p2vo_ID` mediumint(9) NOT NULL auto_increment,
  `p2vo_per_ID` mediumint(9) default NULL,
  `p2vo_vol_ID` mediumint(9) default NULL,
  PRIMARY KEY  (`p2vo_ID`),
  UNIQUE KEY `p2vo_ID` (`p2vo_ID`)
) ENGINE=MyISAM CHARACTER SET utf8 COLLATE utf8_unicode_ci AUTO_INCREMENT=1 ;

--
-- Dumping data for table `person2volunteeropp_p2vo`
--


-- --------------------------------------------------------

--
-- Table structure for table `person_custom`
--

CREATE TABLE `person_custom` (
  `per_ID` mediumint(9) NOT NULL default '0',
  PRIMARY KEY  (`per_ID`)
) ENGINE=MyISAM CHARACTER SET utf8 COLLATE utf8_unicode_ci;

--
-- Dumping data for table `person_custom`
--


-- --------------------------------------------------------

--
-- Table structure for table `person_custom_master`
--

CREATE TABLE `person_custom_master` (
  `custom_Order` smallint(6) NOT NULL default '0',
  `custom_Field` varchar(5) NOT NULL default '',
  `custom_Name` varchar(40) NOT NULL default '',
  `custom_Special` mediumint(8) unsigned default NULL,
  `custom_Side` enum('left','right') NOT NULL default 'left',
  `custom_FieldSec` tinyint(4) NOT NULL,
  `type_ID` tinyint(4) NOT NULL default '0'
) ENGINE=MyISAM CHARACTER SET utf8 COLLATE utf8_unicode_ci;

--
-- Dumping data for table `person_custom_master`
--


-- --------------------------------------------------------

--
-- Table structure for table `person_per`
--

CREATE TABLE `person_per` (
  `per_ID` mediumint(9) unsigned NOT NULL auto_increment,
  `per_Title` varchar(50) default NULL,
  `per_FirstName` varchar(50) default NULL,
  `per_MiddleName` varchar(50) default NULL,
  `per_LastName` varchar(50) default NULL,
  `per_Suffix` varchar(50) default NULL,
  `per_Address1` varchar(50) default NULL,
  `per_Address2` varchar(50) default NULL,
  `per_City` varchar(50) default NULL,
  `per_State` varchar(50) default NULL,
  `per_Zip` varchar(50) default NULL,
  `per_Country` varchar(50) default NULL,
  `per_HomePhone` varchar(30) default NULL,
  `per_WorkPhone` varchar(30) default NULL,
  `per_CellPhone` varchar(30) default NULL,
  `per_Email` varchar(50) default NULL,
  `per_WorkEmail` varchar(50) default NULL,
  `per_BirthMonth` tinyint(3) unsigned NOT NULL default '0',
  `per_BirthDay` tinyint(3) unsigned NOT NULL default '0',
  `per_BirthYear` year(4) default NULL,
  `per_MembershipDate` date default NULL,
  `per_Gender` tinyint(1) unsigned NOT NULL default '0',
  `per_fmr_ID` tinyint(3) unsigned NOT NULL default '0',
  `per_cls_ID` tinyint(3) unsigned NOT NULL default '0',
  `per_fam_ID` smallint(5) unsigned NOT NULL default '0',
  `per_Envelope` smallint(5) unsigned default NULL,
  `per_DateLastEdited` datetime default NULL,
  `per_DateEntered` datetime NOT NULL default '0000-00-00 00:00:00',
  `per_EnteredBy` smallint(5) unsigned NOT NULL default '0',
  `per_EditedBy` smallint(5) unsigned default '0',
  `per_FriendDate` date default NULL,
  `per_Flags` mediumint(9) NOT NULL default '0',
  PRIMARY KEY  (`per_ID`),
  KEY `per_ID` (`per_ID`)
) ENGINE=MyISAM CHARACTER SET utf8 COLLATE utf8_unicode_ci  AUTO_INCREMENT=2 ;

--
-- Dumping data for table `person_per`
--

INSERT INTO `person_per` (`per_ID`, `per_Title`, `per_FirstName`, `per_MiddleName`, `per_LastName`, `per_Suffix`, `per_Address1`, `per_Address2`, `per_City`, `per_State`, `per_Zip`, `per_Country`, `per_HomePhone`, `per_WorkPhone`, `per_CellPhone`, `per_Email`, `per_WorkEmail`, `per_BirthMonth`, `per_BirthDay`, `per_BirthYear`, `per_MembershipDate`, `per_Gender`, `per_fmr_ID`, `per_cls_ID`, `per_fam_ID`, `per_Envelope`, `per_DateLastEdited`, `per_DateEntered`, `per_EnteredBy`, `per_EditedBy`, `per_FriendDate`, `per_Flags`) VALUES
(1, NULL, 'Church', NULL, 'Admin', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0, 0000, NULL, 0, 0, 0, 0, NULL, NULL, '2004-08-25 18:00:00', 1, 0, NULL, 0);

-- --------------------------------------------------------

--
-- Table structure for table `pledge_plg`
--

CREATE TABLE `pledge_plg` (
  `plg_plgID` mediumint(9) NOT NULL auto_increment,
  `plg_FamID` mediumint(9) default NULL,
  `plg_FYID` mediumint(9) default NULL,
  `plg_date` date default NULL,
  `plg_amount` decimal(8,2) default NULL,
  `plg_schedule` enum('Weekly', 'Monthly','Quarterly','Once','Other') default NULL,
  `plg_method` enum('CREDITCARD','CHECK','CASH','BANKDRAFT','EGIVE') default NULL,
  `plg_comment` text,
  `plg_DateLastEdited` date NOT NULL default '0000-00-00',
  `plg_EditedBy` mediumint(9) NOT NULL default '0',
  `plg_PledgeOrPayment` enum('Pledge','Payment') NOT NULL default 'Pledge',
  `plg_fundID` tinyint(3) unsigned default NULL,
  `plg_depID` mediumint(9) unsigned default NULL,
  `plg_CheckNo` bigint(16) unsigned default NULL,
  `plg_Problem` tinyint(1) default NULL,
  `plg_scanString` text,
  `plg_aut_ID` mediumint(9) NOT NULL default '0',
  `plg_aut_Cleared` tinyint(1) NOT NULL default '0',
  `plg_aut_ResultID` mediumint(9) NOT NULL default '0',
  `plg_NonDeductible` decimal(8,2) NOT NULL,
  `plg_GroupKey` VARCHAR( 64 ) NOT NULL,
  PRIMARY KEY  (`plg_plgID`)
) ENGINE=MyISAM CHARACTER SET utf8 COLLATE utf8_unicode_ci AUTO_INCREMENT=1 ;

--
-- Dumping data for table `pledge_plg`
--


-- --------------------------------------------------------

--
-- Table structure for table `propertytype_prt`
--

CREATE TABLE `propertytype_prt` (
  `prt_ID` mediumint(9) NOT NULL auto_increment,
  `prt_Class` varchar(10) NOT NULL default '',
  `prt_Name` varchar(50) NOT NULL default '',
  `prt_Description` text NOT NULL,
  PRIMARY KEY  (`prt_ID`),
  UNIQUE KEY `prt_ID` (`prt_ID`),
  KEY `prt_ID_2` (`prt_ID`)
) ENGINE=MyISAM CHARACTER SET utf8 COLLATE utf8_unicode_ci  AUTO_INCREMENT=4 ;

--
-- Dumping data for table `propertytype_prt`
--

INSERT INTO `propertytype_prt` (`prt_ID`, `prt_Class`, `prt_Name`, `prt_Description`) VALUES
(1, 'p', 'General', 'General Person Properties'),
(2, 'f', 'General', 'General Family Properties'),
(3, 'g', 'General', 'General Group Properties');

-- --------------------------------------------------------

--
-- Table structure for table `property_pro`
--

CREATE TABLE `property_pro` (
  `pro_ID` mediumint(8) unsigned NOT NULL auto_increment,
  `pro_Class` varchar(10) NOT NULL default '',
  `pro_prt_ID` mediumint(8) unsigned NOT NULL default '0',
  `pro_Name` varchar(200) NOT NULL default '0',
  `pro_Description` text NOT NULL,
  `pro_Prompt` varchar(255) default NULL,
  PRIMARY KEY  (`pro_ID`),
  UNIQUE KEY `pro_ID` (`pro_ID`),
  KEY `pro_ID_2` (`pro_ID`)
) ENGINE=MyISAM CHARACTER SET utf8 COLLATE utf8_unicode_ci  AUTO_INCREMENT=4 ;

--
-- Dumping data for table `property_pro`
--

INSERT INTO `property_pro` (`pro_ID`, `pro_Class`, `pro_prt_ID`, `pro_Name`, `pro_Description`, `pro_Prompt`) VALUES
(1, 'p', 1, 'Disabled', 'has a disability.', 'What is the nature of the disability?'),
(2, 'f', 2, 'Single Parent', 'is a single-parent household.', ''),
(3, 'g', 3, 'Youth', 'is youth-oriented.', '');

-- --------------------------------------------------------

--
-- Table structure for table `queryparameteroptions_qpo`
--

CREATE TABLE `queryparameteroptions_qpo` (
  `qpo_ID` smallint(5) unsigned NOT NULL auto_increment,
  `qpo_qrp_ID` mediumint(8) unsigned NOT NULL default '0',
  `qpo_Display` varchar(50) NOT NULL default '',
  `qpo_Value` varchar(50) NOT NULL default '',
  PRIMARY KEY  (`qpo_ID`),
  UNIQUE KEY `qpo_ID` (`qpo_ID`)
) ENGINE=MyISAM CHARACTER SET utf8 COLLATE utf8_unicode_ci  AUTO_INCREMENT=28 ;

--
-- Dumping data for table `queryparameteroptions_qpo`
--

INSERT INTO `queryparameteroptions_qpo` (`qpo_ID`, `qpo_qrp_ID`, `qpo_Display`, `qpo_Value`) VALUES
(1, 4, 'Male', '1'),
(2, 4, 'Female', '2'),
(3, 6, 'Male', '1'),
(4, 6, 'Female', '2'),
(5, 15, 'Name', 'CONCAT(per_FirstName,per_MiddleName,per_LastName)'),
(6, 15, 'Zip Code', 'fam_Zip'),
(7, 15, 'State', 'fam_State'),
(8, 15, 'City', 'fam_City'),
(9, 15, 'Home Phone', 'per_HomePhone'),
(10, 27, '2012/2013', '17'),
(11, 27, '2013/2014', '18'),
(12, 27, '2014/2015', '19'),
(13, 27, '2015/2016', '20'),
(14, 28, '2012/2013', '17'),
(15, 28, '2013/2014', '18'),
(16, 28, '2014/2015', '19'),
(17, 28, '2015/2016', '20'),
(18, 30, '2012/2013', '17'),
(19, 30, '2013/2014', '18'),
(20, 30, '2014/2015', '19'),
(21, 30, '2015/2016', '20'),
(22, 31, '2012/2013', '17'),
(23, 31, '2013/2014', '18'),
(24, 31, '2014/2015', '19'),
(25, 31, '2015/2016', '20'),
(26, 15, 'Email', 'per_Email'),
(27, 15, 'WorkEmail', 'per_WorkEmail'),
(28, 32, '2012/2013', '17'),
(29, 32, '2013/2014', '18'),
(30, 32, '2014/2015', '19'),
(31, 32, '2015/2016', '20'),
(32, 33, 'Member', '1'),
(33, 33, 'Regular Attender', '2'),
(34, 33, 'Guest', '3'),
(35, 33, 'Non-Attender', '4'),
(36, 33, 'Non-Attender (staff)', '5');

-- --------------------------------------------------------

--
-- Table structure for table `queryparameters_qrp`
--

CREATE TABLE `queryparameters_qrp` (
  `qrp_ID` mediumint(8) unsigned NOT NULL auto_increment,
  `qrp_qry_ID` mediumint(8) unsigned NOT NULL default '0',
  `qrp_Type` tinyint(3) unsigned NOT NULL default '0',
  `qrp_OptionSQL` text,
  `qrp_Name` varchar(25) default NULL,
  `qrp_Description` text,
  `qrp_Alias` varchar(25) default NULL,
  `qrp_Default` varchar(25) default NULL,
  `qrp_Required` tinyint(3) unsigned NOT NULL default '0',
  `qrp_InputBoxSize` tinyint(3) unsigned NOT NULL default '0',
  `qrp_Validation` varchar(5) NOT NULL default '',
  `qrp_NumericMax` int(11) default NULL,
  `qrp_NumericMin` int(11) default NULL,
  `qrp_AlphaMinLength` int(11) default NULL,
  `qrp_AlphaMaxLength` int(11) default NULL,
  PRIMARY KEY  (`qrp_ID`),
  UNIQUE KEY `qrp_ID` (`qrp_ID`),
  KEY `qrp_ID_2` (`qrp_ID`),
  KEY `qrp_qry_ID` (`qrp_qry_ID`)
) ENGINE=MyISAM CHARACTER SET utf8 COLLATE utf8_unicode_ci  AUTO_INCREMENT=102 ;

--
-- Dumping data for table `queryparameters_qrp`
--

INSERT INTO `queryparameters_qrp` (`qrp_ID`, `qrp_qry_ID`, `qrp_Type`, `qrp_OptionSQL`, `qrp_Name`, `qrp_Description`, `qrp_Alias`, `qrp_Default`, `qrp_Required`, `qrp_InputBoxSize`, `qrp_Validation`, `qrp_NumericMax`, `qrp_NumericMin`, `qrp_AlphaMinLength`, `qrp_AlphaMaxLength`) VALUES
(1, 4, 0, NULL, 'Minimum Age', 'The minimum age for which you want records returned.', 'min', '0', 0, 5, 'n', 120, 0, NULL, NULL),
(2, 4, 0, NULL, 'Maximum Age', 'The maximum age for which you want records returned.', 'max', '120', 1, 5, 'n', 120, 0, NULL, NULL),
(4, 6, 1, '', 'Gender', 'The desired gender to search the database for.', 'gender', '1', 1, 0, '', 0, 0, 0, 0),
(5, 7, 2, 'SELECT lst_OptionID as Value, lst_OptionName as Display FROM list_lst WHERE lst_ID=2 ORDER BY lst_OptionSequence', 'Family Role', 'Select the desired family role.', 'role', '1', 0, 0, '', 0, 0, 0, 0),
(6, 7, 1, '', 'Gender', 'The gender for which you would like records returned.', 'gender', '1', 1, 0, '', 0, 0, 0, 0),
(8, 9, 2, 'SELECT pro_ID AS Value, pro_Name as Display \r\nFROM property_pro\r\nWHERE pro_Class= ''p'' \r\nORDER BY pro_Name ', 'Property', 'The property for which you would like person records returned.', 'PropertyID', '0', 1, 0, '', 0, 0, 0, 0),
(9, 10, 2, 'SELECT distinct don_date as Value, don_date as Display FROM donations_don ORDER BY don_date ASC', 'Beginning Date', 'Please select the beginning date to calculate total contributions for each member (i.e. YYYY-MM-DD). NOTE: You can only choose dates that conatain donations.', 'startdate', '1', 1, 0, '0', 0, 0, 0, 0),
(10, 10, 2, 'SELECT distinct don_date as Value, don_date as Display FROM donations_don\r\nORDER BY don_date DESC', 'Ending Date', 'Please enter the last date to calculate total contributions for each member (i.e. YYYY-MM-DD).', 'enddate', '1', 1, 0, '', 0, 0, 0, 0),
(14, 15, 0, '', 'Search', 'Enter any part of the following: Name, City, State, Zip, Home Phone, Email, or Work Email.', 'searchstring', '', 1, 0, '', 0, 0, 0, 0),
(15, 15, 1, '', 'Field', 'Select field to search for.', 'searchwhat', '1', 1, 0, '', 0, 0, 0, 0),
(16, 11, 2, 'SELECT distinct don_date as Value, don_date as Display FROM donations_don ORDER BY don_date ASC', 'Beginning Date', 'Please select the beginning date to calculate total contributions for each member (i.e. YYYY-MM-DD). NOTE: You can only choose dates that conatain donations.', 'startdate', '1', 1, 0, '0', 0, 0, 0, 0),
(17, 11, 2, 'SELECT distinct don_date as Value, don_date as Display FROM donations_don\r\nORDER BY don_date DESC', 'Ending Date', 'Please enter the last date to calculate total contributions for each member (i.e. YYYY-MM-DD).', 'enddate', '1', 1, 0, '', 0, 0, 0, 0),
(18, 18, 0, '', 'Month', 'The birthday month for which you would like records returned.', 'birthmonth', '1', 1, 0, '', 12, 1, 1, 2),
(19, 19, 2, 'SELECT grp_ID AS Value, grp_Name AS Display FROM group_grp ORDER BY grp_Type', 'Class', 'The sunday school class for which you would like records returned.', 'group', '1', 1, 0, '', 12, 1, 1, 2),
(20, 20, 2, 'SELECT grp_ID AS Value, grp_Name AS Display FROM group_grp ORDER BY grp_Type', 'Class', 'The sunday school class for which you would like records returned.', 'group', '1', 1, 0, '', 12, 1, 1, 2),
(21, 21, 2, 'SELECT grp_ID AS Value, grp_Name AS Display FROM group_grp ORDER BY grp_Type', 'Registered students', 'Group of registered students', 'group', '1', 1, 0, '', 12, 1, 1, 2),
(22, 22, 0, '', 'Month', 'The membership anniversary month for which you would like records returned.', 'membermonth', '1', 1, 0, '', 12, 1, 1, 2),
(25, 25, 2, 'SELECT vol_ID AS Value, vol_Name AS Display FROM volunteeropportunity_vol ORDER BY vol_Name', 'Volunteer opportunities', 'Choose a volunteer opportunity', 'volopp', '1', 1, 0, '', 12, 1, 1, 2),
(26, 26, 0, '', 'Months', 'Number of months since becoming a friend', 'friendmonths', '1', 1, 0, '', 24, 1, 1, 2),
(27, 28, 1, '', 'First Fiscal Year', 'First fiscal year for comparison', 'fyid1', '9', 1, 0, '', 12, 9, 0, 0),
(28, 28, 1, '', 'Second Fiscal Year', 'Second fiscal year for comparison', 'fyid2', '9', 1, 0, '', 12, 9, 0, 0),
(30, 30, 1, '', 'First Fiscal Year', 'Pledged this year', 'fyid1', '9', 1, 0, '', 12, 9, 0, 0),
(31, 30, 1, '', 'Second Fiscal Year', 'but not this year', 'fyid2', '9', 1, 0, '', 12, 9, 0, 0),
(32, 32, 1, '', 'Fiscal Year', 'Fiscal Year.', 'fyid', '9', 1, 0, '', 12, 9, 0, 0),
(33, 18, 1, '', 'Classification', 'Member, Regular Attender, etc.', 'percls', '1', 1, 0, '', 12, 1, 1, 2),
(100, 100, 2, 'SELECT vol_ID AS Value, vol_Name AS Display FROM volunteeropportunity_vol ORDER BY vol_Name', 'Volunteer opportunities', 'First volunteer opportunity choice', 'volopp1', '1', 1, 0, '', 12, 1, 1, 2),
(101, 100, 2, 'SELECT vol_ID AS Value, vol_Name AS Display FROM volunteeropportunity_vol ORDER BY vol_Name', 'Volunteer opportunities', 'Second volunteer opportunity choice', 'volopp2', '1', 1, 0, '', 12, 1, 1, 2),
(200, 200, 2, 'SELECT custom_field as Value, custom_Name as Display FROM person_custom_master', 'Custom field', 'Choose customer person field', 'custom', '1', 0, 0, '', 0, 0, 0, 0),
(201, 200, 0, '', 'Field value', 'Match custom field to this value', 'value', '1', 0, 0, '', 0, 0, 0, 0);

-- --------------------------------------------------------

--
-- Table structure for table `query_qry`
--

CREATE TABLE `query_qry` (
  `qry_ID` mediumint(8) unsigned NOT NULL auto_increment,
  `qry_SQL` text NOT NULL,
  `qry_Name` varchar(255) NOT NULL default '',
  `qry_Description` text NOT NULL,
  `qry_Count` tinyint(1) unsigned NOT NULL default '0',
  PRIMARY KEY  (`qry_ID`),
  UNIQUE KEY `qry_ID` (`qry_ID`),
  KEY `qry_ID_2` (`qry_ID`)
) ENGINE=MyISAM CHARACTER SET utf8 COLLATE utf8_unicode_ci  AUTO_INCREMENT=101 ;

--
-- Dumping data for table `query_qry`
--

INSERT INTO `query_qry` (`qry_ID`, `qry_SQL`, `qry_Name`, `qry_Description`, `qry_Count`) VALUES
(3, 'SELECT CONCAT(''<a href=FamilyView.php?FamilyID='',fam_ID,''>'',fam_Name,''</a>'') AS ''Family Name'', COUNT(*) AS ''No.''\nFROM person_per\nINNER JOIN family_fam\nON fam_ID = per_fam_ID\nGROUP BY per_fam_ID\nORDER BY ''No.'' DESC', 'Family Member Count', 'Returns each family and the total number of people assigned to them.', 0),
(4, 'SELECT per_ID as AddToCart,CONCAT(''<a\r\nhref=PersonView.php?PersonID='',per_ID,''>'',per_FirstName,''\r\n'',per_LastName,''</a>'') AS Name,\r\nCONCAT(per_BirthMonth,''/'',per_BirthDay,''/'',per_BirthYear) AS ''Birth Date'',\r\nDATE_FORMAT(FROM_DAYS(TO_DAYS(NOW())-TO_DAYS(CONCAT(per_BirthYear,''-'',per_BirthMonth,''-'',per_BirthDay))),''%Y'')+0 AS  ''Age''\r\nFROM person_per\r\nWHERE\r\nDATE_ADD(CONCAT(per_BirthYear,''-'',per_BirthMonth,''-'',per_BirthDay),INTERVAL\r\n~min~ YEAR) <= CURDATE()\r\nAND\r\nDATE_ADD(CONCAT(per_BirthYear,''-'',per_BirthMonth,''-'',per_BirthDay),INTERVAL\r\n(~max~ + 1) YEAR) >= CURDATE()', 'Person by Age', 'Returns any person records with ages between two given ages.', 1),
(6, 'SELECT COUNT(per_ID) AS Total FROM person_per WHERE per_Gender = ~gender~', 'Total By Gender', 'Total of records matching a given gender.', 0),
(7, 'SELECT per_ID as AddToCart, CONCAT(per_FirstName,'' '',per_LastName) AS Name FROM person_per WHERE per_fmr_ID = ~role~ AND per_Gender = ~gender~', 'Person by Role and Gender', 'Selects person records with the family role and gender specified.', 1),
(9, 'SELECT \r\nper_ID as AddToCart, \r\nCONCAT(per_FirstName,'' '',per_LastName) AS Name, \r\nCONCAT(r2p_Value,'' '') AS Value\r\nFROM person_per,record2property_r2p\r\nWHERE per_ID = r2p_record_ID\r\nAND r2p_pro_ID = ~PropertyID~\r\nORDER BY per_LastName', 'Person by Property', 'Returns person records which are assigned the given property.', 1),
(15, 'SELECT per_ID as AddToCart, CONCAT(''<a href=PersonView.php?PersonID='',per_ID,''>'',per_FirstName,'' '',per_MiddleName,'' '',per_LastName,''</a>'') AS Name, fam_City as City, fam_State as State, fam_Zip as ZIP, per_HomePhone as HomePhone, per_Email as Email, per_WorkEmail as WorkEmail FROM person_per RIGHT JOIN family_fam ON family_fam.fam_id = person_per.per_fam_id WHERE ~searchwhat~ LIKE ''%~searchstring~%''', 'Advanced Search', 'Search by any part of Name, City, State, Zip, Home Phone, Email, or Work Email.', 1),
(18, 'SELECT per_ID as AddToCart, per_BirthDay as Day, CONCAT(per_FirstName,'' '',per_LastName) AS Name FROM person_per WHERE per_cls_ID=~percls~ AND per_BirthMonth=~birthmonth~ ORDER BY per_BirthDay', 'Birthdays', 'People with birthdays in a particular month', 0),
(21, 'SELECT per_ID as AddToCart, CONCAT(''<a href=PersonView.php?PersonID='',per_ID,''>'',per_FirstName,'' '',per_LastName,''</a>'') AS Name FROM person_per LEFT JOIN person2group2role_p2g2r ON per_id = p2g2r_per_ID WHERE p2g2r_grp_ID=~group~ ORDER BY per_LastName', 'Registered students', 'Find Registered students', 1),
(22, 'SELECT per_ID as AddToCart, DAYOFMONTH(per_MembershipDate) as Day, per_MembershipDate AS DATE, CONCAT(per_FirstName,'' '',per_LastName) AS Name FROM person_per WHERE per_cls_ID=1 AND MONTH(per_MembershipDate)=~membermonth~ ORDER BY per_MembershipDate', 'Membership anniversaries', 'Members who joined in a particular month', 0),
(23, 'SELECT usr_per_ID as AddToCart, CONCAT(a.per_FirstName,'' '',a.per_LastName) AS Name FROM user_usr LEFT JOIN person_per a ON per_ID=usr_per_ID ORDER BY per_LastName', 'Select database users', 'People who are registered as database users', 0),
(24, 'SELECT per_ID as AddToCart, CONCAT(''<a href=PersonView.php?PersonID='',per_ID,''>'',per_FirstName,'' '',per_LastName,''</a>'') AS Name FROM person_per WHERE per_cls_id =1', 'Select all members', 'People who are members', 0),
(25, 'SELECT per_ID as AddToCart, CONCAT(''<a href=PersonView.php?PersonID='',per_ID,''>'',per_FirstName,'' '',per_LastName,''</a>'') AS Name FROM person_per LEFT JOIN person2volunteeropp_p2vo ON per_id = p2vo_per_ID WHERE p2vo_vol_ID = ~volopp~ ORDER BY per_LastName', 'Volunteers', 'Find volunteers for a particular opportunity', 1),
(26, 'SELECT per_ID as AddToCart, CONCAT(per_FirstName,'' '',per_LastName) AS Name FROM person_per WHERE DATE_SUB(NOW(),INTERVAL ~friendmonths~ MONTH)<per_FriendDate ORDER BY per_MembershipDate', 'Recent friends', 'Friends who signed up in previous months', 0),
(27, 'SELECT per_ID as AddToCart, CONCAT(per_FirstName,'' '',per_LastName) AS Name FROM person_per inner join family_fam on per_fam_ID=fam_ID where per_fmr_ID<>3 AND fam_OkToCanvass="TRUE" ORDER BY fam_Zip', 'Families to Canvass', 'People in families that are ok to canvass.', 0),
(28, 'SELECT fam_Name, a.plg_amount as PlgFY1, b.plg_amount as PlgFY2 from family_fam left join pledge_plg a on a.plg_famID = fam_ID and a.plg_FYID=~fyid1~ and a.plg_PledgeOrPayment=''Pledge'' left join pledge_plg b on b.plg_famID = fam_ID and b.plg_FYID=~fyid2~ and b.plg_PledgeOrPayment=''Pledge'' order by fam_Name', 'Pledge comparison', 'Compare pledges between two fiscal years', 1),
(30, 'SELECT per_ID as AddToCart, CONCAT(per_FirstName,'' '',per_LastName) AS Name, fam_address1, fam_city, fam_state, fam_zip FROM person_per join family_fam on per_fam_id=fam_id where per_fmr_id<>3 and per_fam_id in (select fam_id from family_fam inner join pledge_plg a on a.plg_famID=fam_ID and a.plg_FYID=~fyid1~ and a.plg_amount>0) and per_fam_id not in (select fam_id from family_fam inner join pledge_plg b on b.plg_famID=fam_ID and b.plg_FYID=~fyid2~ and b.plg_amount>0)', 'Missing pledges', 'Find people who pledged one year but not another', 1),
(31, 'select per_ID as AddToCart, per_FirstName, per_LastName, per_email from person_per, autopayment_aut where aut_famID=per_fam_ID and aut_CreditCard!="" and per_email!="" and (per_fmr_ID=1 or per_fmr_ID=2 or per_cls_ID=1)', 'Credit Card People', 'People who are configured to pay by credit card.', 0),
(32, 'SELECT fam_Name, fam_Envelope, b.fun_Name as Fund_Name, a.plg_amount as Pledge from family_fam left join pledge_plg a on a.plg_famID = fam_ID and a.plg_FYID=~fyid~ and a.plg_PledgeOrPayment=\'Pledge\' and a.plg_amount>0 join donationfund_fun b on b.fun_ID = a.plg_fundID order by fam_Name, a.plg_fundID', 'Family Pledge by Fiscal Year', 'Pledge summary by family name for each fund for the selected fiscal year', 1),
(100, 'SELECT a.per_ID as AddToCart, CONCAT(''<a href=PersonView.php?PersonID='',a.per_ID,''>'',a.per_FirstName,'' '',a.per_LastName,''</a>'') AS Name FROM person_per AS a LEFT JOIN person2volunteeropp_p2vo p2v1 ON (a.per_id = p2v1.p2vo_per_ID AND p2v1.p2vo_vol_ID = ~volopp1~) LEFT JOIN person2volunteeropp_p2vo p2v2 ON (a.per_id = p2v2.p2vo_per_ID AND p2v2.p2vo_vol_ID = ~volopp2~) WHERE p2v1.p2vo_per_ID=p2v2.p2vo_per_ID ORDER BY per_LastName', 'Volunteers', 'Find volunteers for who match two specific opportunity codes', 1),
(200, 'SELECT a.per_ID as AddToCart, CONCAT(''<a href=PersonView.php?PersonID='',a.per_ID,''>'',a.per_FirstName,'' '',a.per_LastName,''</a>'') AS Name FROM person_per AS a LEFT JOIN person_custom pc ON a.per_id = pc.per_ID WHERE pc.~custom~=''~value~'' ORDER BY per_LastName', 'CustomSearch', 'Find people with a custom field value', 1);

-- --------------------------------------------------------

--
-- Table structure for table `record2property_r2p`
--

CREATE TABLE `record2property_r2p` (
  `r2p_pro_ID` mediumint(8) unsigned NOT NULL default '0',
  `r2p_record_ID` mediumint(8) unsigned NOT NULL default '0',
  `r2p_Value` text NOT NULL
) ENGINE=MyISAM CHARACTER SET utf8 COLLATE utf8_unicode_ci;

--
-- Dumping data for table `record2property_r2p`
--


-- --------------------------------------------------------

--
-- Table structure for table `result_res`
--

CREATE TABLE `result_res` (
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
) ENGINE=MyISAM CHARACTER SET utf8 COLLATE utf8_unicode_ci AUTO_INCREMENT=1 ;

--
-- Dumping data for table `result_res`
--


-- --------------------------------------------------------

--
-- Table structure for table `userconfig_ucfg`
--

CREATE TABLE `userconfig_ucfg` (
  `ucfg_per_id` mediumint(9) unsigned NOT NULL,
  `ucfg_id` int(11) NOT NULL default '0',
  `ucfg_name` varchar(50) NOT NULL default '',
  `ucfg_value` text,
  `ucfg_type` enum('text','number','date','boolean','textarea') NOT NULL default 'text',
  `ucfg_tooltip` text NOT NULL,
  `ucfg_permission` enum('FALSE','TRUE') NOT NULL default 'FALSE',
  `ucfg_cat` varchar(20) NOT NULL,
  PRIMARY KEY  (`ucfg_per_id`,`ucfg_id`)
) ENGINE=MyISAM CHARACTER SET utf8 COLLATE utf8_unicode_ci;

--
-- Dumping data for table `userconfig_ucfg`
--

INSERT INTO `userconfig_ucfg` (`ucfg_per_id`, `ucfg_id`, `ucfg_name`, `ucfg_value`, `ucfg_type`, `ucfg_tooltip`, `ucfg_permission`, `ucfg_cat`) VALUES
(0, 0, 'bEmailMailto', '1', 'boolean', 'User permission to send email via mailto: links', 'TRUE', ''),
(0, 1, 'sMailtoDelimiter', ',', 'text', 'Delimiter to separate emails in mailto: links', 'TRUE', ''),
(0, 2, 'bSendPHPMail', '0', 'boolean', 'User permission to send email using PHPMailer', 'FALSE', ''),
(0, 3, 'sFromEmailAddress', '', 'text', 'Reply email address: PHPMailer', 'FALSE', ''),
(0, 4, 'sFromName', 'ChurchCRM Webmaster', 'text', 'Name that appears in From field: PHPMailer', 'FALSE', ''),
(0, 5, 'bCreateDirectory', '0', 'boolean', 'User permission to create directories', 'FALSE', 'SECURITY'),
(0, 6, 'bExportCSV', '0', 'boolean', 'User permission to export CSV files', 'FALSE', 'SECURITY'),
(0, 7, 'bUSAddressVerification', '0', 'boolean', 'User permission to use IST Address Verification', 'FALSE', ''),
(1, 0, 'bEmailMailto', '1', 'boolean', 'User permission to send email via mailto: links', 'TRUE', ''),
(1, 1, 'sMailtoDelimiter', ',', 'text', 'user permission to send email via mailto: links', 'TRUE', ''),
(1, 2, 'bSendPHPMail', '1', 'boolean', 'User permission to send email using PHPMailer', 'TRUE', ''),
(1, 3, 'sFromEmailAddress', '', 'text', 'Reply email address for PHPMailer', 'TRUE', ''),
(1, 4, 'sFromName', 'ChurchCRM Webmaster', 'text', 'Name that appears in From field', 'TRUE', ''),
(1, 5, 'bCreateDirectory', '1', 'boolean', 'User permission to create directories', 'TRUE', ''),
(1, 6, 'bExportCSV', '1', 'boolean', 'User permission to export CSV files', 'TRUE', ''),
(1, 7, 'bUSAddressVerification', '1', 'boolean', 'User permission to use IST Address Verification', 'TRUE', ''),
(0, 10, 'bAddEvent', '0', 'boolean', 'Allow user to add new event', 'FALSE', 'SECURITY'),
(0, 11, 'bSeePrivacyData', '0', 'boolean', 'Allow user to see member privacy data, e.g. Birth Year, Age.', 'FALSE', 'SECURITY');

-- --------------------------------------------------------

--
-- Table structure for table `user_usr`
--

CREATE TABLE `user_usr` (
  `usr_per_ID` mediumint(9) unsigned NOT NULL default '0',
  `usr_Password` varchar(500) NOT NULL default '',
  `usr_NeedPasswordChange` tinyint(3) unsigned NOT NULL default '1',
  `usr_LastLogin` datetime NOT NULL default '0000-00-00 00:00:00',
  `usr_LoginCount` smallint(5) unsigned NOT NULL default '0',
  `usr_FailedLogins` tinyint(3) unsigned NOT NULL default '0',
  `usr_AddRecords` tinyint(3) unsigned NOT NULL default '0',
  `usr_EditRecords` tinyint(3) unsigned NOT NULL default '0',
  `usr_DeleteRecords` tinyint(3) unsigned NOT NULL default '0',
  `usr_MenuOptions` tinyint(3) unsigned NOT NULL default '0',
  `usr_ManageGroups` tinyint(3) unsigned NOT NULL default '0',
  `usr_Finance` tinyint(3) unsigned NOT NULL default '0',
  `usr_Communication` tinyint(3) unsigned NOT NULL default '0',
  `usr_Notes` tinyint(3) unsigned NOT NULL default '0',
  `usr_Admin` tinyint(3) unsigned NOT NULL default '0',
  `usr_Workspacewidth` smallint(6) default NULL,
  `usr_BaseFontSize` tinyint(4) default NULL,
  `usr_SearchLimit` tinyint(4) default '10',
  `usr_Style` varchar(50) default 'Style.css',
  `usr_showPledges` tinyint(1) NOT NULL default '0',
  `usr_showPayments` tinyint(1) NOT NULL default '0',
  `usr_showSince` date NOT NULL default '0000-00-00',
  `usr_defaultFY` mediumint(9) NOT NULL default '10',
  `usr_currentDeposit` mediumint(9) NOT NULL default '0',
  `usr_UserName` varchar(32) default NULL,
  `usr_EditSelf` tinyint(3) unsigned NOT NULL default '0',
  `usr_CalStart` date default NULL,
  `usr_CalEnd` date default NULL,
  `usr_CalNoSchool1` date default NULL,
  `usr_CalNoSchool2` date default NULL,
  `usr_CalNoSchool3` date default NULL,
  `usr_CalNoSchool4` date default NULL,
  `usr_CalNoSchool5` date default NULL,
  `usr_CalNoSchool6` date default NULL,
  `usr_CalNoSchool7` date default NULL,
  `usr_CalNoSchool8` date default NULL,
  `usr_SearchFamily` tinyint(3) default NULL,
  `usr_Canvasser` tinyint(3) NOT NULL default '0',
  PRIMARY KEY  (`usr_per_ID`),
  UNIQUE KEY `usr_UserName` (`usr_UserName`),
  KEY `usr_per_ID` (`usr_per_ID`)
) ENGINE=MyISAM CHARACTER SET utf8 COLLATE utf8_unicode_ci;

--
-- Dumping data for table `user_usr`
--

INSERT INTO `user_usr` (`usr_per_ID`, `usr_Password`, `usr_NeedPasswordChange`, `usr_LastLogin`,
`usr_LoginCount`, `usr_FailedLogins`, `usr_AddRecords`, `usr_EditRecords`, `usr_DeleteRecords`,
`usr_MenuOptions`, `usr_ManageGroups`, `usr_Finance`, `usr_Communication`, `usr_Notes`, `usr_Admin`,
`usr_Workspacewidth`, `usr_BaseFontSize`, `usr_SearchLimit`, `usr_Style`, `usr_showPledges`,
`usr_showPayments`, `usr_showSince`, `usr_defaultFY`, `usr_currentDeposit`, `usr_UserName`, `usr_EditSelf`,
`usr_CalStart`, `usr_CalEnd`, `usr_CalNoSchool1`, `usr_CalNoSchool2`, `usr_CalNoSchool3`, `usr_CalNoSchool4`,
`usr_CalNoSchool5`, `usr_CalNoSchool6`, `usr_CalNoSchool7`, `usr_CalNoSchool8`, `usr_SearchFamily`,
`usr_Canvasser`)
VALUES
(1, '4bdf3fba58c956fc3991a1fde84929223f968e2853de596e49ae80a91499609b', 1, '0000-00-00 00:00:00', 0, 0, 1, 1, 1, 1, 1, 1, 1, 1, 1, 580, 9, 10, 'skin-blue', 0, 0, '0000-00-00', 10, 0, 'Admin', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0);

-- --------------------------------------------------------

--
-- Table structure for table `version_ver`
--

CREATE TABLE `version_ver` (
  `ver_ID` mediumint(9) unsigned NOT NULL auto_increment,
  `ver_version` varchar(50) NOT NULL default '',
  `ver_date` datetime default NULL,
  PRIMARY KEY  (`ver_ID`),
  UNIQUE KEY `ver_version` (`ver_version`)
) ENGINE=MyISAM CHARACTER SET utf8 COLLATE utf8_unicode_ci  AUTO_INCREMENT=1 ;

--
-- Dumping data for table `version_ver`
--

INSERT INTO `version_ver` (`ver_version`, `ver_date`) VALUES
('2.0.0', NOW() );

-- --------------------------------------------------------

--
-- Table structure for table `volunteeropportunity_vol`
--

CREATE TABLE `volunteeropportunity_vol` (
  `vol_ID` int(3) NOT NULL auto_increment,
  `vol_Order` int(3) NOT NULL default '0',
  `vol_Active` enum('true','false') NOT NULL default 'true',
  `vol_Name` varchar(30) default NULL,
  `vol_Description` varchar(100) default NULL,
  PRIMARY KEY  (`vol_ID`),
  UNIQUE KEY `vol_ID` (`vol_ID`)
) ENGINE=MyISAM CHARACTER SET utf8 COLLATE utf8_unicode_ci AUTO_INCREMENT=1 ;

--
-- Dumping data for table `volunteeropportunity_vol`
--


-- --------------------------------------------------------

--
-- Table structure for table `whycame_why`
--

CREATE TABLE `whycame_why` (
  `why_ID` mediumint(9) NOT NULL auto_increment,
  `why_per_ID` mediumint(9) NOT NULL default '0',
  `why_join` text NOT NULL,
  `why_come` text NOT NULL,
  `why_suggest` text NOT NULL,
  `why_hearOfUs` text NOT NULL,
  PRIMARY KEY  (`why_ID`)
) ENGINE=MyISAM CHARACTER SET utf8 COLLATE utf8_unicode_ci AUTO_INCREMENT=1 ;

--
-- Fundraiser support added 4/11/2009 Michael Wilt
--

CREATE TABLE `paddlenum_pn` (
  `pn_ID` mediumint(9) unsigned NOT NULL auto_increment,
  `pn_fr_ID` mediumint(9) unsigned,
  `pn_Num` mediumint(9) unsigned,
  `pn_per_ID` mediumint(9) NOT NULL default '0',
  PRIMARY KEY  (`pn_ID`),
  UNIQUE KEY `pn_ID` (`pn_ID`)
) ENGINE=MyISAM CHARACTER SET utf8 COLLATE utf8_unicode_ci AUTO_INCREMENT=1 ;

CREATE TABLE `fundraiser_fr` (
  `fr_ID` mediumint(9) unsigned NOT NULL auto_increment,
  `fr_date` date default NULL,
  `fr_title` varchar(128) NOT NULL,
  `fr_description` text,
  `fr_EnteredBy` smallint(5) unsigned NOT NULL default '0',
  `fr_EnteredDate` date NOT NULL,
  PRIMARY KEY  (`fr_ID`),
  UNIQUE KEY `fr_ID` (`fr_ID`)
) ENGINE=MyISAM CHARACTER SET utf8 COLLATE utf8_unicode_ci AUTO_INCREMENT=1 ;

CREATE TABLE `donateditem_di` (
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
  `di_picture` text,
  PRIMARY KEY  (`di_ID`),
  UNIQUE KEY `di_ID` (`di_ID`)
) ENGINE=MyISAM CHARACTER SET utf8 COLLATE utf8_unicode_ci AUTO_INCREMENT=1 ;

CREATE TABLE `multibuy_mb` (
  `mb_ID` mediumint(9) unsigned NOT NULL auto_increment,
  `mb_per_ID` mediumint(9) NOT NULL default '0',
  `mb_item_ID` mediumint(9) NOT NULL default '0',
  `mb_count` decimal(8,0) default NULL,
  PRIMARY KEY  (`mb_ID`),
  UNIQUE KEY `mb_ID` (`mb_ID`)
) ENGINE=MyISAM CHARACTER SET utf8 COLLATE utf8_unicode_ci AUTO_INCREMENT=1 ;

CREATE TABLE `egive_egv` (
  `egv_egiveID` varchar(16) character set utf8 NOT NULL,
  `egv_famID` int(11) NOT NULL,
  `egv_DateEntered` datetime NOT NULL,
  `egv_DateLastEdited` datetime NOT NULL,
  `egv_EnteredBy` smallint(6) NOT NULL default '0',
  `egv_EditedBy` smallint(6) NOT NULL default '0'
) ENGINE=MyISAM CHARACTER SET utf8 COLLATE utf8_unicode_ci;
