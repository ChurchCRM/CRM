-- MySQL dump 10.13  Distrib 5.5.46, for debian-linux-gnu (x86_64)
--
-- Host: localhost    Database: churchcrm
-- ------------------------------------------------------
-- Server version	5.5.46-0ubuntu0.14.04.2

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `autopayment_aut`
--

DROP TABLE IF EXISTS `autopayment_aut`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `autopayment_aut` (
  `aut_ID` mediumint(9) unsigned NOT NULL AUTO_INCREMENT,
  `aut_FamID` mediumint(9) unsigned NOT NULL DEFAULT '0',
  `aut_EnableBankDraft` tinyint(1) unsigned NOT NULL DEFAULT '0',
  `aut_EnableCreditCard` tinyint(1) unsigned NOT NULL DEFAULT '0',
  `aut_NextPayDate` date DEFAULT NULL,
  `aut_FYID` mediumint(9) NOT NULL DEFAULT '9',
  `aut_Amount` decimal(6,2) NOT NULL DEFAULT '0.00',
  `aut_Interval` tinyint(3) NOT NULL DEFAULT '1',
  `aut_Fund` mediumint(6) NOT NULL DEFAULT '0',
  `aut_FirstName` varchar(50) COLLATE utf8_unicode_ci DEFAULT NULL,
  `aut_LastName` varchar(50) COLLATE utf8_unicode_ci DEFAULT NULL,
  `aut_Address1` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `aut_Address2` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `aut_City` varchar(50) COLLATE utf8_unicode_ci DEFAULT NULL,
  `aut_State` varchar(50) COLLATE utf8_unicode_ci DEFAULT NULL,
  `aut_Zip` varchar(50) COLLATE utf8_unicode_ci DEFAULT NULL,
  `aut_Country` varchar(50) COLLATE utf8_unicode_ci DEFAULT NULL,
  `aut_Phone` varchar(30) COLLATE utf8_unicode_ci DEFAULT NULL,
  `aut_Email` varchar(100) COLLATE utf8_unicode_ci DEFAULT NULL,
  `aut_CreditCard` varchar(50) COLLATE utf8_unicode_ci DEFAULT NULL,
  `aut_ExpMonth` varchar(2) COLLATE utf8_unicode_ci DEFAULT NULL,
  `aut_ExpYear` varchar(4) COLLATE utf8_unicode_ci DEFAULT NULL,
  `aut_BankName` varchar(50) COLLATE utf8_unicode_ci DEFAULT NULL,
  `aut_Route` varchar(30) COLLATE utf8_unicode_ci DEFAULT NULL,
  `aut_Account` varchar(30) COLLATE utf8_unicode_ci DEFAULT NULL,
  `aut_DateLastEdited` datetime DEFAULT NULL,
  `aut_EditedBy` smallint(5) unsigned DEFAULT '0',
  `aut_Serial` mediumint(9) NOT NULL DEFAULT '1',
  `aut_CreditCardVanco` varchar(50) COLLATE utf8_unicode_ci DEFAULT NULL,
  `aut_AccountVanco` varchar(50) COLLATE utf8_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`aut_ID`),
  UNIQUE KEY `aut_ID` (`aut_ID`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `autopayment_aut`
--

LOCK TABLES `autopayment_aut` WRITE;
/*!40000 ALTER TABLE `autopayment_aut` DISABLE KEYS */;
/*!40000 ALTER TABLE `autopayment_aut` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `canvassdata_can`
--

DROP TABLE IF EXISTS `canvassdata_can`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `canvassdata_can` (
  `can_ID` mediumint(9) unsigned NOT NULL AUTO_INCREMENT,
  `can_famID` mediumint(9) NOT NULL DEFAULT '0',
  `can_Canvasser` mediumint(9) NOT NULL DEFAULT '0',
  `can_FYID` mediumint(9) DEFAULT NULL,
  `can_date` date DEFAULT NULL,
  `can_Positive` text COLLATE utf8_unicode_ci,
  `can_Critical` text COLLATE utf8_unicode_ci,
  `can_Insightful` text COLLATE utf8_unicode_ci,
  `can_Financial` text COLLATE utf8_unicode_ci,
  `can_Suggestion` text COLLATE utf8_unicode_ci,
  `can_NotInterested` tinyint(1) NOT NULL DEFAULT '0',
  `can_WhyNotInterested` text COLLATE utf8_unicode_ci,
  PRIMARY KEY (`can_ID`),
  UNIQUE KEY `can_ID` (`can_ID`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `canvassdata_can`
--

LOCK TABLES `canvassdata_can` WRITE;
/*!40000 ALTER TABLE `canvassdata_can` DISABLE KEYS */;
/*!40000 ALTER TABLE `canvassdata_can` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `config_cfg`
--

DROP TABLE IF EXISTS `config_cfg`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `config_cfg` (
  `cfg_id` int(11) NOT NULL DEFAULT '0',
  `cfg_name` varchar(50) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  `cfg_value` text COLLATE utf8_unicode_ci,
  `cfg_type` enum('text','number','date','boolean','textarea') COLLATE utf8_unicode_ci NOT NULL DEFAULT 'text',
  `cfg_default` text COLLATE utf8_unicode_ci NOT NULL,
  `cfg_tooltip` text COLLATE utf8_unicode_ci NOT NULL,
  `cfg_section` varchar(50) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  `cfg_category` varchar(20) COLLATE utf8_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`cfg_id`),
  UNIQUE KEY `cfg_name` (`cfg_name`),
  KEY `cfg_id` (`cfg_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `config_cfg`
--

LOCK TABLES `config_cfg` WRITE;
/*!40000 ALTER TABLE `config_cfg` DISABLE KEYS */;
INSERT INTO `config_cfg` VALUES (1,'sWEBCALENDARDB','','text','','WebCalendar database name','General',NULL),(2,'debug','1','boolean','1','Set debug mode\r\nThis may be helpful for when you\'re first setting up ChurchInfo, but you should\r\nprobably turn it off for maximum security otherwise.  If you are having trouble,\r\nplease enable this so that you\'ll know what the errors are.  This is especially\r\nimportant if you need to report a problem on the help forums.','General',NULL),(3,'sJPGRAPH_PATH','Include/jpgraph-1.13/src','text','Include/jpgraph-1.13/src','JPGraph library','General',NULL),(4,'sFPDF_PATH','Include/fpdf17','text','Include/fpdf17','FPDF library','General',NULL),(5,'sDirClassifications','1,2,4,5','text','1,2,4,5','Include only these classifications in the directory, comma seperated','General',NULL),(6,'sDirRoleHead','1,7','text','1,7','These are the family role numbers designated as head of house','General',NULL),(7,'sDirRoleSpouse','2','text','2','These are the family role numbers designated as spouse','General',NULL),(8,'sDirRoleChild','3','text','3','These are the family role numbers designated as child','General',NULL),(9,'sSessionTimeout','3600','number','3600','Session timeout length in seconds\rSet to zero to disable session timeouts.','General',NULL),(10,'aFinanceQueries','28,30,31,32','text','28','Queries for which user must have finance permissions to use:','General',NULL),(11,'bCSVAdminOnly','1','boolean','1','Should only administrators have access to the CSV export system and directory report?','General',NULL),(12,'sDefault_Pass','password','text','password','Default password for new users and those with reset passwords','General',NULL),(13,'sMinPasswordLength','6','number','6','Minimum length a user may set their password to','General',NULL),(14,'sMinPasswordChange','4','number','4','Minimum amount that a new password must differ from the old one (# of characters changed)\rSet to zero to disable this feature','General',NULL),(15,'sDisallowedPasswords','churchinfo,password,god,jesus,church,christian','text','churchinfo,password,god,jesus,church,christian','A comma-seperated list of disallowed (too obvious) passwords.','General',NULL),(16,'iMaxFailedLogins','50','number','50','Maximum number of failed logins to allow before a user account is locked.\rOnce the maximum has been reached, an administrator must re-enable the account.\rThis feature helps to protect against automated password guessing attacks.\rSet to zero to disable this feature.','General',NULL),(17,'bToolTipsOn','','boolean','','Turn on or off guided help (Tool Tips).\rThis feature is not complete.  Leave off for now.','General',NULL),(18,'iNavMethod','1','number','1','Interface navigation method\r1 = Javascript MenuBar (default)\r2 = Flat Sidebar (alternative for buggy browsers)','General',NULL),(19,'bFamListFirstNames','1','boolean','1','Show family member firstnames in Family Listing?','General',NULL),(20,'iPDFOutputType','1','number','1','PDF handling mode.\r1 = Save File dialog\r2 = Open in current browser window','General',NULL),(21,'sDefaultCity','','text','','Default City','General',NULL),(22,'sDefaultState','','text','','Default State - Must be 2-letter abbreviation!','General',NULL),(23,'sDefaultCountry','United States','text','United States','Default Country','General',NULL),(24,'bEmailSend','','boolean','','If you wish to be able to send emails from within ChurchInfo. This requires\reither an SMTP server address to send from or sendmail installed in PHP.','General',NULL),(25,'sSendType','smtp','text','smtp','The method for sending email. Either \"smtp\" or \"sendmail\"','General',NULL),(26,'sToEmailAddress','','text','','Default account for receiving a copy of all emails','General',NULL),(27,'sSMTPHost','','text','','SMTP Server Address (mail.server.com:25)','General',NULL),(28,'sSMTPAuth','1','boolean','1','Does your SMTP server require auththentication (username/password)?','General',NULL),(29,'sSMTPUser','','text','','SMTP Username','General',NULL),(30,'sSMTPPass','','text','','SMTP Password','General',NULL),(31,'sWordWrap','72','number','72','Word Wrap point. Default for most email programs is 72','General',NULL),(32,'bDefectiveBrowser','1','boolean','1','Are you using any non-standards-compliant \"broken\" browsers at this installation?\rIf so, enabling this will turn off the CSS tags that make the menubar stay\rat the top of the screen instead of scrolling with the rest of the page.\rIt will also turn off the use of nice, alpha-blended PNG images, which IE still\rdoes not properly handle.\rNOTICE: MS Internet Explorer is currently not standards-compliant enough for\rthese purposes.  Please use a quality web browser such as Netscape 7, Firefox, etc.\r','General',NULL),(33,'bShowFamilyData','1','boolean','1','Unavailable person info inherited from assigned family for display?\rThis option causes certain info from a person\'s assigned family record to be\rdisplayed IF the corresponding info has NOT been entered for that person. ','General',NULL),(34,'bOldVCardVersion','','boolean','','Use vCard 2.1 rather than vCard 3.0 standard.','General',NULL),(35,'bEnableBackupUtility','1','boolean','','This backup system only works on \"UNIX-style\" operating systems such as\rGNU/Linux, OSX and the BSD variants (NOT Windows).\rOf course, remember that only your web server needs to running a UNIX-style\rOS for this feature to work.','General',NULL),(36,'sGZIPname','gzip','text','gzip','','General',NULL),(37,'sZIPname','zip','text','zip','','General',NULL),(38,'sPGPname','gpg','text','gpg','','General',NULL),(39,'sLanguage','en_US','text','en_US','Internationalization (I18n) support\rUS English (en_US), Italian (it_IT), French (fr_FR), and German (de_DE)','General',NULL),(40,'iFYMonth','1','number','1','First month of the fiscal year','General',NULL),(41,'sXML_RPC_PATH','XML/RPC.php','text','XML/RPC.php','Path to RPC.php, required for Lat/Lon address lookup','General',NULL),(42,'sGeocoderID','','text','','User ID for rpc.geocoder.us','General',NULL),(43,'sGeocoderPW','','text','','Password for rpc.geocoder.us','General',NULL),(44,'sGoogleMapKey','','text','','Google map API requires a unique key from http://maps.google.com/apis/maps/signup.html','General',NULL),(45,'nChurchLatitude','40.4403606','number','','Latitude of the church, used to center the Google map','General',NULL),(46,'nChurchLongitude','-75.3549482','number','','Longitude of the church, used to center the Google map','General',NULL),(47,'bHidePersonAddress','1','boolean','1','Set true to disable entering addresses in Person Editor.  Set false to enable entering addresses in Person Editor.','General',NULL),(48,'bHideFriendDate','','boolean','0','Set true to disable entering Friend Date in Person Editor.  Set false to enable entering Friend Date in Person Editor.','General',NULL),(49,'bHideFamilyNewsletter','','boolean','0','Set true to disable management of newsletter subscriptions in the Family Editor.','General',NULL),(50,'bHideWeddingDate','','boolean','0','Set true to disable entering Wedding Date in Family Editor.  Set false to enable entering Wedding Date in Family Editor.','General',NULL),(51,'bHideLatLon','','boolean','0','Set true to disable entering Latitude and Longitude in Family Editor.  Set false to enable entering Latitude and Longitude in Family Editor.  Lookups are still performed, just not displayed.','General',NULL),(52,'bUseDonationEnvelopes','','boolean','0','Set true to enable use of donation envelopes','General',NULL),(53,'sHeader','','textarea','','Enter in HTML code which will be displayed as a header at the top of each page. Be sure to close your tags! Note: You must REFRESH YOUR BROWSER A SECOND TIME to view the new header.','General',NULL),(54,'sISTusername','username','text','username','Intelligent Search Technolgy, Ltd. CorrectAddress Username for https://www.intelligentsearch.com/Hosted/User','General',NULL),(55,'sISTpassword','','text','','Intelligent Search Technolgy, Ltd. CorrectAddress Password for https://www.intelligentsearch.com/Hosted/User','General',NULL),(56,'bUseGoogleGeocode','1','boolean','1','Set true to use the Google geocoder.  Set false to use rpc.geocoder.us.','General',NULL),(57,'iChecksPerDepositForm','14','number','14','Number of checks for Deposit Slip Report','General',NULL),(58,'bUseScannedChecks','','boolean','0','Set true to enable use of scanned checks','General',NULL),(61,'iEventPeriodStartHr','7','number','7','Church Event Valid Period Start Hour (0-23)','General',NULL),(62,'iEventPeriodEndHr','18','number','18','Church Event Valid Period End Hour (0-23, must be greater than iEventStartHr)','General',NULL),(63,'iEventPeriodIntervalMin','15','number','15','Event Period interval (in minutes)','General',NULL),(64,'sDistanceUnit','miles','text','miles','Unit used to measure distance, miles or km.','General',NULL),(65,'sTimeZone','America/New_York','text','America/New_York','Time zone- see http://php.net/manual/en/timezones.php for valid choices.','General',NULL),(66,'sGMapIcons','red-dot,Probert-dot,purple,yellow-dot,blue-dot,orange,yellow,Probert,blue,red,pink,lightblue','text','red-dot,Probert-dot,purple,yellow-dot,blue-dot,orange,yellow,Probert,blue,red,pink,lightblue','Names of markers for Google Maps in order of classification','General',NULL),(67,'cfgForceUppercaseZip','','boolean','0','Make user-entered zip/postcodes UPPERCASE when saving to the database. Useful in the UK.','General',NULL),(2001,'mailChimpApiKey','','text','','see http://kb.mailchimp.com/accounts/management/about-api-keys','General',NULL),(72,'bEnableNonDeductible','','boolean','0','Enable non-deductible payments','General',NULL),(73,'sElectronicTransactionProcessor','Vanco','text','Vanco','Electronic Transaction Processor','General',NULL),(999,'bRegistered','1','boolean','0','ChurchInfo has been registered.  The ChurchInfo team uses registration information to track usage.  This information is kept confidential and never released or sold.  If this field is true the registration option in the admin menu changes to update registration.','General',NULL),(1001,'leftX','20','number','20','Left Margin (1 = 1/100th inch)','ChurchInfoReport',NULL),(1002,'incrementY','4','number','4','Line Thickness (1 = 1/100th inch','ChurchInfoReport',NULL),(1003,'sChurchName','Some Great Church','text','','Church Name','ChurchInfoReport',NULL),(1004,'sChurchAddress','4692 Academy Street','text','','Church Address','ChurchInfoReport',NULL),(1005,'sChurchCity','Dekalb','text','','Church City','ChurchInfoReport',NULL),(1006,'sChurchState','IL','text','','Church State','ChurchInfoReport',NULL),(1007,'sChurchZip','60115','text','','Church Zip','ChurchInfoReport',NULL),(1008,'sChurchPhone','421-875-7777','text','','Church Phone','ChurchInfoReport',NULL),(1009,'sChurchEmail','someone@somewhere.com','text','','Church Email','ChurchInfoReport',NULL),(1010,'sHomeAreaCode','815','text','','Home area code of the church','ChurchInfoReport',NULL),(1011,'sTaxReport1','This letter shows our record of your payments for','text','This letter shows our record of your payments for','Verbage for top line of tax report. Dates will be appended to the end of this line.','ChurchInfoReport',NULL),(1012,'sTaxReport2','Thank you for your help in making a difference. We greatly appreciate your gift!','text','Thank you for your help in making a difference. We greatly appreciate your gift!','Verbage for bottom line of tax report.','ChurchInfoReport',NULL),(1013,'sTaxReport3','If you have any questions or corrections to make to this report, please contact the church at the above number during business hours, 9am to 4pm, M-F.','text','If you have any questions or corrections to make to this report, please contact the church at the above number during business hours, 9am to 4pm, M-F.','Verbage for bottom line of tax report.','ChurchInfoReport',NULL),(1014,'sTaxSigner','-','text','','Tax Report signer','ChurchInfoReport',NULL),(1015,'sReminder1','This letter shows our record of your pledge and payments for fiscal year','text','This letter shows our record of your pledge and payments for fiscal year','Verbage for the pledge reminder report','ChurchInfoReport',NULL),(1016,'sReminderSigner','-','text','','Pledge Reminder Signer','ChurchInfoReport',NULL),(1017,'sReminderNoPledge','Pledges: We do not have record of a pledge for from you for this fiscal year.','text','Pledges: We do not have record of a pledge for from you for this fiscal year.','Verbage for the pledge reminder report - No record of a pledge','ChurchInfoReport',NULL),(1018,'sReminderNoPayments','Payments: We do not have record of a pledge for from you for this fiscal year.','text','Payments: We do not have record of a pledge for from you for this fiscal year.','Verbage for the pledge reminder report - No record of payments','ChurchInfoReport',NULL),(1019,'sConfirm1','This letter shows the information we have in our database with respect to your family.  Please review, mark-up as necessary, and return this form to the church office, or email someone@somewhere.com with your changes.','text','This letter shows the information we have in our database with respect to your family.  Please review, mark-up as necessary, and return this form to the church office.','Verbage for the database information confirmation and correction report','ChurchInfoReport',NULL),(1020,'sConfirm2','If you have any other family members that are not listed here, please add them below, along with any relevant details.','text','Thank you very much for helping us to update this information.  If you want on-line access to the church database please provide your email address and a desired password and we will send instructions.','Verbage for the database information confirmation and correction report','ChurchInfoReport',NULL),(1021,'sConfirm3','','text','Email _____________________________________ Password ________________','Verbage for the database information confirmation and correction report','ChurchInfoReport',NULL),(1022,'sConfirm4','','text','[  ] I no longer want to be associated with the church (check here to be removed from our records).','Verbage for the database information confirmation and correction report','ChurchInfoReport',NULL),(1023,'sConfirm5','','text','','Verbage for the database information confirmation and correction report','ChurchInfoReport',NULL),(1024,'sConfirm6','','text','','Verbage for the database information confirmation and correction report','ChurchInfoReport',NULL),(1025,'sConfirmSigner','','text','','Database information confirmation and correction report signer','ChurchInfoReport',NULL),(1026,'sPledgeSummary1','Summary of pledges and payments for the fiscal year','text','Summary of pledges and payments for the fiscal year','Verbage for the pledge summary report','ChurchInfoReport',NULL),(1027,'sPledgeSummary2','as of','text',' as of','Verbage for the pledge summary report','ChurchInfoReport',NULL),(1028,'sDirectoryDisclaimer1','Every effort was made to insure the accuracy of this directory.  If there are any errors or omissions, please contact the church office.This directory is for the use of the people of','text','Every effort was made to insure the accuracy of this directory.  If there are any errors or omissions, please contact the church office.\n\nThis directory is for the use of the people of','Verbage for the directory report','ChurchInfoReport',NULL),(1029,'sDirectoryDisclaimer2',', and the information contained in it may not be used for business or commercial purposes.','text',', and the information contained in it may not be used for business or commercial purposes.','Verbage for the directory report','ChurchInfoReport',NULL),(1030,'bDirLetterHead','../Images/church_letterhead.jpg','text','../Images/church_letterhead.jpg','Church Letterhead path and file','ChurchInfoReport',NULL),(1031,'sZeroGivers','This letter shows our record of your payments for','text','This letter shows our record of your payments for','Verbage for top line of tax report. Dates will be appended to the end of this line.','ChurchInfoReport',NULL),(1032,'sZeroGivers2','Thank you for your help in making a difference. We greatly appreciate your gift!','text','Thank you for your help in making a difference. We greatly appreciate your gift!','Verbage for bottom line of tax report.','ChurchInfoReport',NULL),(1033,'sZeroGivers3','If you have any questions or corrections to make to this report, please contact the church at the above number during business hours, 9am to 4pm, M-F.','text','If you have any questions or corrections to make to this report, please contact the church at the above number during business hours, 9am to 4pm, M-F.','Verbage for bottom line of tax report.','ChurchInfoReport',NULL);
/*!40000 ALTER TABLE `config_cfg` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `deposit_dep`
--

DROP TABLE IF EXISTS `deposit_dep`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `deposit_dep` (
  `dep_ID` mediumint(9) unsigned NOT NULL AUTO_INCREMENT,
  `dep_Date` date DEFAULT NULL,
  `dep_Comment` text COLLATE utf8_unicode_ci,
  `dep_EnteredBy` mediumint(9) unsigned DEFAULT NULL,
  `dep_Closed` tinyint(1) NOT NULL DEFAULT '0',
  `dep_Type` enum('Bank','CreditCard','BankDraft','eGive') COLLATE utf8_unicode_ci NOT NULL DEFAULT 'Bank',
  PRIMARY KEY (`dep_ID`)
) ENGINE=MyISAM AUTO_INCREMENT=3 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci PACK_KEYS=0;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `deposit_dep`
--

LOCK TABLES `deposit_dep` WRITE;
/*!40000 ALTER TABLE `deposit_dep` DISABLE KEYS */;
INSERT INTO `deposit_dep` VALUES (1,'2015-11-22','Sunday Morning Deposit',2,1,'Bank'),(2,'2015-11-29','11-29-2015 Sunday Service',2,1,'Bank');
/*!40000 ALTER TABLE `deposit_dep` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `donateditem_di`
--

DROP TABLE IF EXISTS `donateditem_di`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `donateditem_di` (
  `di_ID` mediumint(9) unsigned NOT NULL AUTO_INCREMENT,
  `di_item` varchar(32) COLLATE utf8_unicode_ci NOT NULL,
  `di_FR_ID` mediumint(9) unsigned NOT NULL,
  `di_donor_ID` mediumint(9) NOT NULL DEFAULT '0',
  `di_buyer_ID` mediumint(9) NOT NULL DEFAULT '0',
  `di_multibuy` smallint(1) NOT NULL DEFAULT '0',
  `di_title` varchar(128) COLLATE utf8_unicode_ci NOT NULL,
  `di_description` text COLLATE utf8_unicode_ci,
  `di_sellprice` decimal(8,2) DEFAULT NULL,
  `di_estprice` decimal(8,2) DEFAULT NULL,
  `di_minimum` decimal(8,2) DEFAULT NULL,
  `di_materialvalue` decimal(8,2) DEFAULT NULL,
  `di_EnteredBy` smallint(5) unsigned NOT NULL DEFAULT '0',
  `di_EnteredDate` date NOT NULL,
  `di_picture` text COLLATE utf8_unicode_ci,
  PRIMARY KEY (`di_ID`),
  UNIQUE KEY `di_ID` (`di_ID`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `donateditem_di`
--

LOCK TABLES `donateditem_di` WRITE;
/*!40000 ALTER TABLE `donateditem_di` DISABLE KEYS */;
/*!40000 ALTER TABLE `donateditem_di` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `donationfund_fun`
--

DROP TABLE IF EXISTS `donationfund_fun`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `donationfund_fun` (
  `fun_ID` tinyint(3) NOT NULL AUTO_INCREMENT,
  `fun_Active` enum('true','false') COLLATE utf8_unicode_ci NOT NULL DEFAULT 'true',
  `fun_Name` varchar(30) COLLATE utf8_unicode_ci DEFAULT NULL,
  `fun_Description` varchar(100) COLLATE utf8_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`fun_ID`),
  UNIQUE KEY `fun_ID` (`fun_ID`)
) ENGINE=MyISAM AUTO_INCREMENT=6 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `donationfund_fun`
--

LOCK TABLES `donationfund_fun` WRITE;
/*!40000 ALTER TABLE `donationfund_fun` DISABLE KEYS */;
INSERT INTO `donationfund_fun` VALUES (1,'true','General','Pledge income for the operating budget'),(2,'true','Childrens Ministry',''),(3,'true','Music Ministry',''),(4,'true','Building Fund','Rent');
/*!40000 ALTER TABLE `donationfund_fun` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `egive_egv`
--

DROP TABLE IF EXISTS `egive_egv`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `egive_egv` (
  `egv_egiveID` varchar(16) CHARACTER SET utf8 NOT NULL,
  `egv_famID` int(11) NOT NULL,
  `egv_DateEntered` datetime NOT NULL,
  `egv_DateLastEdited` datetime NOT NULL,
  `egv_EnteredBy` smallint(6) NOT NULL DEFAULT '0',
  `egv_EditedBy` smallint(6) NOT NULL DEFAULT '0'
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `egive_egv`
--

LOCK TABLES `egive_egv` WRITE;
/*!40000 ALTER TABLE `egive_egv` DISABLE KEYS */;
/*!40000 ALTER TABLE `egive_egv` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `email_message_pending_emp`
--

DROP TABLE IF EXISTS `email_message_pending_emp`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `email_message_pending_emp` (
  `emp_usr_id` mediumint(9) unsigned NOT NULL DEFAULT '0',
  `emp_to_send` smallint(5) unsigned NOT NULL DEFAULT '0',
  `emp_subject` varchar(128) COLLATE utf8_unicode_ci NOT NULL,
  `emp_message` text COLLATE utf8_unicode_ci NOT NULL,
  `emp_attach_name` text COLLATE utf8_unicode_ci,
  `emp_attach` tinyint(1) DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `email_message_pending_emp`
--

LOCK TABLES `email_message_pending_emp` WRITE;
/*!40000 ALTER TABLE `email_message_pending_emp` DISABLE KEYS */;
/*!40000 ALTER TABLE `email_message_pending_emp` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `email_recipient_pending_erp`
--

DROP TABLE IF EXISTS `email_recipient_pending_erp`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `email_recipient_pending_erp` (
  `erp_id` smallint(5) unsigned NOT NULL DEFAULT '0',
  `erp_usr_id` mediumint(9) unsigned NOT NULL DEFAULT '0',
  `erp_num_attempt` smallint(5) unsigned NOT NULL DEFAULT '0',
  `erp_failed_time` datetime DEFAULT NULL,
  `erp_email_address` varchar(50) COLLATE utf8_unicode_ci NOT NULL DEFAULT ''
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `email_recipient_pending_erp`
--

LOCK TABLES `email_recipient_pending_erp` WRITE;
/*!40000 ALTER TABLE `email_recipient_pending_erp` DISABLE KEYS */;
/*!40000 ALTER TABLE `email_recipient_pending_erp` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `event_attend`
--

DROP TABLE IF EXISTS `event_attend`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `event_attend` (
  `event_id` int(11) NOT NULL DEFAULT '0',
  `person_id` int(11) NOT NULL DEFAULT '0',
  `checkin_date` datetime DEFAULT NULL,
  `checkin_id` int(11) DEFAULT NULL,
  `checkout_date` datetime DEFAULT NULL,
  `checkout_id` int(11) DEFAULT NULL,
  UNIQUE KEY `event_id` (`event_id`,`person_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `event_attend`
--

LOCK TABLES `event_attend` WRITE;
/*!40000 ALTER TABLE `event_attend` DISABLE KEYS */;
/*!40000 ALTER TABLE `event_attend` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `event_types`
--

DROP TABLE IF EXISTS `event_types`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `event_types` (
  `type_id` int(11) NOT NULL AUTO_INCREMENT,
  `type_name` varchar(255) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  `type_defstarttime` time NOT NULL DEFAULT '00:00:00',
  `type_defrecurtype` enum('none','weekly','monthly','yearly') COLLATE utf8_unicode_ci NOT NULL DEFAULT 'none',
  `type_defrecurDOW` enum('Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday') COLLATE utf8_unicode_ci NOT NULL DEFAULT 'Sunday',
  `type_defrecurDOM` char(2) COLLATE utf8_unicode_ci NOT NULL DEFAULT '0',
  `type_defrecurDOY` date NOT NULL DEFAULT '0000-00-00',
  `type_active` int(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`type_id`)
) ENGINE=MyISAM AUTO_INCREMENT=3 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `event_types`
--

LOCK TABLES `event_types` WRITE;
/*!40000 ALTER TABLE `event_types` DISABLE KEYS */;
INSERT INTO `event_types` VALUES (1,'Church Service','10:30:00','weekly','Sunday','','0000-00-00',1),(2,'Sunday School','09:30:00','weekly','Sunday','','0000-00-00',1);
/*!40000 ALTER TABLE `event_types` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `eventcountnames_evctnm`
--

DROP TABLE IF EXISTS `eventcountnames_evctnm`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `eventcountnames_evctnm` (
  `evctnm_countid` int(5) NOT NULL AUTO_INCREMENT,
  `evctnm_eventtypeid` smallint(5) NOT NULL DEFAULT '0',
  `evctnm_countname` varchar(20) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  `evctnm_notes` varchar(20) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  UNIQUE KEY `evctnm_countid` (`evctnm_countid`),
  UNIQUE KEY `evctnm_eventtypeid` (`evctnm_eventtypeid`,`evctnm_countname`)
) ENGINE=MyISAM AUTO_INCREMENT=7 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `eventcountnames_evctnm`
--

LOCK TABLES `eventcountnames_evctnm` WRITE;
/*!40000 ALTER TABLE `eventcountnames_evctnm` DISABLE KEYS */;
INSERT INTO `eventcountnames_evctnm` VALUES (1,1,'Total',''),(2,1,'Members',''),(3,1,'Visitors',''),(4,2,'Total',''),(5,2,'Members',''),(6,2,'Visitors','');
/*!40000 ALTER TABLE `eventcountnames_evctnm` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `eventcounts_evtcnt`
--

DROP TABLE IF EXISTS `eventcounts_evtcnt`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `eventcounts_evtcnt` (
  `evtcnt_eventid` int(5) NOT NULL DEFAULT '0',
  `evtcnt_countid` int(5) NOT NULL DEFAULT '0',
  `evtcnt_countname` varchar(20) COLLATE utf8_unicode_ci DEFAULT NULL,
  `evtcnt_countcount` int(6) DEFAULT NULL,
  `evtcnt_notes` varchar(20) COLLATE utf8_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`evtcnt_eventid`,`evtcnt_countid`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `eventcounts_evtcnt`
--

LOCK TABLES `eventcounts_evtcnt` WRITE;
/*!40000 ALTER TABLE `eventcounts_evtcnt` DISABLE KEYS */;
/*!40000 ALTER TABLE `eventcounts_evtcnt` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `events_event`
--

DROP TABLE IF EXISTS `events_event`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `events_event` (
  `event_id` int(11) NOT NULL AUTO_INCREMENT,
  `event_type` int(11) NOT NULL DEFAULT '0',
  `event_title` varchar(255) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  `event_desc` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `event_text` text COLLATE utf8_unicode_ci,
  `event_start` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  `event_end` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  `inactive` int(1) NOT NULL DEFAULT '0',
  `event_typename` varchar(40) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  PRIMARY KEY (`event_id`),
  FULLTEXT KEY `event_txt` (`event_text`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `events_event`
--

LOCK TABLES `events_event` WRITE;
/*!40000 ALTER TABLE `events_event` DISABLE KEYS */;
/*!40000 ALTER TABLE `events_event` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `family_custom`
--

DROP TABLE IF EXISTS `family_custom`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `family_custom` (
  `fam_ID` mediumint(9) NOT NULL DEFAULT '0',
  PRIMARY KEY (`fam_ID`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `family_custom`
--

LOCK TABLES `family_custom` WRITE;
/*!40000 ALTER TABLE `family_custom` DISABLE KEYS */;
INSERT INTO `family_custom` VALUES (5),(6),(7),(8),(9),(10),(11),(12),(13),(14),(15),(16),(18),(19),(20),(21),(22);
/*!40000 ALTER TABLE `family_custom` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `family_custom_master`
--

DROP TABLE IF EXISTS `family_custom_master`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `family_custom_master` (
  `fam_custom_Order` smallint(6) NOT NULL DEFAULT '0',
  `fam_custom_Field` varchar(5) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  `fam_custom_Name` varchar(40) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  `fam_custom_Special` mediumint(8) unsigned DEFAULT NULL,
  `fam_custom_Side` enum('left','right') COLLATE utf8_unicode_ci NOT NULL DEFAULT 'left',
  `fam_custom_FieldSec` tinyint(4) NOT NULL DEFAULT '1',
  `type_ID` tinyint(4) NOT NULL DEFAULT '0'
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `family_custom_master`
--

LOCK TABLES `family_custom_master` WRITE;
/*!40000 ALTER TABLE `family_custom_master` DISABLE KEYS */;
/*!40000 ALTER TABLE `family_custom_master` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `family_fam`
--

DROP TABLE IF EXISTS `family_fam`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `family_fam` (
  `fam_ID` mediumint(9) unsigned NOT NULL AUTO_INCREMENT,
  `fam_Name` varchar(50) COLLATE utf8_unicode_ci DEFAULT NULL,
  `fam_Address1` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `fam_Address2` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `fam_City` varchar(50) COLLATE utf8_unicode_ci DEFAULT NULL,
  `fam_State` varchar(50) COLLATE utf8_unicode_ci DEFAULT NULL,
  `fam_Zip` varchar(50) COLLATE utf8_unicode_ci DEFAULT NULL,
  `fam_Country` varchar(50) COLLATE utf8_unicode_ci DEFAULT NULL,
  `fam_HomePhone` varchar(30) COLLATE utf8_unicode_ci DEFAULT NULL,
  `fam_WorkPhone` varchar(30) COLLATE utf8_unicode_ci DEFAULT NULL,
  `fam_CellPhone` varchar(30) COLLATE utf8_unicode_ci DEFAULT NULL,
  `fam_Email` varchar(100) COLLATE utf8_unicode_ci DEFAULT NULL,
  `fam_WeddingDate` date DEFAULT NULL,
  `fam_DateEntered` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  `fam_DateLastEdited` datetime DEFAULT NULL,
  `fam_EnteredBy` smallint(5) unsigned NOT NULL DEFAULT '0',
  `fam_EditedBy` smallint(5) unsigned DEFAULT '0',
  `fam_scanCheck` text COLLATE utf8_unicode_ci,
  `fam_scanCredit` text COLLATE utf8_unicode_ci,
  `fam_SendNewsLetter` enum('FALSE','TRUE') COLLATE utf8_unicode_ci NOT NULL DEFAULT 'FALSE',
  `fam_DateDeactivated` date DEFAULT NULL,
  `fam_OkToCanvass` enum('FALSE','TRUE') COLLATE utf8_unicode_ci NOT NULL DEFAULT 'FALSE',
  `fam_Canvasser` smallint(5) unsigned NOT NULL DEFAULT '0',
  `fam_Latitude` double DEFAULT NULL,
  `fam_Longitude` double DEFAULT NULL,
  `fam_Envelope` mediumint(9) NOT NULL DEFAULT '0',
  PRIMARY KEY (`fam_ID`),
  KEY `fam_ID` (`fam_ID`)
) ENGINE=MyISAM AUTO_INCREMENT=25 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `family_fam`
--

LOCK TABLES `family_fam` WRITE;
/*!40000 ALTER TABLE `family_fam` DISABLE KEYS */;
INSERT INTO `family_fam` VALUES (1,'Klotz','573 6th Street West','','Lynnwood','WA','98037','United States','','','','','2014-12-28','2015-11-21 21:04:50','2015-12-02 21:55:34',1,2,NULL,NULL,'TRUE',NULL,'FALSE',0,47.8460444,-122.2412826,0),(2,'Proberte','399 Walnut Street','Apt 3','Rahway','NJ','07065','United States','','','','','2013-08-10','2015-11-21 21:06:08','2015-12-02 21:57:30',1,2,NULL,NULL,'FALSE',NULL,'FALSE',0,40.643527,-74.304713,0),(3,'Greenwood','387 Franklin Court','','Aiken','PA','29803','United States','','','','','2015-07-23','2015-11-22 09:05:07','2015-12-02 21:49:22',2,2,NULL,NULL,'FALSE',NULL,'FALSE',0,40.852004,-80.285337,0),(4,'Bates','9678 Wall Street','','Orange','NJ','07050','United States','','','','',NULL,'2015-11-22 09:08:54','2015-12-02 21:58:54',2,2,NULL,NULL,'FALSE',NULL,'FALSE',0,40.8053649,-73.9950107,0),(5,'Burdzy','323 Grove Avenue','','Ashburn','VA','20147','United States','','','','',NULL,'2015-11-22 09:09:52','2015-12-02 21:59:02',2,2,NULL,NULL,'TRUE',NULL,'TRUE',0,39.0660032,-77.447552,0),(6,'Gerig','477 Spruce Street','','Orlando','FL','32806','United States','','','','','01-25','2015-11-22 09:14:19','2015-12-02 21:51:17',2,2,NULL,NULL,'TRUE',NULL,'TRUE',0,28.5758169,-81.3729482,0),(7,'Mendoza','814 Winding Way','','Tonawanda','NY','14150','United States','','','','','2015-12-03','2015-11-22 09:18:06','2015-12-02 21:52:09',2,2,NULL,NULL,'TRUE',NULL,'TRUE',0,43.0203347,-78.880315,0),(8,'Black','9757 Route 20','','Lilburn','GA','30047','United States','','','','',NULL,'2015-11-22 09:25:26','2015-12-02 21:58:58',2,2,NULL,NULL,'TRUE',NULL,'TRUE',0,33.8901036,-84.1429719,0),(9,'Custodio','757 Hartford Road','','Horn Lake','MN','38637','United States','','','','',NULL,'2015-11-22 09:36:03','2015-12-02 21:48:36',2,2,NULL,NULL,'TRUE',NULL,'TRUE',0,34.9616778,-90.0466559,0),(10,'Destavola','710 6th Street','','Westlake','OH','44145','United States','','','','someone@somewhere.com',NULL,'2015-11-22 09:43:21','2015-12-02 21:59:08',2,2,NULL,NULL,'FALSE',NULL,'TRUE',0,40.1315144,-82.918719,0),(11,'Dibb','130 Cleveland Avenue','','Jonesboro','GA','30236','United States','','','','','2015-06-02','2015-11-22 10:00:46','2015-12-02 21:54:03',2,2,NULL,NULL,'TRUE',NULL,'TRUE',0,40.4111678,-85.5699528,0),(12,'Scogin Jr','730 Cooper Street','','Bartlett','IL','60103','United States','','','','','2015-06-24','2015-11-22 12:23:18','2015-12-02 21:57:49',2,2,NULL,NULL,'TRUE',NULL,'TRUE',0,41.9710354,-88.1443961,0),(13,'Bhiladvala','636 Hudson Street','','Mooresville','NC','28115','United States','','','','',NULL,'2015-11-22 12:25:57','2015-12-02 21:52:46',2,2,NULL,NULL,'TRUE',NULL,'TRUE',0,35.5848596,-80.8100724,0),(14,'Loughman','694 River Street','','Brainerd','MN','56401','United States','','','','',NULL,'2015-11-22 12:28:41','2015-12-02 21:56:00',2,2,NULL,NULL,'TRUE',NULL,'TRUE',0,46.3504616,-94.2080589,0),(15,'Cavitch','3072 Woodland Avenue','','Fayetteville','NC','28303','United States','','','','',NULL,'2015-11-22 16:26:34','2015-12-02 21:48:01',2,2,NULL,NULL,'TRUE',NULL,'TRUE',0,35.0651967,-78.9023274,0),(16,'Scogin Sr','975 Mulberry Lane','','South El Monte','CA','91733','United States','','','','','2015-08-14','2015-11-22 16:28:33','2015-12-02 21:58:30',2,2,NULL,NULL,'TRUE',NULL,'TRUE',0,39.7424584,-121.800455,0),(17,'Hitchcock','610 Ridge Road','','Jacksonville Beach','FL','32250','United States','','','','',NULL,'2015-11-22 17:01:38','2015-12-02 21:55:05',2,2,NULL,NULL,'FALSE',NULL,'FALSE',0,30.3155095,-81.4762603,0),(18,'Probert','251 Broadway','','Nicholasville','KY','40356','United States','','','','','2015-09-27','2015-11-22 17:02:52','2015-12-02 21:56:24',2,2,NULL,NULL,'TRUE',NULL,'TRUE',0,37.8783131,-84.5760528,0),(19,'Dibb - Dan','710 6th Street','','Westlake','OH','44145','United States','','','','','2015-07-20','2015-11-22 17:06:27','2015-12-02 21:54:41',2,2,NULL,NULL,'TRUE',NULL,'TRUE',0,40.1315144,-82.918719,0),(20,'Lamas','','','','','','United States','','','','',NULL,'2015-11-22 17:09:54',NULL,2,0,NULL,NULL,'TRUE',NULL,'TRUE',0,NULL,NULL,0),(21,'Black','','','','','','United States','','','','',NULL,'2015-11-22 17:58:01',NULL,2,0,NULL,NULL,'TRUE',NULL,'TRUE',0,NULL,NULL,0),(22,'Tagiuri','951 Briarwood Drive','','new jj','NJ','07006','United States','','','','','2015-07-31','2015-11-22 18:59:05','2015-12-02 21:56:51',2,2,NULL,NULL,'TRUE',NULL,'TRUE',0,40.8515243,-74.2824862,0),(23,'Gerig','892 Madison Avenue','','Ringgold','GA','30736','United States','','','','',NULL,'2015-11-29 13:01:52','2015-12-02 21:51:37',2,2,NULL,NULL,'FALSE',NULL,'FALSE',0,34.9159099,-85.1091173,0),(24,'Tagiuri','','','','','','United States','','','5078675309','someone@somewhere.com',NULL,'2015-11-29 13:20:16','2015-11-29 13:20:54',2,2,NULL,NULL,'FALSE',NULL,'FALSE',0,NULL,NULL,0);
/*!40000 ALTER TABLE `family_fam` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `fundraiser_fr`
--

DROP TABLE IF EXISTS `fundraiser_fr`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `fundraiser_fr` (
  `fr_ID` mediumint(9) unsigned NOT NULL AUTO_INCREMENT,
  `fr_date` date DEFAULT NULL,
  `fr_title` varchar(128) COLLATE utf8_unicode_ci NOT NULL,
  `fr_description` text COLLATE utf8_unicode_ci,
  `fr_EnteredBy` smallint(5) unsigned NOT NULL DEFAULT '0',
  `fr_EnteredDate` date NOT NULL,
  PRIMARY KEY (`fr_ID`),
  UNIQUE KEY `fr_ID` (`fr_ID`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `fundraiser_fr`
--

LOCK TABLES `fundraiser_fr` WRITE;
/*!40000 ALTER TABLE `fundraiser_fr` DISABLE KEYS */;
/*!40000 ALTER TABLE `fundraiser_fr` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `group_grp`
--

DROP TABLE IF EXISTS `group_grp`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `group_grp` (
  `grp_ID` mediumint(8) unsigned NOT NULL AUTO_INCREMENT,
  `grp_Type` tinyint(4) NOT NULL DEFAULT '0',
  `grp_RoleListID` mediumint(8) unsigned NOT NULL DEFAULT '0',
  `grp_DefaultRole` mediumint(9) NOT NULL DEFAULT '0',
  `grp_Name` varchar(50) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  `grp_Description` text COLLATE utf8_unicode_ci,
  `grp_hasSpecialProps` enum('true','false') COLLATE utf8_unicode_ci NOT NULL DEFAULT 'false',
  PRIMARY KEY (`grp_ID`),
  UNIQUE KEY `grp_ID` (`grp_ID`),
  KEY `grp_ID_2` (`grp_ID`)
) ENGINE=MyISAM AUTO_INCREMENT=5 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `group_grp`
--

LOCK TABLES `group_grp` WRITE;
/*!40000 ALTER TABLE `group_grp` DISABLE KEYS */;
INSERT INTO `group_grp` VALUES (2,4,13,1,'Toddlers','','true'),(3,4,14,1,'Infants','','false'),(4,4,15,1,'Test','','false');
/*!40000 ALTER TABLE `group_grp` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `groupprop_2`
--

DROP TABLE IF EXISTS `groupprop_2`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `groupprop_2` (
  `per_ID` mediumint(8) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`per_ID`),
  UNIQUE KEY `per_ID` (`per_ID`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `groupprop_2`
--

LOCK TABLES `groupprop_2` WRITE;
/*!40000 ALTER TABLE `groupprop_2` DISABLE KEYS */;
INSERT INTO `groupprop_2` VALUES (3),(8),(18),(25),(26),(43),(44),(49),(51),(52),(54);
/*!40000 ALTER TABLE `groupprop_2` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `groupprop_master`
--

DROP TABLE IF EXISTS `groupprop_master`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `groupprop_master` (
  `grp_ID` mediumint(9) unsigned NOT NULL DEFAULT '0',
  `prop_ID` tinyint(3) unsigned NOT NULL DEFAULT '0',
  `prop_Field` varchar(5) COLLATE utf8_unicode_ci NOT NULL DEFAULT '0',
  `prop_Name` varchar(40) COLLATE utf8_unicode_ci DEFAULT NULL,
  `prop_Description` varchar(60) COLLATE utf8_unicode_ci DEFAULT NULL,
  `type_ID` smallint(5) unsigned NOT NULL DEFAULT '0',
  `prop_Special` mediumint(9) unsigned DEFAULT NULL,
  `prop_PersonDisplay` enum('false','true') COLLATE utf8_unicode_ci NOT NULL DEFAULT 'false'
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci COMMENT='Group-specific properties order, name, description, type';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `groupprop_master`
--

LOCK TABLES `groupprop_master` WRITE;
/*!40000 ALTER TABLE `groupprop_master` DISABLE KEYS */;
/*!40000 ALTER TABLE `groupprop_master` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `istlookup_lu`
--

DROP TABLE IF EXISTS `istlookup_lu`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `istlookup_lu` (
  `lu_fam_ID` mediumint(9) NOT NULL DEFAULT '0',
  `lu_LookupDateTime` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  `lu_DeliveryLine1` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `lu_DeliveryLine2` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `lu_City` varchar(50) COLLATE utf8_unicode_ci DEFAULT NULL,
  `lu_State` varchar(50) COLLATE utf8_unicode_ci DEFAULT NULL,
  `lu_ZipAddon` varchar(50) COLLATE utf8_unicode_ci DEFAULT NULL,
  `lu_Zip` varchar(10) COLLATE utf8_unicode_ci DEFAULT NULL,
  `lu_Addon` varchar(10) COLLATE utf8_unicode_ci DEFAULT NULL,
  `lu_LOTNumber` varchar(10) COLLATE utf8_unicode_ci DEFAULT NULL,
  `lu_DPCCheckdigit` varchar(10) COLLATE utf8_unicode_ci DEFAULT NULL,
  `lu_RecordType` varchar(10) COLLATE utf8_unicode_ci DEFAULT NULL,
  `lu_LastLine` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `lu_CarrierRoute` varchar(10) COLLATE utf8_unicode_ci DEFAULT NULL,
  `lu_ReturnCodes` varchar(10) COLLATE utf8_unicode_ci DEFAULT NULL,
  `lu_ErrorCodes` varchar(10) COLLATE utf8_unicode_ci DEFAULT NULL,
  `lu_ErrorDesc` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`lu_fam_ID`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci COMMENT='US Address Verification Lookups From Intelligent Search Tech';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `istlookup_lu`
--

LOCK TABLES `istlookup_lu` WRITE;
/*!40000 ALTER TABLE `istlookup_lu` DISABLE KEYS */;
/*!40000 ALTER TABLE `istlookup_lu` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `list_lst`
--

DROP TABLE IF EXISTS `list_lst`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `list_lst` (
  `lst_ID` mediumint(8) unsigned NOT NULL DEFAULT '0',
  `lst_OptionID` mediumint(8) unsigned NOT NULL DEFAULT '0',
  `lst_OptionSequence` tinyint(3) unsigned NOT NULL DEFAULT '0',
  `lst_OptionName` varchar(50) COLLATE utf8_unicode_ci NOT NULL DEFAULT ''
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `list_lst`
--

LOCK TABLES `list_lst` WRITE;
/*!40000 ALTER TABLE `list_lst` DISABLE KEYS */;
INSERT INTO `list_lst` VALUES (1,1,1,'Member'),(1,2,2,'Regular Attender'),(1,3,3,'Guest'),(1,5,4,'Non-Attender'),(1,4,5,'Non-Attender (staff)'),(2,1,1,'Head of Household'),(2,2,2,'Spouse'),(2,3,3,'Child'),(2,4,4,'Other Relative'),(2,5,5,'Non Relative'),(3,1,1,'Ministry'),(3,2,2,'Team'),(3,3,3,'Bible Study'),(3,4,4,'Sunday School Class'),(4,1,1,'True / False'),(4,2,2,'Date'),(4,3,3,'Text Field (50 char)'),(4,4,4,'Text Field (100 char)'),(4,5,5,'Text Field (Long)'),(4,6,6,'Year'),(4,7,7,'Season'),(4,8,8,'Number'),(4,9,9,'Person from Group'),(4,10,10,'Money'),(4,11,11,'Phone Number'),(4,12,12,'Custom Drop-Down List'),(5,1,1,'bAll'),(5,2,2,'bAdmin'),(5,3,3,'bAddRecords'),(5,4,4,'bEditRecords'),(5,5,5,'bDeleteRecords'),(5,6,6,'bMenuOptions'),(5,7,7,'bManageGroups'),(5,8,8,'bFinance'),(5,9,9,'bNotes'),(5,10,10,'bCommunication'),(5,11,11,'bCanvasser'),(10,1,1,'Teacher'),(10,2,2,'Student'),(11,1,1,'Member'),(12,1,1,'Teacher'),(12,2,2,'Student'),(13,2,2,'Student'),(13,1,1,'Teacher'),(14,1,1,'Teacher'),(14,2,2,'Student'),(15,1,1,'Teacher'),(15,2,2,'Student');
/*!40000 ALTER TABLE `list_lst` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `menuconfig_mcf`
--

DROP TABLE IF EXISTS `menuconfig_mcf`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `menuconfig_mcf` (
  `mid` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(20) COLLATE utf8_unicode_ci NOT NULL,
  `parent` varchar(20) COLLATE utf8_unicode_ci NOT NULL,
  `ismenu` tinyint(1) NOT NULL,
  `content_english` varchar(100) COLLATE utf8_unicode_ci NOT NULL,
  `content` varchar(100) COLLATE utf8_unicode_ci DEFAULT NULL,
  `uri` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `statustext` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `security_grp` varchar(50) COLLATE utf8_unicode_ci NOT NULL,
  `session_var` varchar(50) COLLATE utf8_unicode_ci DEFAULT NULL,
  `session_var_in_text` tinyint(1) NOT NULL,
  `session_var_in_uri` tinyint(1) NOT NULL,
  `url_parm_name` varchar(50) COLLATE utf8_unicode_ci DEFAULT NULL,
  `active` tinyint(1) NOT NULL,
  `sortorder` tinyint(3) NOT NULL,
  `icon` varchar(50) COLLATE utf8_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`mid`)
) ENGINE=MyISAM AUTO_INCREMENT=102 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `menuconfig_mcf`
--

LOCK TABLES `menuconfig_mcf` WRITE;
/*!40000 ALTER TABLE `menuconfig_mcf` DISABLE KEYS */;
INSERT INTO `menuconfig_mcf` VALUES (1,'root','',1,'Main','Main','','','bAll',NULL,0,0,NULL,1,0,NULL),(101,'sundayschool-dash','sundayschool',0,'Dashboard','Dashboard','Reports/SundaySchoolClassList.php','','bAll',NULL,0,0,NULL,1,2,NULL),(100,'sundayschool','root',1,'Sunday School','Sunday School','','','bAll',NULL,0,0,NULL,1,4,'fa-stack-overflow'),(7,'editusers','admin',0,'Edit Users','Edit Users','UserList.php','','bAdmin',NULL,0,0,NULL,1,1,NULL),(8,'addnewuser','admin',0,'Add New User','Add New User','UserEditor.php','','bAdmin',NULL,0,0,NULL,1,2,NULL),(9,'custompersonfld','admin',0,'Edit Custom Person Fields','Edit Custom Person Fields','PersonCustomFieldsEditor.php','','bAdmin',NULL,0,0,NULL,1,3,NULL),(10,'donationfund','admin',0,'Edit Donation Funds','Edit Donation Funds','DonationFundEditor.php','','bAdmin',NULL,0,0,NULL,1,4,NULL),(11,'dbbackup','admin',0,'Backup Database','Backup Database','BackupDatabase.php','','bAdmin',NULL,0,0,NULL,1,5,NULL),(12,'cvsimport','admin',0,'CSV Import','CSV Import','CSVImport.php','','bAdmin',NULL,0,0,NULL,1,6,NULL),(13,'accessreport','admin',0,'Access report','Access report','AccessReport.php','','bAdmin',NULL,0,0,NULL,1,7,NULL),(14,'generalsetting','admin',0,'Edit General Settings','Edit General Settings','SettingsGeneral.php','','bAdmin',NULL,0,0,NULL,1,8,NULL),(15,'reportsetting','admin',0,'Edit Report Settings','Edit Report Settings','SettingsReport.php','','bAdmin',NULL,0,0,NULL,1,9,NULL),(16,'userdefault','admin',0,'Edit User Default Settings','Edit User Default Settings','SettingsUser.php','','bAdmin',NULL,0,0,NULL,1,10,NULL),(17,'envelopmgr','admin',0,'Envelope Manager','Envelope Manager','ManageEnvelopes.php','','bAdmin',NULL,0,0,NULL,1,11,NULL),(18,'register','admin',0,'Please select this option to register ChurchInfo after configuring.','Please select this option to register ChurchInfo after configuring.','Register.php','','bAdmin',NULL,0,0,NULL,1,12,NULL),(19,'people','root',1,'Members','Members','','Members','bAll',NULL,0,0,NULL,1,3,'fa-users'),(20,'newperson','people',0,'Add New Person','Add New Person','PersonEditor.php','','bAddRecords',NULL,0,0,NULL,1,1,NULL),(21,'viewperson','people',0,'View All Persons','View All Persons','SelectList.php?mode=person','','bAll',NULL,0,0,NULL,1,2,NULL),(22,'classes','people',0,'Classification Manager','Classification Manager','OptionManager.php?mode=classes','','bMenuOptions',NULL,0,0,NULL,1,3,NULL),(24,'volunteeropportunity','people',0,'Edit volunteer opportunities','Edit volunteer opportunities','VolunteerOpportunityEditor.php','','bAll',NULL,0,0,NULL,1,5,NULL),(26,'newfamily','people',0,'Add New Family','Add New Family','FamilyEditor.php','','bAddRecords',NULL,0,0,NULL,1,7,NULL),(27,'viewfamily','people',0,'View All Families','View All Families','FamilyList.php','','bAll',NULL,0,0,NULL,1,8,NULL),(28,'familygeotools','people',0,'Family Geographic Utilties','Family Geographic Utilties','GeoPage.php','','bAll',NULL,0,0,NULL,1,9,NULL),(29,'familymap','people',0,'Family Map','Family Map','MapUsingGoogle.php?GroupID=-1','','bAll',NULL,0,0,NULL,1,10,NULL),(30,'rolemanager','people',0,'Family Roles Manager','Family Roles Manager','OptionManager.php?mode=famroles','','bMenuOptions',NULL,0,0,NULL,1,11,NULL),(31,'events','root',1,'Events','Events','','Events','bAll',NULL,0,0,NULL,1,9,'fa-ticket'),(32,'listevent','events',0,'List Church Events','List Church Events','ListEvents.php','List Church Events','bAll',NULL,0,0,NULL,1,1,NULL),(33,'addevent','events',0,'Add Church Event','Add Church Event','EventNames.php','Add Church Event','bAll',NULL,0,0,NULL,1,2,NULL),(34,'eventype','events',0,'List Event Types','List Event Types','EventNames.php','','bAdmin',NULL,0,0,NULL,1,3,NULL),(83,'eventcheckin','events',0,'Check-in and Check-out','Check-in and Check-out','Checkin.php','','bAll',NULL,0,0,NULL,1,4,NULL),(35,'deposit','root',1,'Deposit','Deposit','','','bFinance',NULL,0,0,NULL,1,10,'fa-bank'),(36,'newdeposit','deposit',0,'Create New Deposit','Create New Deposit','DepositSlipEditor.php?DepositType=Bank','','bFinance',NULL,0,0,NULL,1,1,NULL),(37,'viewdeposit','deposit',0,'View All Deposits','View All Deposits','FindDepositSlip.php','','bFinance',NULL,0,0,NULL,1,2,NULL),(38,'depositreport','deposit',0,'Deposit Reports','Deposit Reports','FinancialReports.php','','bFinance',NULL,0,0,NULL,1,3,NULL),(40,'depositslip','deposit',0,'Edit Deposit Slip','Edit Deposit Slip','DepositSlipEditor.php','','bFinance','iCurrentDeposit',1,1,'DepositSlipID',1,5,NULL),(84,'fundraiser','root',1,'Fundraiser','Fundraiser','','','bAll',NULL,0,0,NULL,1,11,'fa-money'),(85,'newfundraiser','fundraiser',0,'Create New Fundraiser','Create New Fundraiser','FundRaiserEditor.php?FundRaiserID=-1','','bAll',NULL,0,0,NULL,1,1,NULL),(86,'viewfundraiser','fundraiser',0,'View All Fundraisers','View All Fundraisers','FindFundRaiser.php','','bAll',NULL,0,0,NULL,1,1,NULL),(87,'editfundraiser','fundraiser',0,'Edit Fundraiser','Edit Fundraiser','FundRaiserEditor.php','','bAll','iCurrentFundraiser',1,1,'FundRaiserID',1,5,NULL),(88,'viewbuyers','fundraiser',0,'View Buyers','View Buyers','PaddleNumList.php','','bAll','iCurrentFundraiser',1,1,'FundRaiserID',1,5,NULL),(89,'adddonors','fundraiser',0,'Add Donors to Buyer List','Add Donors to Buyer List','AddDonors.php','','bAll','iCurrentFundraiser',1,1,'FundRaiserID',1,5,NULL),(41,'cart','root',1,'Cart','Cart','','','bAll',NULL,0,0,NULL,1,6,'fa-shopping-cart'),(42,'viewcart','cart',0,'List Cart Items','List Cart Items','CartView.php','','bAll',NULL,0,0,NULL,1,1,NULL),(43,'emptycart','cart',0,'Empty Cart','Empty Cart','CartView.php?Action=EmptyCart','','bAll',NULL,0,0,NULL,1,2,NULL),(44,'carttogroup','cart',0,'Empty Cart to Group','Empty Cart to Group','CartToGroup.php','','bManageGroups',NULL,0,0,NULL,1,3,NULL),(45,'carttofamily','cart',0,'Empty Cart to Family','Empty Cart to Family','CartToFamily.php','','bAddRecords',NULL,0,0,NULL,1,4,NULL),(46,'carttoevent','cart',0,'Empty Cart to Event','Empty Cart to Event','CartToEvent.php','Empty Cart contents to Event','bAll',NULL,0,0,NULL,1,5,NULL),(47,'report','root',1,'Data/Reports','Data/Reports','','','bAll',NULL,0,0,NULL,1,8,'fa-file-pdf-o'),(48,'cvsexport','report',0,'CSV Export Records','CSV Export Records','CSVExport.php','','bAll',NULL,0,0,NULL,1,1,NULL),(49,'querymenu','report',0,'Query Menu','Query Menu','QueryList.php','','bAll',NULL,0,0,NULL,1,2,NULL),(50,'reportmenu','report',0,'Reports Menu','Reports Menu','ReportList.php','','bAll',NULL,0,0,NULL,1,3,NULL),(51,'groups','root',1,'Groups','Groups','','','bAll',NULL,0,0,NULL,1,7,'fa-tag'),(52,'listgroups','groups',0,'List Groups','List Groups','GroupList.php','','bAll',NULL,0,0,NULL,1,1,NULL),(53,'newgroup','groups',0,'Add a New Group','Add a New Group','GroupEditor.php','','bManageGroups',NULL,0,0,NULL,1,2,NULL),(54,'editgroup','groups',0,'Edit Group Types','Edit Group Types','OptionManager.php?mode=grptypes','','bMenuOptions',NULL,0,0,NULL,1,3,NULL),(55,'assigngroup','group',0,'Group Assignment Helper','Group Assignment Helper','SelectList.php?mode=groupassign','','bAll',NULL,0,0,NULL,1,4,NULL),(56,'properties','root',1,'Properties','Properties','','','bAll',NULL,0,0,NULL,1,12,'fa-cogs'),(57,'peopleproperty','properties',0,'People Properties','People Properties','PropertyList.php?Type=p','','bAll',NULL,0,0,NULL,1,1,NULL),(58,'familyproperty','properties',0,'Family Properties','Family Properties','PropertyList.php?Type=f','','bAll',NULL,0,0,NULL,1,2,NULL),(59,'groupproperty','properties',0,'Group Properties','Group Properties','PropertyList.php?Type=g','','bAll',NULL,0,0,NULL,1,3,NULL),(60,'propertytype','properties',0,'Property Types','Property Types','PropertyTypeList.php','','bAll',NULL,0,0,NULL,1,4,NULL),(65,'about','help',0,'About ChurchInfo','About ChurchInfo','Help.php?page=About','','bAll',NULL,0,0,NULL,1,1,NULL),(66,'wiki','help',0,'Wiki Documentation','Wiki Documentation','Help.php?page=Wiki','','bAll',NULL,0,0,NULL,1,2,NULL),(67,'helppeople','help',0,'People','People','Help.php?page=People','','bAll',NULL,0,0,NULL,1,3,NULL),(68,'helpfamily','help',0,'Families','Families','Help.php?page=Family','','bAll',NULL,0,0,NULL,1,4,NULL),(69,'helpgeofeature','help',0,'Geographic features','Geographic features','Help.php?page=Geographic','','bAll',NULL,0,0,NULL,1,5,NULL),(70,'helpgroups','help',0,'Groups','Groups','Help.php?page=Groups','','bAll',NULL,0,0,NULL,1,6,NULL),(71,'helpfinance','help',0,'Finances','Finances','Help.php?page=Finances','','bAll',NULL,0,0,NULL,1,7,NULL),(90,'helpfundraiser','help',0,'Fundraiser','Fundraiser','Help.php?page=Fundraiser','','bAll',NULL,0,0,NULL,1,8,NULL),(72,'helpreports','help',0,'Reports','Reports','Help.php?page=Reports','','bAll',NULL,0,0,NULL,1,9,NULL),(73,'helpadmin','help',0,'Administration','Administration','Help.php?page=Admin','','bAll',NULL,0,0,NULL,1,10,NULL),(74,'helpcart','help',0,'Cart','Cart','Help.php?page=Cart','','bAll',NULL,0,0,NULL,1,11,NULL),(75,'helpproperty','help',0,'Properties','Properties','Help.php?page=Properties','','bAll',NULL,0,0,NULL,1,12,NULL),(76,'helpnotes','help',0,'Notes','Notes','Help.php?page=Notes','','bAll',NULL,0,0,NULL,1,13,NULL),(77,'helpcustomfields','help',0,'Custom Fields','Custom Fields','Help.php?page=Custom','','bAll',NULL,0,0,NULL,1,14,NULL),(78,'helpclassification','help',0,'Classifications','Classifications','Help.php?page=Class','','bAll',NULL,0,0,NULL,1,15,NULL),(79,'helpcanvass','help',0,'Canvass Support','Canvass Support','Help.php?page=Canvass','','bAll',NULL,0,0,NULL,1,16,NULL),(80,'helpevents','help',0,'Events','Events','Help.php?page=Events','','bAll',NULL,0,0,NULL,1,17,NULL),(81,'menusetup','admin',0,'Menu Options','Menu Options','MenuSetup.php','','bAdmin',NULL,0,0,NULL,1,13,NULL),(82,'customfamilyfld','admin',0,'Edit Custom Family Fields','Edit Custom Family Fields','FamilyCustomFieldsEditor.php','','bAdmin',NULL,0,0,NULL,1,3,NULL);
/*!40000 ALTER TABLE `menuconfig_mcf` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `multibuy_mb`
--

DROP TABLE IF EXISTS `multibuy_mb`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `multibuy_mb` (
  `mb_ID` mediumint(9) unsigned NOT NULL AUTO_INCREMENT,
  `mb_per_ID` mediumint(9) NOT NULL DEFAULT '0',
  `mb_item_ID` mediumint(9) NOT NULL DEFAULT '0',
  `mb_count` decimal(8,0) DEFAULT NULL,
  PRIMARY KEY (`mb_ID`),
  UNIQUE KEY `mb_ID` (`mb_ID`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `multibuy_mb`
--

LOCK TABLES `multibuy_mb` WRITE;
/*!40000 ALTER TABLE `multibuy_mb` DISABLE KEYS */;
/*!40000 ALTER TABLE `multibuy_mb` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `note_nte`
--

DROP TABLE IF EXISTS `note_nte`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `note_nte` (
  `nte_ID` mediumint(8) unsigned NOT NULL AUTO_INCREMENT,
  `nte_per_ID` mediumint(8) unsigned NOT NULL DEFAULT '0',
  `nte_fam_ID` mediumint(8) unsigned NOT NULL DEFAULT '0',
  `nte_Private` mediumint(8) unsigned NOT NULL DEFAULT '0',
  `nte_Text` text COLLATE utf8_unicode_ci,
  `nte_DateEntered` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  `nte_DateLastEdited` datetime DEFAULT NULL,
  `nte_EnteredBy` mediumint(8) unsigned NOT NULL DEFAULT '0',
  `nte_EditedBy` mediumint(8) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`nte_ID`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `note_nte`
--

LOCK TABLES `note_nte` WRITE;
/*!40000 ALTER TABLE `note_nte` DISABLE KEYS */;
/*!40000 ALTER TABLE `note_nte` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `paddlenum_pn`
--

DROP TABLE IF EXISTS `paddlenum_pn`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `paddlenum_pn` (
  `pn_ID` mediumint(9) unsigned NOT NULL AUTO_INCREMENT,
  `pn_fr_ID` mediumint(9) unsigned DEFAULT NULL,
  `pn_Num` mediumint(9) unsigned DEFAULT NULL,
  `pn_per_ID` mediumint(9) NOT NULL DEFAULT '0',
  PRIMARY KEY (`pn_ID`),
  UNIQUE KEY `pn_ID` (`pn_ID`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `paddlenum_pn`
--

LOCK TABLES `paddlenum_pn` WRITE;
/*!40000 ALTER TABLE `paddlenum_pn` DISABLE KEYS */;
/*!40000 ALTER TABLE `paddlenum_pn` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `person2group2role_p2g2r`
--

DROP TABLE IF EXISTS `person2group2role_p2g2r`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `person2group2role_p2g2r` (
  `p2g2r_per_ID` mediumint(8) unsigned NOT NULL DEFAULT '0',
  `p2g2r_grp_ID` mediumint(8) unsigned NOT NULL DEFAULT '0',
  `p2g2r_rle_ID` mediumint(8) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`p2g2r_per_ID`,`p2g2r_grp_ID`),
  KEY `p2g2r_per_ID` (`p2g2r_per_ID`,`p2g2r_grp_ID`,`p2g2r_rle_ID`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `person2group2role_p2g2r`
--

LOCK TABLES `person2group2role_p2g2r` WRITE;
/*!40000 ALTER TABLE `person2group2role_p2g2r` DISABLE KEYS */;
INSERT INTO `person2group2role_p2g2r` VALUES (3,2,1),(8,2,2),(18,2,2),(25,2,2),(26,2,2),(43,2,2),(44,2,2),(49,2,2),(51,2,2),(52,2,2),(54,2,2),(55,3,2),(59,3,2);
/*!40000 ALTER TABLE `person2group2role_p2g2r` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `person2volunteeropp_p2vo`
--

DROP TABLE IF EXISTS `person2volunteeropp_p2vo`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `person2volunteeropp_p2vo` (
  `p2vo_ID` mediumint(9) NOT NULL AUTO_INCREMENT,
  `p2vo_per_ID` mediumint(9) DEFAULT NULL,
  `p2vo_vol_ID` mediumint(9) DEFAULT NULL,
  PRIMARY KEY (`p2vo_ID`),
  UNIQUE KEY `p2vo_ID` (`p2vo_ID`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `person2volunteeropp_p2vo`
--

LOCK TABLES `person2volunteeropp_p2vo` WRITE;
/*!40000 ALTER TABLE `person2volunteeropp_p2vo` DISABLE KEYS */;
/*!40000 ALTER TABLE `person2volunteeropp_p2vo` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `person_custom`
--

DROP TABLE IF EXISTS `person_custom`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `person_custom` (
  `per_ID` mediumint(9) NOT NULL DEFAULT '0',
  PRIMARY KEY (`per_ID`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `person_custom`
--

LOCK TABLES `person_custom` WRITE;
/*!40000 ALTER TABLE `person_custom` DISABLE KEYS */;
INSERT INTO `person_custom` VALUES (2),(3),(4),(5),(6),(7),(8),(9),(10),(11),(12),(13),(14),(15),(16),(17),(18),(19),(20),(21),(22),(23),(24),(25),(26),(27),(28),(29),(30),(31),(32),(33),(34),(35),(36),(37),(38),(39),(40),(41),(42),(43),(44),(45),(46),(47),(48),(49),(50),(51),(52),(53),(54),(55),(56),(57),(58),(59),(60),(61),(62),(63),(64),(65),(66),(67),(68);
/*!40000 ALTER TABLE `person_custom` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `person_custom_master`
--

DROP TABLE IF EXISTS `person_custom_master`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `person_custom_master` (
  `custom_Order` smallint(6) NOT NULL DEFAULT '0',
  `custom_Field` varchar(5) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  `custom_Name` varchar(40) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  `custom_Special` mediumint(8) unsigned DEFAULT NULL,
  `custom_Side` enum('left','right') COLLATE utf8_unicode_ci NOT NULL DEFAULT 'left',
  `custom_FieldSec` tinyint(4) NOT NULL,
  `type_ID` tinyint(4) NOT NULL DEFAULT '0'
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `person_custom_master`
--

LOCK TABLES `person_custom_master` WRITE;
/*!40000 ALTER TABLE `person_custom_master` DISABLE KEYS */;
/*!40000 ALTER TABLE `person_custom_master` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `person_per`
--

DROP TABLE IF EXISTS `person_per`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `person_per` (
  `per_ID` mediumint(9) unsigned NOT NULL AUTO_INCREMENT,
  `per_Title` varchar(50) COLLATE utf8_unicode_ci DEFAULT NULL,
  `per_FirstName` varchar(50) COLLATE utf8_unicode_ci DEFAULT NULL,
  `per_MiddleName` varchar(50) COLLATE utf8_unicode_ci DEFAULT NULL,
  `per_LastName` varchar(50) COLLATE utf8_unicode_ci DEFAULT NULL,
  `per_Suffix` varchar(50) COLLATE utf8_unicode_ci DEFAULT NULL,
  `per_Address1` varchar(50) COLLATE utf8_unicode_ci DEFAULT NULL,
  `per_Address2` varchar(50) COLLATE utf8_unicode_ci DEFAULT NULL,
  `per_City` varchar(50) COLLATE utf8_unicode_ci DEFAULT NULL,
  `per_State` varchar(50) COLLATE utf8_unicode_ci DEFAULT NULL,
  `per_Zip` varchar(50) COLLATE utf8_unicode_ci DEFAULT NULL,
  `per_Country` varchar(50) COLLATE utf8_unicode_ci DEFAULT NULL,
  `per_HomePhone` varchar(30) COLLATE utf8_unicode_ci DEFAULT NULL,
  `per_WorkPhone` varchar(30) COLLATE utf8_unicode_ci DEFAULT NULL,
  `per_CellPhone` varchar(30) COLLATE utf8_unicode_ci DEFAULT NULL,
  `per_Email` varchar(50) COLLATE utf8_unicode_ci DEFAULT NULL,
  `per_WorkEmail` varchar(50) COLLATE utf8_unicode_ci DEFAULT NULL,
  `per_BirthMonth` tinyint(3) unsigned NOT NULL DEFAULT '0',
  `per_BirthDay` tinyint(3) unsigned NOT NULL DEFAULT '0',
  `per_BirthYear` year(4) DEFAULT NULL,
  `per_MembershipDate` date DEFAULT NULL,
  `per_Gender` tinyint(1) unsigned NOT NULL DEFAULT '0',
  `per_fmr_ID` tinyint(3) unsigned NOT NULL DEFAULT '0',
  `per_cls_ID` tinyint(3) unsigned NOT NULL DEFAULT '0',
  `per_fam_ID` smallint(5) unsigned NOT NULL DEFAULT '0',
  `per_Envelope` smallint(5) unsigned DEFAULT NULL,
  `per_DateLastEdited` datetime DEFAULT NULL,
  `per_DateEntered` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  `per_EnteredBy` smallint(5) unsigned NOT NULL DEFAULT '0',
  `per_EditedBy` smallint(5) unsigned DEFAULT '0',
  `per_FriendDate` date DEFAULT NULL,
  `per_Flags` mediumint(9) NOT NULL DEFAULT '0',
  PRIMARY KEY (`per_ID`),
  KEY `per_ID` (`per_ID`)
) ENGINE=MyISAM AUTO_INCREMENT=69 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `person_per`
--

LOCK TABLES `person_per` WRITE;
/*!40000 ALTER TABLE `person_per` DISABLE KEYS */;
INSERT INTO `person_per` VALUES (1,NULL,'ChurchInfo',NULL,'Admin',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,0,0,0000,NULL,0,0,0,0,NULL,NULL,'08-25 18:00:00',0,0,NULL,0),(2,'Mr.','Charles','','Klotz','','','','','','','United States','5555555555','','','someone@somewhere.com','',4,6,NULL,NULL,1,1,1,1,0,'2015-11-30 21:56:22','2015-11-21 21:04:50',1,2,'2015-11-21',0),(3,'Mrs.','Anna','','Klotz','','','','','','','United States','','','5555555555','someone@somewhere.com','',1,4,NULL,NULL,2,2,1,1,0,'2015-11-22 09:28:08','2015-11-21 21:05:37',1,2,'2015-11-21',0),(4,'Mr.','Tim','','Proberte','','','','','','','United States','','','5555555555','someone@somewhere.com','',11,24,NULL,NULL,1,1,1,2,0,'2015-11-29 19:33:55','2015-11-21 21:06:08',1,2,'2015-11-21',0),(5,'Mrs.','Mariska','','Proberte','','','','','','','United States','','','5555555555','someone@somewhere.com','',8,22,NULL,NULL,2,2,1,2,0,'2015-12-02 22:16:11','2015-11-21 21:06:22',1,2,'2015-11-21',0),(6,'Mr.','Jon','A','Tagiuri','','','','','','','United States','','','5555555555','someone@somewhere.com','',7,19,NULL,NULL,1,1,1,3,0,'2015-11-22 17:40:01','2015-11-22 09:05:07',2,2,'2015-11-22',0),(7,'Mrs.','Jamie','','Tagiuri','','','','','','','United States','','','5555555555','someone@somewhere.com','',10,13,NULL,NULL,2,2,1,3,0,'2015-11-22 17:40:17','2015-11-22 09:05:43',2,2,'2015-11-22',0),(8,'','Chaddy','','Tagiuri','','','','','','','United States','','','','','',10,16,NULL,NULL,1,3,1,3,0,'2015-12-02 22:15:40','2015-11-22 09:06:03',2,2,'2015-11-22',0),(9,'Ms.','Amanda','','Tagiuri','','','','','','','United States','','','5555555555','someone@somewhere.com','',5,28,NULL,NULL,2,1,1,24,0,'2015-11-29 13:20:16','2015-11-22 09:08:13',2,2,'2015-11-22',0),(10,'Mrs','Patrizia','','Tagiuri','','','','','','','United States','','','5555555555','someone@somewhere.com','',9,15,NULL,NULL,2,2,1,4,0,'2015-12-02 22:16:35','2015-11-22 09:09:25',2,2,'2015-11-22',0),(11,'Mr.','Matthew','','Tagiuri','','','','','','','United States','','','5555555555','someone@somewhere.com','',4,12,NULL,NULL,0,1,1,5,0,'2015-11-22 17:36:55','2015-11-22 09:10:05',2,2,'2015-11-22',0),(12,'Mrs.','Leah','','Tagiuri','','','','','','','United States','','','5555555555','someone@somewhere.com','',12,13,NULL,NULL,2,2,1,5,0,'2015-11-22 17:37:09','2015-11-22 09:10:16',2,2,'2015-11-22',0),(13,'Mr.','Bruce','','Tagiuri','','','','','','','United States','','','','','',5,12,NULL,NULL,1,1,1,4,0,'2015-11-22 17:33:57','2015-11-22 09:14:01',2,2,'2015-11-22',0),(14,'Mr.','Jim','','Gerig','','','','','','','United States','','','5555555555','someone@somewhere.com','',5,20,NULL,NULL,1,1,1,6,0,'2015-11-22 19:13:51','2015-11-22 09:14:32',2,2,'2015-11-22',0),(15,'Mrs.','Jan','','Gerig','','','','','','','United States','','','5555555555','someone@somewhere.com','',5,31,NULL,NULL,2,2,1,6,0,'2015-11-22 19:14:04','2015-11-22 09:15:22',2,2,'2015-11-22',0),(16,'Mr.','David','','Preston','','','','','','','United States','','','5555555555','someone@somewhere.com','',10,6,NULL,NULL,1,1,1,7,0,'2015-12-02 22:22:01','2015-11-22 09:18:19',2,2,'2015-11-22',0),(17,'Mrs.','Christy','','Hitchcock','','','','','','','United States','','','5555555555','someone@somewhere.com','',1,10,NULL,NULL,2,2,1,7,0,'2015-11-22 17:28:22','2015-11-22 09:18:36',2,2,'2015-11-22',0),(18,'','Ailene','','Preston','','','','','','','United States','','','','','',6,25,NULL,NULL,2,3,1,7,0,'2015-12-02 22:22:11','2015-11-22 09:19:19',2,2,'2015-11-22',0),(19,'Dr.','Richard','E','Black','','','','','','','United States','','','5555555555','someone@somewhere.com','',9,12,NULL,NULL,1,1,1,8,0,'2015-12-02 22:13:13','2015-11-22 09:25:42',2,2,'2015-11-22',0),(20,'Mrs.','Donna','','Black','','','','','','','United States','','','5555555555','someone@somewhere.com','',6,4,NULL,NULL,2,2,1,8,0,'2015-12-02 22:13:30','2015-11-22 09:25:59',2,2,'2015-11-22',0),(21,'Mr.','Phil','','Custodio','','','','','','','United States','','','5555555555','someone@somewhere.com','',4,7,NULL,NULL,1,1,1,9,0,'2015-11-30 20:27:59','2015-11-22 09:36:24',2,2,'2015-11-22',1),(22,'Mrs.','Lindsey','','Custodio','','','','','','','United States','','','5555555555','someone@somewhere.com','',7,31,NULL,NULL,2,2,1,9,0,'2015-11-30 20:32:50','2015-11-22 09:36:38',2,2,'2015-11-22',1),(23,'Mr.','Damon','','Destavola','','','','','','','United States','','','5555555555','','',7,9,NULL,NULL,1,1,1,10,0,'2015-11-29 18:05:17','2015-11-22 09:43:43',2,2,'2015-11-22',0),(24,'Mrs.','Gabi','','Destavola','','','','','','','United States','','','5555555555','','',9,8,NULL,NULL,2,2,1,10,0,'2015-12-02 22:17:59','2015-11-22 09:44:00',2,2,'2015-11-22',0),(25,'','Goddart','','Destavola','','','','','','','United States','','','','','',8,8,NULL,NULL,1,3,1,10,0,'2015-12-02 22:14:30','2015-11-22 09:44:28',2,2,'2015-11-22',0),(26,'','Gert','','Destavola','','','','','','','United States','','','','','',3,23,NULL,NULL,1,3,1,10,0,'2015-12-02 22:14:37','2015-11-22 09:44:35',2,2,'2015-11-22',0),(27,'','Mill','','Destavola','','','','','','','United States','','','','','',7,27,NULL,NULL,1,3,1,10,0,'2015-12-02 22:14:45','2015-11-22 09:44:58',2,2,'2015-11-22',0),(28,'Mr.','Richard','','Dibb','','','','','','','United States','','','5555555555','someone@somewhere.com','',8,26,NULL,NULL,1,1,1,11,0,'2015-11-22 17:23:54','2015-11-22 10:01:03',2,2,'2015-11-22',0),(29,'Mrs.','Bernadette','','Dibb','','','','','','','United States','','','5555555555','someone@somewhere.com','',8,23,NULL,NULL,2,2,1,11,0,'2015-11-22 17:24:15','2015-11-22 10:01:25',2,2,'2015-11-22',0),(30,'','Michael','','Scogin','','','','','','','','','','5555555555','someone@somewhere.com','',5,7,NULL,NULL,1,1,1,12,0,'2015-11-22 17:42:36','2015-11-22 12:23:18',2,2,NULL,0),(31,'','Maryann','','Scogin','','','','','','','','','','5555555555','someone@somewhere.com','',10,11,NULL,NULL,2,2,1,12,0,'2015-12-02 22:15:14','2015-11-22 12:23:18',2,2,NULL,0),(32,'','Constantin','','Loughman','','','','','','','','','','','someone@somewhere.com','',0,0,NULL,NULL,1,1,1,14,0,'2015-11-22 17:43:29','2015-11-22 12:28:41',2,2,NULL,0),(33,'','Anna','','Loughman','','','','','','','','','','','someone@somewhere.com','',0,0,NULL,NULL,2,2,1,14,0,'2015-11-22 17:46:12','2015-11-22 12:28:41',2,2,NULL,0),(34,'','Torrin','','Cavitch','','','','','','','','','','','someone@somewhere.com','',10,2,NULL,NULL,1,1,1,15,0,'2015-12-02 22:17:24','2015-11-22 16:26:34',2,2,NULL,0),(35,'','Penelopa','','Cavitch','','','','','','','','','','5555555555','someone@somewhere.com','',6,29,NULL,NULL,2,2,1,15,0,'2015-12-02 22:17:35','2015-11-22 16:26:35',2,2,NULL,0),(36,'','Jim','','Scogin','','','','','','','','','','','','',5,13,NULL,NULL,1,1,1,16,0,'2015-11-22 17:41:02','2015-11-22 16:28:33',2,2,NULL,0),(37,'','Debbie','','Scogin','','','','','','','','','','5555555555','someone@somewhere.com','',9,11,NULL,NULL,2,2,1,16,0,'2015-11-22 17:41:47','2015-11-22 16:28:33',2,2,NULL,0),(38,'','Noel','','Hitchcock','','','','','','','United States','5555555555','','','someone@somewhere.com','',2,10,NULL,NULL,2,0,1,17,0,'2015-11-22 17:14:48','2015-11-22 17:01:38',2,2,'2015-11-22',0),(39,'','Eamon','','Probert','','','','','','','','','','5555555555','someone@somewhere.com','',4,1,NULL,NULL,1,1,1,18,0,'2015-12-02 22:15:51','2015-11-22 17:02:52',2,2,NULL,0),(40,'','Nancy','','Probert','','','','','','','','','','5555555555','','',9,6,NULL,NULL,2,2,1,18,0,'2015-11-22 17:19:31','2015-11-22 17:02:52',2,2,NULL,0),(41,'','Dan','','Dibb','','','','','','','','','','5555555555','someone@somewhere.com','',2,5,NULL,NULL,1,1,1,19,0,'2015-11-22 17:22:41','2015-11-22 17:06:27',2,2,NULL,0),(42,'','Lorilyn','','Dibb','','','','','','','','','','5555555555','someone@somewhere.com','',8,14,NULL,NULL,2,2,1,19,0,'2015-12-02 22:18:13','2015-11-22 17:06:27',2,2,NULL,0),(43,'','Domingo','','Dibb','','','','','','','','','','','','',6,3,NULL,NULL,1,3,1,19,0,'2015-12-02 22:20:39','2015-11-22 17:06:27',2,2,NULL,0),(44,'','Linus','','Dibb','','','','','','','','','','','','',1,23,NULL,NULL,1,3,1,19,0,'2015-12-02 22:18:28','2015-11-22 17:06:27',2,2,NULL,0),(45,'Mr.','Arvin','','Lamas','','','','','','','','','','','someone@somewhere.com','',0,0,NULL,NULL,1,1,1,20,0,'2015-12-02 22:20:10','2015-11-22 17:09:54',2,2,NULL,0),(46,'','Tyler','','Peterkin','','','','','','','United States','','','','','',12,26,NULL,NULL,1,3,5,15,0,NULL,'2015-11-22 17:16:17',2,0,'2015-11-22',0),(47,'','Brittani','','Peterkin','','','','','','','United States','','','','','',12,26,NULL,NULL,2,3,5,15,0,'2015-12-02 22:16:00','2015-11-22 17:16:49',2,2,'2015-11-22',0),(48,'Mr.','Demetrius','','Proberte','','','','','','','United States','','','5555555555','someone@somewhere.com','',1,30,NULL,NULL,1,3,3,18,0,NULL,'2015-11-22 17:21:14',2,0,'2015-11-22',0),(68,'','Joanna','','Scogin','','','','','','','United States','','','','','',10,17,NULL,NULL,2,3,1,12,0,'2015-12-02 22:16:50','2015-11-29 18:08:09',2,2,'2015-11-29',0),(49,'','Tory','','Preston','','','','','','','United States','','','','','',9,5,NULL,NULL,1,3,1,7,0,'2015-12-02 22:22:08','2015-11-22 17:28:47',2,2,'2015-11-22',0),(50,'','Jenny','','Black','','','','','','','United States','','','','','',9,12,NULL,NULL,2,4,1,8,0,'2015-12-02 22:13:22','2015-11-22 17:32:50',2,2,'2015-11-22',0),(51,'','Matthew','','Tagiuri','','','','','','','United States','','','','','',2,23,NULL,NULL,1,3,1,5,0,'2015-11-22 17:44:27','2015-11-22 17:37:41',2,2,'2015-11-22',0),(52,'','Elliott','','Tagiuri','','','','','','','United States','','','','','',7,18,NULL,NULL,2,3,1,5,0,'2015-12-02 22:16:19','2015-11-22 17:38:12',2,2,'2015-11-22',0),(53,'','Gracie','','Tagiuri','','','','','','','United States','','','','','',7,9,NULL,NULL,0,3,1,5,0,NULL,'2015-11-22 17:38:42',2,0,'2015-11-22',0),(54,'','William','','Tagiuri','','','','','','','United States','','','','','',6,19,NULL,NULL,1,3,1,5,0,NULL,'2015-11-22 17:39:13',2,0,'2015-11-22',0),(55,'','David','','Preston','','','','','','','United States','','','','','',11,5,NULL,NULL,1,3,1,7,0,'2015-12-02 22:22:16','2015-11-22 17:49:48',2,2,'2015-11-22',0),(56,'','Aaron','','Black','','','','','','','','','','5555555555','someone@somewhere.com','',0,0,NULL,NULL,1,1,1,21,0,'2015-11-22 17:58:38','2015-11-22 17:58:01',2,2,NULL,0),(57,'','Andrew','','Tagiuri','','','','','','','','','','5555555555','someone@somewhere.com','',9,29,NULL,NULL,1,1,1,22,0,'2015-11-22 18:59:31','2015-11-22 18:59:05',2,2,NULL,0),(58,'','Adrienne','','Tagiuri','','','','','','','','','','5555555555','someone@somewhere.com','',5,11,NULL,NULL,2,2,1,22,0,'2015-11-22 18:59:53','2015-11-22 18:59:05',2,2,NULL,0),(59,'','Isaac','','Dibb','','','','','','','United States','','','','','',9,23,NULL,NULL,1,3,1,19,0,NULL,'2015-11-24 19:13:59',2,0,'2015-11-24',0),(60,'','Adria','','Tagiuri','','','','','','','United States','','','','','',5,13,NULL,NULL,2,3,0,5,0,'2015-12-02 22:17:07','2015-11-29 09:32:42',2,2,'2015-11-29',0),(61,'','Naomi','','Scogin','','','','','','','United States','','','','','',0,0,NULL,NULL,2,3,0,12,0,NULL,'2015-11-29 09:33:50',2,0,'2015-11-29',0),(62,'','Fran','','Lamas','','','','','','','United States','','','','','',0,0,NULL,NULL,2,2,1,20,0,NULL,'2015-11-29 09:36:08',2,0,'2015-11-29',0),(63,'','Will','','Lamas','','','','','','','United States','','','','','',0,0,NULL,NULL,1,3,1,20,0,NULL,'2015-11-29 09:36:29',2,0,'2015-11-29',0),(64,'Mr.','George','R','Gerig','','','','','','','United States','','','','someone@somewhere.com','',0,0,NULL,NULL,1,1,1,23,0,'2015-11-29 17:28:18','2015-11-29 13:01:52',2,2,'2015-11-29',0),(65,'Mrs','Angela','','Gerig','','','','','','','United States','','','','','',0,0,NULL,NULL,2,2,0,23,0,'2015-11-29 17:28:26','2015-11-29 13:14:29',2,2,'2015-11-29',0),(66,'','Lily','','Custodio','','','','','','','United States','','','','','',6,9,NULL,NULL,2,3,1,9,0,'2015-12-02 22:13:44','2015-11-29 18:03:04',2,2,'2015-11-29',1),(67,'','Sophia','','Custodio','','','','','','','United States','','','','','',2,23,NULL,NULL,2,3,1,9,0,'2015-11-30 20:38:11','2015-11-29 18:03:41',2,2,'2015-11-29',1);
/*!40000 ALTER TABLE `person_per` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `pledge_plg`
--

DROP TABLE IF EXISTS `pledge_plg`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `pledge_plg` (
  `plg_plgID` mediumint(9) NOT NULL AUTO_INCREMENT,
  `plg_FamID` mediumint(9) DEFAULT NULL,
  `plg_FYID` mediumint(9) DEFAULT NULL,
  `plg_date` date DEFAULT NULL,
  `plg_amount` decimal(8,2) DEFAULT NULL,
  `plg_schedule` enum('Weekly','Monthly','Quarterly','Once','Other') COLLATE utf8_unicode_ci DEFAULT NULL,
  `plg_method` enum('CREDITCARD','CHECK','CASH','BANKDRAFT','EGIVE') COLLATE utf8_unicode_ci DEFAULT NULL,
  `plg_comment` text COLLATE utf8_unicode_ci,
  `plg_DateLastEdited` date NOT NULL DEFAULT '0000-00-00',
  `plg_EditedBy` mediumint(9) NOT NULL DEFAULT '0',
  `plg_PledgeOrPayment` enum('Pledge','Payment') COLLATE utf8_unicode_ci NOT NULL DEFAULT 'Pledge',
  `plg_fundID` tinyint(3) unsigned DEFAULT NULL,
  `plg_depID` mediumint(9) unsigned DEFAULT NULL,
  `plg_CheckNo` bigint(16) unsigned DEFAULT NULL,
  `plg_Problem` tinyint(1) DEFAULT NULL,
  `plg_scanString` text COLLATE utf8_unicode_ci,
  `plg_aut_ID` mediumint(9) NOT NULL DEFAULT '0',
  `plg_aut_Cleared` tinyint(1) NOT NULL DEFAULT '0',
  `plg_aut_ResultID` mediumint(9) NOT NULL DEFAULT '0',
  `plg_NonDeductible` decimal(8,2) NOT NULL,
  `plg_GroupKey` varchar(64) COLLATE utf8_unicode_ci NOT NULL,
  PRIMARY KEY (`plg_plgID`)
) ENGINE=MyISAM AUTO_INCREMENT=26 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `pledge_plg`
--

LOCK TABLES `pledge_plg` WRITE;
/*!40000 ALTER TABLE `pledge_plg` DISABLE KEYS */;
INSERT INTO `pledge_plg` VALUES (5,12,20,'2015-11-22',400.00,'Once','CASH','','2015-11-22',2,'Payment',1,1,NULL,NULL,'',0,0,0,0.00,'cash|0|12|1|2015-11-22'),(6,10,20,'2015-11-22',100.00,'Once','CHECK','','2015-11-22',2,'Payment',1,1,531,NULL,'',0,0,0,0.00,'531|0|10|1|2015-11-22'),(7,13,20,'2015-11-22',24.38,'Once','CHECK','','2015-11-22',2,'Payment',1,1,10648,NULL,'',0,0,0,0.00,'10648|0|13|1|2015-11-22'),(8,13,20,'2015-11-22',25.00,'Once','CASH','','2015-11-22',2,'Payment',1,1,0,NULL,'',0,0,0,0.00,'cash|0|13|1|2015-11-22'),(9,0,20,'2015-11-22',40.00,'Once','CASH','Anonymous','2015-11-22',2,'Payment',1,1,NULL,NULL,'',0,0,0,0.00,'cash|0|0|1|2015-11-22'),(10,14,20,'2015-11-22',75.00,'Once','CHECK','','2015-12-02',2,'Payment',1,1,648,NULL,'',0,0,0,0.00,'648|0|14|1|2015-11-22'),(11,14,20,'2015-11-22',20.00,'Once','CHECK','','2015-12-02',2,'Payment',1,1,140,NULL,'',0,0,0,0.00,'140|0|14|1|2015-11-22'),(12,4,20,'2015-11-22',263.80,'Once','CHECK','','2015-11-22',2,'Payment',1,1,2244,NULL,'',0,0,0,0.00,'2244|0|4|1|2015-11-22'),(13,8,20,'2015-11-22',150.00,'Once','CASH','','2015-11-22',2,'Payment',1,1,NULL,NULL,'',0,0,0,0.00,'cash|0|8|1|2015-11-22'),(14,1,20,'2015-11-22',150.00,'Once','CHECK','','2015-11-22',2,'Payment',1,1,269,NULL,'',0,0,0,0.00,'269|0|1|1|2015-11-22'),(15,24,20,'2015-11-29',40.00,'Once','CHECK','','2015-12-02',2,'Payment',1,2,198,NULL,'',0,0,0,0.00,'198|0|4|1|2015-11-29'),(16,5,20,'2015-11-29',250.00,'Once','CHECK','','2015-11-29',2,'Payment',1,2,1547,NULL,'',0,0,0,0.00,'1547|0|5|1|2015-11-29'),(17,2,20,'2015-11-29',20.00,'Once','CASH','','2015-11-29',2,'Payment',1,2,NULL,NULL,'',0,0,0,0.00,'cash|0|2|1|2015-11-29'),(18,14,20,'2015-11-29',50.00,'Once','CHECK','','2015-12-02',2,'Payment',1,2,649,NULL,'',0,0,0,0.00,'649|0|14|1|2015-11-29'),(19,16,20,'2015-11-29',400.00,'Once','CHECK','','2015-11-29',2,'Payment',1,2,454,NULL,'',0,0,0,0.00,'454|0|16|1|2015-11-29'),(20,23,20,'2015-11-29',60.00,'Once','CHECK','','2015-11-29',2,'Payment',1,2,2111,NULL,'',0,0,0,0.00,'2111|0|23|1|2015-11-29'),(21,23,20,'2015-11-29',60.00,'Once','CHECK','','2015-11-29',2,'Payment',1,2,2102,NULL,'',0,0,0,0.00,'2102|0|23|1|2015-11-29'),(22,23,20,'2015-11-29',60.00,'Once','CHECK','','2015-11-29',2,'Payment',1,2,2105,NULL,'',0,0,0,0.00,'2105|0|23|1|2015-11-29'),(23,19,20,'2015-11-29',340.00,'Once','CHECK','','2015-11-29',2,'Payment',1,2,165,NULL,'',0,0,0,0.00,'165|0|19|1|2015-11-29'),(24,6,20,'2015-11-29',350.00,'Once','CHECK','','2015-11-29',2,'Payment',1,2,5012,NULL,'',0,0,0,0.00,'5012|0|6|1|2015-11-29'),(25,9,20,'2015-11-29',23.37,'Once','CASH','Big Bag of Change','2015-12-02',2,'Payment',1,2,0,NULL,'',0,0,0,0.00,'cash|0|9|1|2015-11-29');
/*!40000 ALTER TABLE `pledge_plg` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `property_pro`
--

DROP TABLE IF EXISTS `property_pro`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `property_pro` (
  `pro_ID` mediumint(8) unsigned NOT NULL AUTO_INCREMENT,
  `pro_Class` varchar(10) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  `pro_prt_ID` mediumint(8) unsigned NOT NULL DEFAULT '0',
  `pro_Name` varchar(200) COLLATE utf8_unicode_ci NOT NULL DEFAULT '0',
  `pro_Description` text COLLATE utf8_unicode_ci NOT NULL,
  `pro_Prompt` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`pro_ID`),
  UNIQUE KEY `pro_ID` (`pro_ID`),
  KEY `pro_ID_2` (`pro_ID`)
) ENGINE=MyISAM AUTO_INCREMENT=4 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `property_pro`
--

LOCK TABLES `property_pro` WRITE;
/*!40000 ALTER TABLE `property_pro` DISABLE KEYS */;
INSERT INTO `property_pro` VALUES (1,'p',1,'Disabled','has a disability.','What is the nature of the disability?'),(2,'f',2,'Single Parent','is a single-parent household.',''),(3,'g',3,'Youth','is youth-oriented.','');
/*!40000 ALTER TABLE `property_pro` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `propertytype_prt`
--

DROP TABLE IF EXISTS `propertytype_prt`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `propertytype_prt` (
  `prt_ID` mediumint(9) NOT NULL AUTO_INCREMENT,
  `prt_Class` varchar(10) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  `prt_Name` varchar(50) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  `prt_Description` text COLLATE utf8_unicode_ci NOT NULL,
  PRIMARY KEY (`prt_ID`),
  UNIQUE KEY `prt_ID` (`prt_ID`),
  KEY `prt_ID_2` (`prt_ID`)
) ENGINE=MyISAM AUTO_INCREMENT=4 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `propertytype_prt`
--

LOCK TABLES `propertytype_prt` WRITE;
/*!40000 ALTER TABLE `propertytype_prt` DISABLE KEYS */;
INSERT INTO `propertytype_prt` VALUES (1,'p','General','General Person Properties'),(2,'f','General','General Family Properties'),(3,'g','General','General Group Properties');
/*!40000 ALTER TABLE `propertytype_prt` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `query_qry`
--

DROP TABLE IF EXISTS `query_qry`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `query_qry` (
  `qry_ID` mediumint(8) unsigned NOT NULL AUTO_INCREMENT,
  `qry_SQL` text COLLATE utf8_unicode_ci NOT NULL,
  `qry_Name` varchar(255) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  `qry_Description` text COLLATE utf8_unicode_ci NOT NULL,
  `qry_Count` tinyint(1) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`qry_ID`),
  UNIQUE KEY `qry_ID` (`qry_ID`),
  KEY `qry_ID_2` (`qry_ID`)
) ENGINE=MyISAM AUTO_INCREMENT=201 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `query_qry`
--

LOCK TABLES `query_qry` WRITE;
/*!40000 ALTER TABLE `query_qry` DISABLE KEYS */;
INSERT INTO `query_qry` VALUES (2,'SELECT COUNT(per_ID)\nAS \'Count\'\nFROM person_per','Person Count','Returns the total number of people in the database.',0),(3,'SELECT CONCAT(\'<a href=FamilyView.php?FamilyID=\',fam_ID,\'>\',fam_Name,\'</a>\') AS \'Family Name\', COUNT(*) AS \'No.\'\nFROM person_per\nINNER JOIN family_fam\nON fam_ID = per_fam_ID\nGROUP BY per_fam_ID\nORDER BY \'No.\' DESC','Family Member Count','Returns each family and the total number of people assigned to them.',0),(4,'SELECT per_ID as AddToCart,CONCAT(\'<a\r\nhref=PersonView.php?PersonID=\',per_ID,\'>\',per_FirstName,\'\r\n\',per_LastName,\'</a>\') AS Name,\r\nCONCAT(per_BirthMonth,\'/\',per_BirthDay,\'/\',per_BirthYear) AS \'Birth Date\',\r\nDATE_FORMAT(FROM_DAYS(TO_DAYS(NOW())-TO_DAYS(CONCAT(per_BirthYear,\'-\',per_BirthMonth,\'-\',per_BirthDay))),\'%Y\')+0 AS  \'Age\'\r\nFROM person_per\r\nWHERE\r\nDATE_ADD(CONCAT(per_BirthYear,\'-\',per_BirthMonth,\'-\',per_BirthDay),INTERVAL\r\n~min~ YEAR) <= CURDATE()\r\nAND\r\nDATE_ADD(CONCAT(per_BirthYear,\'-\',per_BirthMonth,\'-\',per_BirthDay),INTERVAL\r\n(~max~ + 1) YEAR) >= CURDATE()','Person by Age','Returns any person records with ages between two given ages.',1),(6,'SELECT COUNT(per_ID) AS Total FROM person_per WHERE per_Gender = ~gender~','Total By Gender','Total of records matching a given gender.',0),(7,'SELECT per_ID as AddToCart, CONCAT(per_FirstName,\' \',per_LastName) AS Name FROM person_per WHERE per_fmr_ID = ~role~ AND per_Gender = ~gender~','Person by Role and Gender','Selects person records with the family role and gender specified.',1),(9,'SELECT \r\nper_ID as AddToCart, \r\nCONCAT(per_FirstName,\' \',per_LastName) AS Name, \r\nCONCAT(r2p_Value,\' \') AS Value\r\nFROM person_per,record2property_r2p\r\nWHERE per_ID = r2p_record_ID\r\nAND r2p_pro_ID = ~PropertyID~\r\nORDER BY per_LastName','Person by Property','Returns person records which are assigned the given property.',1),(15,'SELECT per_ID as AddToCart, CONCAT(\'<a href=PersonView.php?PersonID=\',per_ID,\'>\',per_FirstName,\' \',per_MiddleName,\' \',per_LastName,\'</a>\') AS Name, fam_City as City, fam_State as State, fam_Zip as ZIP, per_HomePhone as HomePhone, per_Email as Email, per_WorkEmail as WorkEmail FROM person_per RIGHT JOIN family_fam ON family_fam.fam_id = person_per.per_fam_id WHERE ~searchwhat~ LIKE \'%~searchstring~%\'','Advanced Search','Search by any part of Name, City, State, Zip, Home Phone, Email, or Work Email.',1),(16,'SELECT per_ID as AddToCart, CONCAT(\'<a href=PersonView.php?PersonID=\',per_ID,\'>\',per_FirstName,\' \',per_LastName,\'</a>\') AS Name FROM person_per LEFT JOIN person2group2role_p2g2r ON per_id = p2g2r_per_ID LEFT JOIN group_grp a ON grp_ID = p2g2r_grp_ID LEFT JOIN list_lst b ON lst_ID = grp_RoleListID AND p2g2r_rle_ID = lst_OptionID WHERE lst_OptionName = \'Teacher\'','Find Teachers','Find all people assigned to Sunday school classes as teachers',1),(17,'SELECT per_ID as AddToCart, CONCAT(\'<a href=PersonView.php?PersonID=\',per_ID,\'>\',per_FirstName,\' \',per_LastName,\'</a>\') AS Name FROM person_per LEFT JOIN person2group2role_p2g2r ON per_id = p2g2r_per_ID LEFT JOIN group_grp a ON grp_ID = p2g2r_grp_ID LEFT JOIN list_lst b ON lst_ID = grp_RoleListID AND p2g2r_rle_ID = lst_OptionID WHERE lst_OptionName = \'Student\'','Find Students','Find all people assigned to Sunday school classes as students',1),(18,'SELECT per_ID as AddToCart, per_BirthDay as Day, CONCAT(per_FirstName,\' \',per_LastName) AS Name FROM person_per WHERE per_cls_ID=~percls~ AND per_BirthMonth=~birthmonth~ ORDER BY per_BirthDay','Birthdays','People with birthdays in a particular month',0),(19,'SELECT per_ID as AddToCart, CONCAT(\'<a href=PersonView.php?PersonID=\',per_ID,\'>\',per_FirstName,\' \',per_LastName,\'</a>\') AS Name FROM person_per LEFT JOIN person2group2role_p2g2r ON per_id = p2g2r_per_ID LEFT JOIN group_grp a ON grp_ID = p2g2r_grp_ID LEFT JOIN list_lst b ON lst_ID = grp_RoleListID AND p2g2r_rle_ID = lst_OptionID WHERE lst_OptionName = \'Student\' AND grp_ID = ~group~ ORDER BY per_LastName','Class Students','Find students for a particular class',1),(20,'SELECT per_ID as AddToCart, CONCAT(\'<a href=PersonView.php?PersonID=\',per_ID,\'>\',per_FirstName,\' \',per_LastName,\'</a>\') AS Name FROM person_per LEFT JOIN person2group2role_p2g2r ON per_id = p2g2r_per_ID LEFT JOIN group_grp a ON grp_ID = p2g2r_grp_ID LEFT JOIN list_lst b ON lst_ID = grp_RoleListID AND p2g2r_rle_ID = lst_OptionID WHERE lst_OptionName = \'Teacher\' AND grp_ID = ~group~ ORDER BY per_LastName','Class Teachers','Find teachers for a particular class',1),(21,'SELECT per_ID as AddToCart, CONCAT(\'<a href=PersonView.php?PersonID=\',per_ID,\'>\',per_FirstName,\' \',per_LastName,\'</a>\') AS Name FROM person_per LEFT JOIN person2group2role_p2g2r ON per_id = p2g2r_per_ID WHERE p2g2r_grp_ID=~group~ ORDER BY per_LastName','Registered students','Find Registered students',1),(22,'SELECT per_ID as AddToCart, DAYOFMONTH(per_MembershipDate) as Day, per_MembershipDate AS DATE, CONCAT(per_FirstName,\' \',per_LastName) AS Name FROM person_per WHERE per_cls_ID=1 AND MONTH(per_MembershipDate)=~membermonth~ ORDER BY per_MembershipDate','Membership anniversaries','Members who joined in a particular month',0),(23,'SELECT usr_per_ID as AddToCart, CONCAT(a.per_FirstName,\' \',a.per_LastName) AS Name FROM user_usr LEFT JOIN person_per a ON per_ID=usr_per_ID ORDER BY per_LastName','Select database users','People who are registered as database users',0),(24,'SELECT per_ID as AddToCart, CONCAT(\'<a href=PersonView.php?PersonID=\',per_ID,\'>\',per_FirstName,\' \',per_LastName,\'</a>\') AS Name FROM person_per WHERE per_cls_id =1','Select all members','People who are members',0),(25,'SELECT per_ID as AddToCart, CONCAT(\'<a href=PersonView.php?PersonID=\',per_ID,\'>\',per_FirstName,\' \',per_LastName,\'</a>\') AS Name FROM person_per LEFT JOIN person2volunteeropp_p2vo ON per_id = p2vo_per_ID WHERE p2vo_vol_ID = ~volopp~ ORDER BY per_LastName','Volunteers','Find volunteers for a particular opportunity',1),(26,'SELECT per_ID as AddToCart, CONCAT(per_FirstName,\' \',per_LastName) AS Name FROM person_per WHERE DATE_SUB(NOW(),INTERVAL ~friendmonths~ MONTH)<per_FriendDate ORDER BY per_MembershipDate','Recent friends','Friends who signed up in previous months',0),(27,'SELECT per_ID as AddToCart, CONCAT(per_FirstName,\' \',per_LastName) AS Name FROM person_per inner join family_fam on per_fam_ID=fam_ID where per_fmr_ID<>3 AND fam_OkToCanvass=\"TRUE\" ORDER BY fam_Zip','Families to Canvass','People in families that are ok to canvass.',0),(28,'SELECT fam_Name, a.plg_amount as PlgFY1, b.plg_amount as PlgFY2 from family_fam left join pledge_plg a on a.plg_famID = fam_ID and a.plg_FYID=~fyid1~ and a.plg_PledgeOrPayment=\'Pledge\' left join pledge_plg b on b.plg_famID = fam_ID and b.plg_FYID=~fyid2~ and b.plg_PledgeOrPayment=\'Pledge\' order by fam_Name','Pledge comparison','Compare pledges between two fiscal years',1),(30,'SELECT per_ID as AddToCart, CONCAT(per_FirstName,\' \',per_LastName) AS Name, fam_address1, fam_city, fam_state, fam_zip FROM person_per join family_fam on per_fam_id=fam_id where per_fmr_id<>3 and per_fam_id in (select fam_id from family_fam inner join pledge_plg a on a.plg_famID=fam_ID and a.plg_FYID=~fyid1~ and a.plg_amount>0) and per_fam_id not in (select fam_id from family_fam inner join pledge_plg b on b.plg_famID=fam_ID and b.plg_FYID=~fyid2~ and b.plg_amount>0)','Missing pledges','Find people who pledged one year but not another',1),(31,'select per_ID as AddToCart, per_FirstName, per_LastName, per_email from person_per, autopayment_aut where aut_famID=per_fam_ID and aut_CreditCard!=\"\" and per_email!=\"\" and (per_fmr_ID=1 or per_fmr_ID=2 or per_cls_ID=1)','Credit Card People','People who are configured to pay by credit card.',0),(32,'SELECT fam_Name, fam_Envelope, b.fun_Name as Fund_Name, a.plg_amount as Pledge from family_fam left join pledge_plg a on a.plg_famID = fam_ID and a.plg_FYID=~fyid~ and a.plg_PledgeOrPayment=\'Pledge\' and a.plg_amount>0 join donationfund_fun b on b.fun_ID = a.plg_fundID order by fam_Name, a.plg_fundID','Family Pledge by Fiscal Year','Pledge summary by family name for each fund for the selected fiscal year',1),(100,'SELECT a.per_ID as AddToCart, CONCAT(\'<a href=PersonView.php?PersonID=\',a.per_ID,\'>\',a.per_FirstName,\' \',a.per_LastName,\'</a>\') AS Name FROM person_per AS a LEFT JOIN person2volunteeropp_p2vo p2v1 ON (a.per_id = p2v1.p2vo_per_ID AND p2v1.p2vo_vol_ID = ~volopp1~) LEFT JOIN person2volunteeropp_p2vo p2v2 ON (a.per_id = p2v2.p2vo_per_ID AND p2v2.p2vo_vol_ID = ~volopp2~) WHERE p2v1.p2vo_per_ID=p2v2.p2vo_per_ID ORDER BY per_LastName','Volunteers','Find volunteers for who match two specific opportunity codes',1),(200,'SELECT a.per_ID as AddToCart, CONCAT(\'<a href=PersonView.php?PersonID=\',a.per_ID,\'>\',a.per_FirstName,\' \',a.per_LastName,\'</a>\') AS Name FROM person_per AS a LEFT JOIN person_custom pc ON a.per_id = pc.per_ID WHERE pc.~custom~=\'~value~\' ORDER BY per_LastName','CustomSearch','Find people with a custom field value',1);
/*!40000 ALTER TABLE `query_qry` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `queryparameteroptions_qpo`
--

DROP TABLE IF EXISTS `queryparameteroptions_qpo`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `queryparameteroptions_qpo` (
  `qpo_ID` smallint(5) unsigned NOT NULL AUTO_INCREMENT,
  `qpo_qrp_ID` mediumint(8) unsigned NOT NULL DEFAULT '0',
  `qpo_Display` varchar(50) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  `qpo_Value` varchar(50) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  PRIMARY KEY (`qpo_ID`),
  UNIQUE KEY `qpo_ID` (`qpo_ID`)
) ENGINE=MyISAM AUTO_INCREMENT=37 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `queryparameteroptions_qpo`
--

LOCK TABLES `queryparameteroptions_qpo` WRITE;
/*!40000 ALTER TABLE `queryparameteroptions_qpo` DISABLE KEYS */;
INSERT INTO `queryparameteroptions_qpo` VALUES (1,4,'Male','1'),(2,4,'Female','2'),(3,6,'Male','1'),(4,6,'Female','2'),(5,15,'Name','CONCAT(per_FirstName,per_MiddleName,per_LastName)'),(6,15,'Zip Code','fam_Zip'),(7,15,'State','fam_State'),(8,15,'City','fam_City'),(9,15,'Home Phone','per_HomePhone'),(10,27,'2012/2013','17'),(11,27,'2013/2014','18'),(12,27,'2014/2015','19'),(13,27,'2015/2016','20'),(14,28,'2012/2013','17'),(15,28,'2013/2014','18'),(16,28,'2014/2015','19'),(17,28,'2015/2016','20'),(18,30,'2012/2013','17'),(19,30,'2013/2014','18'),(20,30,'2014/2015','19'),(21,30,'2015/2016','20'),(22,31,'2012/2013','17'),(23,31,'2013/2014','18'),(24,31,'2014/2015','19'),(25,31,'2015/2016','20'),(26,15,'Email','per_Email'),(27,15,'WorkEmail','per_WorkEmail'),(28,32,'2012/2013','17'),(29,32,'2013/2014','18'),(30,32,'2014/2015','19'),(31,32,'2015/2016','20'),(32,33,'Member','1'),(33,33,'Regular Attender','2'),(34,33,'Guest','3'),(35,33,'Non-Attender','4'),(36,33,'Non-Attender (staff)','5');
/*!40000 ALTER TABLE `queryparameteroptions_qpo` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `queryparameters_qrp`
--

DROP TABLE IF EXISTS `queryparameters_qrp`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `queryparameters_qrp` (
  `qrp_ID` mediumint(8) unsigned NOT NULL AUTO_INCREMENT,
  `qrp_qry_ID` mediumint(8) unsigned NOT NULL DEFAULT '0',
  `qrp_Type` tinyint(3) unsigned NOT NULL DEFAULT '0',
  `qrp_OptionSQL` text COLLATE utf8_unicode_ci,
  `qrp_Name` varchar(25) COLLATE utf8_unicode_ci DEFAULT NULL,
  `qrp_Description` text COLLATE utf8_unicode_ci,
  `qrp_Alias` varchar(25) COLLATE utf8_unicode_ci DEFAULT NULL,
  `qrp_Default` varchar(25) COLLATE utf8_unicode_ci DEFAULT NULL,
  `qrp_Required` tinyint(3) unsigned NOT NULL DEFAULT '0',
  `qrp_InputBoxSize` tinyint(3) unsigned NOT NULL DEFAULT '0',
  `qrp_Validation` varchar(5) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  `qrp_NumericMax` int(11) DEFAULT NULL,
  `qrp_NumericMin` int(11) DEFAULT NULL,
  `qrp_AlphaMinLength` int(11) DEFAULT NULL,
  `qrp_AlphaMaxLength` int(11) DEFAULT NULL,
  PRIMARY KEY (`qrp_ID`),
  UNIQUE KEY `qrp_ID` (`qrp_ID`),
  KEY `qrp_ID_2` (`qrp_ID`),
  KEY `qrp_qry_ID` (`qrp_qry_ID`)
) ENGINE=MyISAM AUTO_INCREMENT=202 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `queryparameters_qrp`
--

LOCK TABLES `queryparameters_qrp` WRITE;
/*!40000 ALTER TABLE `queryparameters_qrp` DISABLE KEYS */;
INSERT INTO `queryparameters_qrp` VALUES (1,4,0,NULL,'Minimum Age','The minimum age for which you want records returned.','min','0',0,5,'n',120,0,NULL,NULL),(2,4,0,NULL,'Maximum Age','The maximum age for which you want records returned.','max','120',1,5,'n',120,0,NULL,NULL),(4,6,1,'','Gender','The desired gender to search the database for.','gender','1',1,0,'',0,0,0,0),(5,7,2,'SELECT lst_OptionID as Value, lst_OptionName as Display FROM list_lst WHERE lst_ID=2 ORDER BY lst_OptionSequence','Family Role','Select the desired family role.','role','1',0,0,'',0,0,0,0),(6,7,1,'','Gender','The gender for which you would like records returned.','gender','1',1,0,'',0,0,0,0),(8,9,2,'SELECT pro_ID AS Value, pro_Name as Display \r\nFROM property_pro\r\nWHERE pro_Class= \'p\' \r\nORDER BY pro_Name ','Property','The property for which you would like person records returned.','PropertyID','0',1,0,'',0,0,0,0),(9,10,2,'SELECT distinct don_date as Value, don_date as Display FROM donations_don ORDER BY don_date ASC','Beginning Date','Please select the beginning date to calculate total contributions for each member (i.e. YYYY-MM-DD). NOTE: You can only choose dates that conatain donations.','startdate','1',1,0,'0',0,0,0,0),(10,10,2,'SELECT distinct don_date as Value, don_date as Display FROM donations_don\r\nORDER BY don_date DESC','Ending Date','Please enter the last date to calculate total contributions for each member (i.e. YYYY-MM-DD).','enddate','1',1,0,'',0,0,0,0),(14,15,0,'','Search','Enter any part of the following: Name, City, State, Zip, Home Phone, Email, or Work Email.','searchstring','',1,0,'',0,0,0,0),(15,15,1,'','Field','Select field to search for.','searchwhat','1',1,0,'',0,0,0,0),(16,11,2,'SELECT distinct don_date as Value, don_date as Display FROM donations_don ORDER BY don_date ASC','Beginning Date','Please select the beginning date to calculate total contributions for each member (i.e. YYYY-MM-DD). NOTE: You can only choose dates that conatain donations.','startdate','1',1,0,'0',0,0,0,0),(17,11,2,'SELECT distinct don_date as Value, don_date as Display FROM donations_don\r\nORDER BY don_date DESC','Ending Date','Please enter the last date to calculate total contributions for each member (i.e. YYYY-MM-DD).','enddate','1',1,0,'',0,0,0,0),(18,18,0,'','Month','The birthday month for which you would like records returned.','birthmonth','1',1,0,'',12,1,1,2),(19,19,2,'SELECT grp_ID AS Value, grp_Name AS Display FROM group_grp ORDER BY grp_Type','Class','The sunday school class for which you would like records returned.','group','1',1,0,'',12,1,1,2),(20,20,2,'SELECT grp_ID AS Value, grp_Name AS Display FROM group_grp ORDER BY grp_Type','Class','The sunday school class for which you would like records returned.','group','1',1,0,'',12,1,1,2),(21,21,2,'SELECT grp_ID AS Value, grp_Name AS Display FROM group_grp ORDER BY grp_Type','Registered students','Group of registered students','group','1',1,0,'',12,1,1,2),(22,22,0,'','Month','The membership anniversary month for which you would like records returned.','membermonth','1',1,0,'',12,1,1,2),(25,25,2,'SELECT vol_ID AS Value, vol_Name AS Display FROM volunteeropportunity_vol ORDER BY vol_Name','Volunteer opportunities','Choose a volunteer opportunity','volopp','1',1,0,'',12,1,1,2),(26,26,0,'','Months','Number of months since becoming a friend','friendmonths','1',1,0,'',24,1,1,2),(27,28,1,'','First Fiscal Year','First fiscal year for comparison','fyid1','9',1,0,'',12,9,0,0),(28,28,1,'','Second Fiscal Year','Second fiscal year for comparison','fyid2','9',1,0,'',12,9,0,0),(30,30,1,'','First Fiscal Year','Pledged this year','fyid1','9',1,0,'',12,9,0,0),(31,30,1,'','Second Fiscal Year','but not this year','fyid2','9',1,0,'',12,9,0,0),(32,32,1,'','Fiscal Year','Fiscal Year.','fyid','9',1,0,'',12,9,0,0),(33,18,1,'','Classification','Member, Regular Attender, etc.','percls','1',1,0,'',12,1,1,2),(100,100,2,'SELECT vol_ID AS Value, vol_Name AS Display FROM volunteeropportunity_vol ORDER BY vol_Name','Volunteer opportunities','First volunteer opportunity choice','volopp1','1',1,0,'',12,1,1,2),(101,100,2,'SELECT vol_ID AS Value, vol_Name AS Display FROM volunteeropportunity_vol ORDER BY vol_Name','Volunteer opportunities','Second volunteer opportunity choice','volopp2','1',1,0,'',12,1,1,2),(200,200,2,'SELECT custom_field as Value, custom_Name as Display FROM person_custom_master','Custom field','Choose customer person field','custom','1',0,0,'',0,0,0,0),(201,200,0,'','Field value','Match custom field to this value','value','1',0,0,'',0,0,0,0);
/*!40000 ALTER TABLE `queryparameters_qrp` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `record2property_r2p`
--

DROP TABLE IF EXISTS `record2property_r2p`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `record2property_r2p` (
  `r2p_pro_ID` mediumint(8) unsigned NOT NULL DEFAULT '0',
  `r2p_record_ID` mediumint(8) unsigned NOT NULL DEFAULT '0',
  `r2p_Value` text COLLATE utf8_unicode_ci NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `record2property_r2p`
--

LOCK TABLES `record2property_r2p` WRITE;
/*!40000 ALTER TABLE `record2property_r2p` DISABLE KEYS */;
/*!40000 ALTER TABLE `record2property_r2p` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `result_res`
--

DROP TABLE IF EXISTS `result_res`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `result_res` (
  `res_ID` mediumint(9) NOT NULL AUTO_INCREMENT,
  `res_echotype1` text COLLATE utf8_unicode_ci NOT NULL,
  `res_echotype2` text COLLATE utf8_unicode_ci NOT NULL,
  `res_echotype3` text COLLATE utf8_unicode_ci NOT NULL,
  `res_authorization` text COLLATE utf8_unicode_ci NOT NULL,
  `res_order_number` text COLLATE utf8_unicode_ci NOT NULL,
  `res_reference` text COLLATE utf8_unicode_ci NOT NULL,
  `res_status` text COLLATE utf8_unicode_ci NOT NULL,
  `res_avs_result` text COLLATE utf8_unicode_ci NOT NULL,
  `res_security_result` text COLLATE utf8_unicode_ci NOT NULL,
  `res_mac` text COLLATE utf8_unicode_ci NOT NULL,
  `res_decline_code` text COLLATE utf8_unicode_ci NOT NULL,
  `res_tran_date` text COLLATE utf8_unicode_ci NOT NULL,
  `res_merchant_name` text COLLATE utf8_unicode_ci NOT NULL,
  `res_version` text COLLATE utf8_unicode_ci NOT NULL,
  `res_EchoServer` text COLLATE utf8_unicode_ci NOT NULL,
  PRIMARY KEY (`res_ID`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `result_res`
--

LOCK TABLES `result_res` WRITE;
/*!40000 ALTER TABLE `result_res` DISABLE KEYS */;
/*!40000 ALTER TABLE `result_res` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `user_usr`
--

DROP TABLE IF EXISTS `user_usr`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `user_usr` (
  `usr_per_ID` mediumint(9) unsigned NOT NULL DEFAULT '0',
  `usr_Password` text COLLATE utf8_unicode_ci NOT NULL,
  `usr_NeedPasswordChange` tinyint(3) unsigned NOT NULL DEFAULT '1',
  `usr_LastLogin` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  `usr_LoginCount` smallint(5) unsigned NOT NULL DEFAULT '0',
  `usr_FailedLogins` tinyint(3) unsigned NOT NULL DEFAULT '0',
  `usr_AddRecords` tinyint(3) unsigned NOT NULL DEFAULT '0',
  `usr_EditRecords` tinyint(3) unsigned NOT NULL DEFAULT '0',
  `usr_DeleteRecords` tinyint(3) unsigned NOT NULL DEFAULT '0',
  `usr_MenuOptions` tinyint(3) unsigned NOT NULL DEFAULT '0',
  `usr_ManageGroups` tinyint(3) unsigned NOT NULL DEFAULT '0',
  `usr_Finance` tinyint(3) unsigned NOT NULL DEFAULT '0',
  `usr_Communication` tinyint(3) unsigned NOT NULL DEFAULT '0',
  `usr_Notes` tinyint(3) unsigned NOT NULL DEFAULT '0',
  `usr_Admin` tinyint(3) unsigned NOT NULL DEFAULT '0',
  `usr_Workspacewidth` smallint(6) DEFAULT NULL,
  `usr_BaseFontSize` tinyint(4) DEFAULT NULL,
  `usr_SearchLimit` tinyint(4) DEFAULT '10',
  `usr_Style` varchar(50) COLLATE utf8_unicode_ci DEFAULT 'Style.css',
  `usr_showPledges` tinyint(1) NOT NULL DEFAULT '0',
  `usr_showPayments` tinyint(1) NOT NULL DEFAULT '0',
  `usr_showSince` date NOT NULL DEFAULT '0000-00-00',
  `usr_defaultFY` mediumint(9) NOT NULL DEFAULT '10',
  `usr_currentDeposit` mediumint(9) NOT NULL DEFAULT '0',
  `usr_UserName` varchar(32) COLLATE utf8_unicode_ci DEFAULT NULL,
  `usr_EditSelf` tinyint(3) unsigned NOT NULL DEFAULT '0',
  `usr_CalStart` date DEFAULT NULL,
  `usr_CalEnd` date DEFAULT NULL,
  `usr_CalNoSchool1` date DEFAULT NULL,
  `usr_CalNoSchool2` date DEFAULT NULL,
  `usr_CalNoSchool3` date DEFAULT NULL,
  `usr_CalNoSchool4` date DEFAULT NULL,
  `usr_CalNoSchool5` date DEFAULT NULL,
  `usr_CalNoSchool6` date DEFAULT NULL,
  `usr_CalNoSchool7` date DEFAULT NULL,
  `usr_CalNoSchool8` date DEFAULT NULL,
  `usr_SearchFamily` tinyint(3) DEFAULT NULL,
  `usr_Canvasser` tinyint(3) NOT NULL DEFAULT '0',
  PRIMARY KEY (`usr_per_ID`),
  UNIQUE KEY `usr_UserName` (`usr_UserName`),
  KEY `usr_per_ID` (`usr_per_ID`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `user_usr`
--

LOCK TABLES `user_usr` WRITE;
/*!40000 ALTER TABLE `user_usr` DISABLE KEYS */;
INSERT INTO `user_usr` VALUES (1,'c61d55216b9ebb70b2e86b9d389a845b8c02d070046c509164c73e4212f12417',0,'2015-11-22 05:48:52',2,0,1,1,1,1,1,1,1,1,1,580,9,10,'Style.css',0,0,'0000-00-00',20,1,'Admin',0,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,0,0),(2,'43f5b217d30b6f08c06acb5a19358ca945118fa3381dee6eebc5b3e2e0390e14',0,'2015-12-02 18:07:16',32,0,0,0,0,0,0,0,0,0,1,NULL,NULL,127,'',1,1,'2010-01-01',20,2,'charles',0,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,1,0),(4,'329d7ebd4b8f9054fb58c63f3fcd129adcfab6de5d9a4a06f7c2abdd1e1bf326',0,'2015-11-29 11:11:36',1,0,0,0,0,0,0,0,0,0,1,NULL,NULL,10,'',0,0,'0000-00-00',20,0,'tim',0,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,0),(31,'3b3ad733c8571384c133694595c33d96c638b36f08a484bd0ad38bf312fdb294',0,'2015-11-25 08:31:59',1,0,0,0,0,0,0,0,0,0,0,NULL,NULL,100,'',0,0,'0000-00-00',20,0,'dScogin',1,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,0,0);
/*!40000 ALTER TABLE `user_usr` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `userconfig_ucfg`
--

DROP TABLE IF EXISTS `userconfig_ucfg`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `userconfig_ucfg` (
  `ucfg_per_id` mediumint(9) unsigned NOT NULL,
  `ucfg_id` int(11) NOT NULL DEFAULT '0',
  `ucfg_name` varchar(50) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  `ucfg_value` text COLLATE utf8_unicode_ci,
  `ucfg_type` enum('text','number','date','boolean','textarea') COLLATE utf8_unicode_ci NOT NULL DEFAULT 'text',
  `ucfg_tooltip` text COLLATE utf8_unicode_ci NOT NULL,
  `ucfg_permission` enum('FALSE','TRUE') COLLATE utf8_unicode_ci NOT NULL DEFAULT 'FALSE',
  `ucfg_cat` varchar(20) COLLATE utf8_unicode_ci NOT NULL,
  PRIMARY KEY (`ucfg_per_id`,`ucfg_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `userconfig_ucfg`
--

LOCK TABLES `userconfig_ucfg` WRITE;
/*!40000 ALTER TABLE `userconfig_ucfg` DISABLE KEYS */;
INSERT INTO `userconfig_ucfg` VALUES (0,0,'bEmailMailto','1','boolean','User permission to send email via mailto: links','TRUE',''),(0,1,'sMailtoDelimiter',',','text','Delimiter to separate emails in mailto: links','TRUE',''),(0,2,'bSendPHPMail','0','boolean','User permission to send email using PHPMailer','FALSE',''),(0,3,'sFromEmailAddress','','text','Reply email address: PHPMailer','FALSE',''),(0,4,'sFromName','ChurchInfo Webmaster','text','Name that appears in From field: PHPMailer','FALSE',''),(0,5,'bCreateDirectory','0','boolean','User permission to create directories','FALSE','SECURITY'),(0,6,'bExportCSV','0','boolean','User permission to export CSV files','FALSE','SECURITY'),(0,7,'bUSAddressVerification','0','boolean','User permission to use IST Address Verification','FALSE',''),(1,0,'bEmailMailto','1','boolean','User permission to send email via mailto: links','TRUE',''),(1,1,'sMailtoDelimiter',',','text','user permission to send email via mailto: links','TRUE',''),(1,2,'bSendPHPMail','1','boolean','User permission to send email using PHPMailer','TRUE',''),(1,3,'sFromEmailAddress','','text','Reply email address for PHPMailer','TRUE',''),(1,4,'sFromName','ChurchInfo Webmaster','text','Name that appears in From field','TRUE',''),(1,5,'bCreateDirectory','1','boolean','User permission to create directories','TRUE',''),(1,6,'bExportCSV','1','boolean','User permission to export CSV files','TRUE',''),(1,7,'bUSAddressVerification','1','boolean','User permission to use IST Address Verification','TRUE',''),(0,10,'bAddEvent','0','boolean','Allow user to add new event','FALSE','SECURITY'),(0,11,'bSeePrivacyData','0','boolean','Allow user to see member privacy data, e.g. Birth Year, Age.','FALSE','SECURITY'),(2,0,'bEmailMailto','1','boolean','User permission to send email via mailto: links','TRUE',''),(2,1,'sMailtoDelimiter',',','text','Delimiter to separate emails in mailto: links','TRUE',''),(2,2,'bSendPHPMail','','boolean','User permission to send email using PHPMailer','FALSE',''),(2,3,'sFromEmailAddress','','text','Reply email address: PHPMailer','FALSE',''),(2,4,'sFromName','ChurchInfo Webmaster','text','Name that appears in From field: PHPMailer','FALSE',''),(2,5,'bCreateDirectory','1','boolean','User permission to create directories','FALSE','SECURITY'),(2,6,'bExportCSV','1','boolean','User permission to export CSV files','FALSE','SECURITY'),(2,7,'bUSAddressVerification','1','boolean','User permission to use IST Address Verification','FALSE',''),(2,10,'bAddEvent','1','boolean','Allow user to add new event','FALSE','SECURITY'),(2,11,'bSeePrivacyData','1','boolean','Allow user to see member privacy data, e.g. Birth Year, Age.','FALSE','SECURITY'),(4,0,'bEmailMailto','1','boolean','User permission to send email via mailto: links','TRUE',''),(4,1,'sMailtoDelimiter',',','text','Delimiter to separate emails in mailto: links','TRUE',''),(4,2,'bSendPHPMail','','boolean','User permission to send email using PHPMailer','FALSE',''),(4,3,'sFromEmailAddress','','text','Reply email address: PHPMailer','FALSE',''),(4,4,'sFromName','ChurchInfo Webmaster','text','Name that appears in From field: PHPMailer','FALSE',''),(4,5,'bCreateDirectory','1','boolean','User permission to create directories','TRUE','SECURITY'),(4,6,'bExportCSV','1','boolean','User permission to export CSV files','TRUE','SECURITY'),(4,7,'bUSAddressVerification','1','boolean','User permission to use IST Address Verification','TRUE',''),(4,10,'bAddEvent','1','boolean','Allow user to add new event','TRUE','SECURITY'),(4,11,'bSeePrivacyData','1','boolean','Allow user to see member privacy data, e.g. Birth Year, Age.','TRUE','SECURITY'),(31,0,'bEmailMailto','1','boolean','User permission to send email via mailto: links','TRUE',''),(31,1,'sMailtoDelimiter',',','text','Delimiter to separate emails in mailto: links','TRUE',''),(31,2,'bSendPHPMail','','boolean','User permission to send email using PHPMailer','FALSE',''),(31,3,'sFromEmailAddress','','text','Reply email address: PHPMailer','FALSE',''),(31,4,'sFromName','ChurchInfo Webmaster','text','Name that appears in From field: PHPMailer','FALSE',''),(31,5,'bCreateDirectory','','boolean','User permission to create directories','FALSE','SECURITY'),(31,6,'bExportCSV','','boolean','User permission to export CSV files','FALSE','SECURITY'),(31,7,'bUSAddressVerification','','boolean','User permission to use IST Address Verification','FALSE',''),(31,10,'bAddEvent','','boolean','Allow user to add new event','FALSE','SECURITY'),(31,11,'bSeePrivacyData','','boolean','Allow user to see member privacy data, e.g. Birth Year, Age.','FALSE','SECURITY');
/*!40000 ALTER TABLE `userconfig_ucfg` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `version_ver`
--

DROP TABLE IF EXISTS `version_ver`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `version_ver` (
  `ver_ID` mediumint(9) unsigned NOT NULL AUTO_INCREMENT,
  `ver_version` varchar(50) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  `ver_date` datetime DEFAULT NULL,
  PRIMARY KEY (`ver_ID`),
  UNIQUE KEY `ver_version` (`ver_version`)
) ENGINE=MyISAM AUTO_INCREMENT=4 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `version_ver`
--

LOCK TABLES `version_ver` WRITE;
/*!40000 ALTER TABLE `version_ver` DISABLE KEYS */;
INSERT INTO `version_ver` VALUES (3,'1.3.0','2015-11-21 17:59:52');
/*!40000 ALTER TABLE `version_ver` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `volunteeropportunity_vol`
--

DROP TABLE IF EXISTS `volunteeropportunity_vol`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `volunteeropportunity_vol` (
  `vol_ID` int(3) NOT NULL AUTO_INCREMENT,
  `vol_Order` int(3) NOT NULL DEFAULT '0',
  `vol_Active` enum('true','false') COLLATE utf8_unicode_ci NOT NULL DEFAULT 'true',
  `vol_Name` varchar(30) COLLATE utf8_unicode_ci DEFAULT NULL,
  `vol_Description` varchar(100) COLLATE utf8_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`vol_ID`),
  UNIQUE KEY `vol_ID` (`vol_ID`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `volunteeropportunity_vol`
--

LOCK TABLES `volunteeropportunity_vol` WRITE;
/*!40000 ALTER TABLE `volunteeropportunity_vol` DISABLE KEYS */;
/*!40000 ALTER TABLE `volunteeropportunity_vol` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `whycame_why`
--

DROP TABLE IF EXISTS `whycame_why`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `whycame_why` (
  `why_ID` mediumint(9) NOT NULL AUTO_INCREMENT,
  `why_per_ID` mediumint(9) NOT NULL DEFAULT '0',
  `why_join` text COLLATE utf8_unicode_ci NOT NULL,
  `why_come` text COLLATE utf8_unicode_ci NOT NULL,
  `why_suggest` text COLLATE utf8_unicode_ci NOT NULL,
  `why_hearOfUs` text COLLATE utf8_unicode_ci NOT NULL,
  PRIMARY KEY (`why_ID`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `whycame_why`
--

LOCK TABLES `whycame_why` WRITE;
/*!40000 ALTER TABLE `whycame_why` DISABLE KEYS */;
/*!40000 ALTER TABLE `whycame_why` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2015-12-03  3:22:37
