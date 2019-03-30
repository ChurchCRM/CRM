-- mysqldump-php https://github.com/ifsnop/mysqldump-php
--
-- Host: localhost	Database: churchcrm
-- ------------------------------------------------------
-- Server version 	5.7.21-0ubuntu0.16.04.1
-- Date: Mon, 09 Apr 2018 12:04:25 -0400

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
-- Table structure for table `calendar_events`
--

DROP TABLE IF EXISTS `calendar_events`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `calendar_events` (
  `calendar_id` int(11) NOT NULL,
  `event_id` int(11) NOT NULL,
  PRIMARY KEY (`calendar_id`,`event_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `calendar_events`
--

LOCK TABLES `calendar_events` WRITE;
/*!40000 ALTER TABLE `calendar_events` DISABLE KEYS */;
SET autocommit=0;
INSERT INTO `calendar_events` VALUES (2,1),(2,2),(2,3);
/*!40000 ALTER TABLE `calendar_events` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;

--
-- Table structure for table `calendars`
--

DROP TABLE IF EXISTS `calendars`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `calendars` (
  `calendar_id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(128) COLLATE utf8_unicode_ci NOT NULL,
  `accesstoken` varchar(99) COLLATE utf8_unicode_ci DEFAULT NULL,
  `foregroundColor` varchar(6) COLLATE utf8_unicode_ci DEFAULT NULL,
  `backgroundColor` varchar(6) COLLATE utf8_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`calendar_id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `calendars`
--

LOCK TABLES `calendars` WRITE;
/*!40000 ALTER TABLE `calendars` DISABLE KEYS */;
SET autocommit=0;
INSERT INTO `calendars` VALUES (1,'Public Calendar',NULL,'FFFFFF','00AA00'),(2,'Private Calendar',NULL,'FFFFFF','0000AA');
/*!40000 ALTER TABLE `calendars` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `canvassdata_can`
--

LOCK TABLES `canvassdata_can` WRITE;
/*!40000 ALTER TABLE `canvassdata_can` DISABLE KEYS */;
SET autocommit=0;
/*!40000 ALTER TABLE `canvassdata_can` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;

--
-- Table structure for table `church_location_person`
--

DROP TABLE IF EXISTS `church_location_person`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `church_location_person` (
  `location_id` int(11) NOT NULL,
  `person_id` int(11) NOT NULL,
  `order` int(11) NOT NULL,
  `person_location_role_id` int(11) NOT NULL,
  PRIMARY KEY (`location_id`,`person_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `church_location_person`
--

LOCK TABLES `church_location_person` WRITE;
/*!40000 ALTER TABLE `church_location_person` DISABLE KEYS */;
SET autocommit=0;
/*!40000 ALTER TABLE `church_location_person` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;

--
-- Table structure for table `church_location_role`
--

DROP TABLE IF EXISTS `church_location_role`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `church_location_role` (
  `location_id` int(11) NOT NULL,
  `role_id` int(11) NOT NULL,
  `role_order` int(11) NOT NULL,
  `role_title` int(11) NOT NULL,
  PRIMARY KEY (`location_id`,`role_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `church_location_role`
--

LOCK TABLES `church_location_role` WRITE;
/*!40000 ALTER TABLE `church_location_role` DISABLE KEYS */;
SET autocommit=0;
/*!40000 ALTER TABLE `church_location_role` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;

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
  PRIMARY KEY (`cfg_id`),
  UNIQUE KEY `cfg_name` (`cfg_name`),
  KEY `cfg_id` (`cfg_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `config_cfg`
--

LOCK TABLES `config_cfg` WRITE;
/*!40000 ALTER TABLE `config_cfg` DISABLE KEYS */;
SET autocommit=0;
INSERT INTO `config_cfg` VALUES (10,'aFinanceQueries','28,30,31,32'),(21,'sDefaultCity','Kansas City'),(22,'sDefaultState','MO'),(23,'sDefaultCountry','United States'),(27,'sSMTPHost','smtp.mailtrap.io:2525'),(28,'bSMTPAuth','1'),(29,'sSMTPUser','c58d4ec1a5a021'),(30,'sSMTPPass','3cfab2ee59990c'),(45,'iChurchLatitude','39.1111974'),(46,'iChurchLongitude','-94.5838009'),(48,'bHideFriendDate',''),(49,'bHideFamilyNewsletter',''),(50,'bHideWeddingDate',''),(51,'bHideLatLon',''),(52,'bUseDonationEnvelopes',''),(58,'bUseScannedChecks',''),(65,'sTimeZone','America/Detroit'),(67,'bForceUppercaseZip',''),(72,'bEnableNonDeductible',''),(80,'bEnableSelfRegistration','1'),(999,'bRegistered',''),(1003,'sChurchName','Main St. Cathedral'),(1004,'sChurchAddress','123 Main St'),(1005,'sChurchCity','Kansas City'),(1006,'sChurchState','MO'),(1007,'sChurchZip','64106'),(1008,'sChurchPhone','555 123 4234'),(1009,'sChurchEmail','demo@churchcrm.io'),(1010,'sHomeAreaCode','555'),(1014,'sTaxSigner','Elder Joe Smith'),(1016,'sReminderSigner','Elder Joe Smith'),(1025,'sConfirmSigner','Elder Joe Smith'),(1027,'sPledgeSummary2','as of'),(1028,'sDirectoryDisclaimer1','Every effort was made to insure the accuracy of this directory.  If there are any errors or omissions, please contact the church office.This directory is for the use of the people of'),(1034,'sChurchChkAcctNum','111111111'),(1035,'bEnableGravatarPhotos','1'),(1037,'sExternalBackupType','WebDAV'),(1046,'sLastIntegrityCheckTimeStamp','2018-04-09 12:03:12'),(1047,'sChurchCountry','United States'),(2010,'bAllowEmptyLastName',''),(2017,'bEnableExternalCalendarAPI',''),(2045,'bPHPMailerAutoTLS',''),(2046,'sPHPMailerSMTPSecure',''),(20142,'bHSTSEnable','');
/*!40000 ALTER TABLE `config_cfg` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;

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
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci PACK_KEYS=0;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `deposit_dep`
--

LOCK TABLES `deposit_dep` WRITE;
/*!40000 ALTER TABLE `deposit_dep` DISABLE KEYS */;
SET autocommit=0;
INSERT INTO `deposit_dep` VALUES (1,'2018-02-11','',NULL,0,'Bank'),(2,'2018-02-18','',NULL,0,'Bank'),(3,'2018-02-25','',NULL,0,'Bank'),(4,'2018-03-04','',NULL,0,'Bank'),(5,'2018-03-11','',NULL,0,'Bank');
/*!40000 ALTER TABLE `deposit_dep` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `donateditem_di`
--

LOCK TABLES `donateditem_di` WRITE;
/*!40000 ALTER TABLE `donateditem_di` DISABLE KEYS */;
SET autocommit=0;
/*!40000 ALTER TABLE `donateditem_di` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;

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
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `donationfund_fun`
--

LOCK TABLES `donationfund_fun` WRITE;
/*!40000 ALTER TABLE `donationfund_fun` DISABLE KEYS */;
SET autocommit=0;
INSERT INTO `donationfund_fun` VALUES (1,'true','Pledges','Pledge income for the operating budget'),(2,'true','New Building Fund',''),(3,'true','Music Ministry','');
/*!40000 ALTER TABLE `donationfund_fun` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `egive_egv`
--

LOCK TABLES `egive_egv` WRITE;
/*!40000 ALTER TABLE `egive_egv` DISABLE KEYS */;
SET autocommit=0;
/*!40000 ALTER TABLE `egive_egv` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `email_message_pending_emp`
--

LOCK TABLES `email_message_pending_emp` WRITE;
/*!40000 ALTER TABLE `email_message_pending_emp` DISABLE KEYS */;
SET autocommit=0;
/*!40000 ALTER TABLE `email_message_pending_emp` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `email_recipient_pending_erp`
--

LOCK TABLES `email_recipient_pending_erp` WRITE;
/*!40000 ALTER TABLE `email_recipient_pending_erp` DISABLE KEYS */;
SET autocommit=0;
/*!40000 ALTER TABLE `email_recipient_pending_erp` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;

--
-- Table structure for table `event_attend`
--

DROP TABLE IF EXISTS `event_attend`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `event_attend` (
  `attend_id` int(11) NOT NULL AUTO_INCREMENT,
  `event_id` int(11) NOT NULL DEFAULT '0',
  `person_id` int(11) NOT NULL DEFAULT '0',
  `checkin_date` datetime DEFAULT NULL,
  `checkin_id` int(11) DEFAULT NULL,
  `checkout_date` datetime DEFAULT NULL,
  `checkout_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`attend_id`),
  UNIQUE KEY `event_id` (`event_id`,`person_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `event_attend`
--

LOCK TABLES `event_attend` WRITE;
/*!40000 ALTER TABLE `event_attend` DISABLE KEYS */;
SET autocommit=0;
INSERT INTO `event_attend` VALUES (1,3,104,'2017-04-15 17:23:46',26,NULL,NULL);
/*!40000 ALTER TABLE `event_attend` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;

--
-- Table structure for table `event_audience`
--

DROP TABLE IF EXISTS `event_audience`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `event_audience` (
  `event_id` int(11) NOT NULL,
  `group_id` int(11) NOT NULL,
  PRIMARY KEY (`event_id`,`group_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `event_audience`
--

LOCK TABLES `event_audience` WRITE;
/*!40000 ALTER TABLE `event_audience` DISABLE KEYS */;
SET autocommit=0;
/*!40000 ALTER TABLE `event_audience` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;

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
  `type_defrecurDOY` date NOT NULL DEFAULT '2016-01-01',
  `type_active` int(1) NOT NULL DEFAULT '1',
  `type_grpid` mediumint(9) DEFAULT NULL,
  PRIMARY KEY (`type_id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `event_types`
--

LOCK TABLES `event_types` WRITE;
/*!40000 ALTER TABLE `event_types` DISABLE KEYS */;
SET autocommit=0;
INSERT INTO `event_types` VALUES (1,'Church Service','10:30:00','weekly','Sunday','','2016-01-01',1,NULL),(2,'Sunday School','09:30:00','weekly','Sunday','','2016-01-01',1,NULL);
/*!40000 ALTER TABLE `event_types` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;

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
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `eventcountnames_evctnm`
--

LOCK TABLES `eventcountnames_evctnm` WRITE;
/*!40000 ALTER TABLE `eventcountnames_evctnm` DISABLE KEYS */;
SET autocommit=0;
INSERT INTO `eventcountnames_evctnm` VALUES (1,1,'Total',''),(2,1,'Members',''),(3,1,'Visitors',''),(4,2,'Total',''),(5,2,'Members',''),(6,2,'Visitors','');
/*!40000 ALTER TABLE `eventcountnames_evctnm` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `eventcounts_evtcnt`
--

LOCK TABLES `eventcounts_evtcnt` WRITE;
/*!40000 ALTER TABLE `eventcounts_evtcnt` DISABLE KEYS */;
SET autocommit=0;
INSERT INTO `eventcounts_evtcnt` VALUES (1,4,'Total',25,''),(1,5,'Members',10,''),(1,6,'Visitors',0,''),(2,1,'Total',100,''),(2,2,'Members',0,''),(2,3,'Visitors',0,''),(3,4,'Total',100,''),(3,5,'Members',0,''),(3,6,'Visitors',0,'');
/*!40000 ALTER TABLE `eventcounts_evtcnt` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;

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
  `event_start` datetime NOT NULL,
  `event_end` datetime NOT NULL,
  `inactive` int(1) NOT NULL DEFAULT '0',
  `event_typename` varchar(40) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  `location_id` int(11) DEFAULT NULL,
  `secondary_contact_person_id` int(11) DEFAULT NULL,
  `primary_contact_person_id` int(11) DEFAULT NULL,
  `event_url` text COLLATE utf8_unicode_ci,
  PRIMARY KEY (`event_id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `events_event`
--

LOCK TABLES `events_event` WRITE;
/*!40000 ALTER TABLE `events_event` DISABLE KEYS */;
SET autocommit=0;
INSERT INTO `events_event` VALUES (1,2,'Sunday School Class Changes','This is when the students move to new classes','','2016-11-20 12:30:00','2016-11-20 13:30:00',0,'Sunday School',NULL,NULL,NULL,NULL),(2,1,'Christmas Service','christmas service','','2016-12-24 22:30:00','2016-12-25 01:30:00',0,'Church Service',NULL,NULL,NULL,NULL),(3,2,'Summer Camp','Summer Camp','','2017-06-06 09:30:00','2017-06-11 09:30:00',0,'Sunday School',NULL,NULL,NULL,NULL);
/*!40000 ALTER TABLE `events_event` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;

--
-- Table structure for table `family_custom`
--

DROP TABLE IF EXISTS `family_custom`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `family_custom` (
  `fam_ID` mediumint(9) NOT NULL DEFAULT '0',
  PRIMARY KEY (`fam_ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `family_custom`
--

LOCK TABLES `family_custom` WRITE;
/*!40000 ALTER TABLE `family_custom` DISABLE KEYS */;
SET autocommit=0;
/*!40000 ALTER TABLE `family_custom` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `family_custom_master`
--

LOCK TABLES `family_custom_master` WRITE;
/*!40000 ALTER TABLE `family_custom_master` DISABLE KEYS */;
SET autocommit=0;
/*!40000 ALTER TABLE `family_custom_master` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;

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
  `fam_DateEntered` datetime NOT NULL,
  `fam_DateLastEdited` datetime DEFAULT NULL,
  `fam_EnteredBy` smallint(5) NOT NULL DEFAULT '0',
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
) ENGINE=InnoDB AUTO_INCREMENT=22 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `family_fam`
--

LOCK TABLES `family_fam` WRITE;
/*!40000 ALTER TABLE `family_fam` DISABLE KEYS */;
SET autocommit=0;
INSERT INTO `family_fam` VALUES (1,'Campbell','3259 Daisy Dr','','Denton','AR','','United States','(728) 139-0768','','','',NULL,'2009-12-25 07:19:06','2016-11-19 15:50:57',1,1,NULL,NULL,'FALSE',NULL,'FALSE',0,33.174318,-97.0673599,0),(2,'Hart','4878 Valley View Ln','','Grand Rapids','ND','','United States','(042) 989-4488','','','',NULL,'2009-04-13 01:17:12','2016-11-19 15:53:10',1,1,NULL,NULL,'FALSE',NULL,'FALSE',0,47.9132828,-97.0864844,0),(3,'Lewis','2379 Northaven Rd','','Detroit','WV','','United States','(609) 441-0871','','','','2010-02-10','2007-11-19 10:08:41','2017-04-15 17:21:40',1,1,NULL,NULL,'FALSE',NULL,'FALSE',0,NULL,NULL,0),(4,'Ray','4212 Parker Rd','','Chesapeake','WI','','United States','(220) 345-1335','','','',NULL,'2003-10-14 16:05:17','2016-11-19 15:55:11',1,1,NULL,NULL,'FALSE','2017-04-15','FALSE',0,36.7001267,-76.2568083,0),(5,'Smith','5572 Robinson Rd','','Santa Clarita','KY','','United States','(886) 863-1106','','','','2016-09-12','2007-09-14 23:32:06','2017-04-15 17:22:04',1,1,NULL,NULL,'FALSE',NULL,'FALSE',0,NULL,NULL,0),(6,'Dixon','6730 Mockingbird Hill','','Roanoke','IL','','United States','(449) 349-7865','','','',NULL,'2013-07-25 20:18:03','2016-11-19 15:52:44',1,1,NULL,NULL,'FALSE',NULL,'FALSE',0,37.316406,-79.8874658,0),(7,'Stewart','7045 Wycliff Ave','','Gainesville','SD','','United States','(813) 837-2427','','','',NULL,'2011-08-17 04:00:29','2016-11-19 15:56:55',1,1,NULL,NULL,'FALSE',NULL,'FALSE',0,0,0,0),(9,'Diaz','1158 Harrison Ct','','Hialeah','IA','','United States','(613) 399-6088','','','',NULL,'2013-04-20 15:01:05','2016-11-19 15:52:17',1,1,NULL,NULL,'FALSE',NULL,'FALSE',0,33.4447804,-112.0542146,0),(10,'Gordon','1255 Brown Terrace','','Louisville','MI','','United States','(215) 006-0420','','','','2011-07-13','2004-09-09 18:40:30','2017-04-15 17:22:19',1,1,NULL,NULL,'FALSE',NULL,'FALSE',0,NULL,NULL,0),(11,'Newman','5427 Stevens Creek Blvd','','Orlando','MN','','United States','(792) 676-7007','','','',NULL,'2006-10-11 03:51:16','2016-11-19 15:54:22',1,1,NULL,NULL,'FALSE',NULL,'FALSE',0,46.729553,-94.6858998,0),(12,'Olson','1272 Shady Ln Dr','','Toledo','NE','','United States','(698) 235-3995','','','',NULL,'2014-08-31 04:21:43','2016-11-19 15:54:30',1,1,NULL,NULL,'FALSE',NULL,'FALSE',0,41.6932406,-83.5833554,0),(13,'Beck','6381 Valwood Pkwy','','Buffalo','ME','','United States','(237) 926-6342','','','','2010-07-22','2007-02-01 16:50:26','2016-11-19 15:49:36',1,1,NULL,NULL,'FALSE',NULL,'FALSE',0,46.8269852,-68.4858767,0),(14,'Berry','1931 Edwards Rd','','Riverside','PA','','United States','(174) 272-0341','','','',NULL,'2013-10-15 09:25:25','2016-11-19 15:49:57',1,1,NULL,NULL,'FALSE',NULL,'FALSE',0,40.0537096,-74.94436,0),(16,'Larson','3866 Edwards Rd','','Inglewood','CO','','United States','(663) 858-8880','','','',NULL,'2016-03-01 14:19:32','2016-11-19 15:53:48',1,1,NULL,NULL,'FALSE',NULL,'FALSE',0,29.3912942,-98.5103497,0),(17,'Cooper','1782 Daisy Dr','','Oxnard','GA','','United States','(718) 878-3276','','','',NULL,'2014-09-26 00:09:54','2016-11-19 15:51:35',1,1,NULL,NULL,'FALSE',NULL,'FALSE',0,34.2769999,-119.1552968,0),(18,'Riley','1403 Avondale Ave','','Scottsdale','ID','','United States','(055) 343-0760','','','','2010-12-22','2002-04-09 05:31:36','2016-11-19 16:43:48',1,1,NULL,NULL,'FALSE',NULL,'FALSE',0,33.4351234,-112.3063973,0),(19,'Kennedy','9481 Wycliff Ave','','Long Beach','KY','','United States','(306) 408-4342','','','',NULL,'2014-11-23 09:17:25','2016-11-19 15:53:40',1,1,NULL,NULL,'FALSE',NULL,'FALSE',0,37.8393332,-84.2700179,0),(20,'Black','4307 Avondale Ave','','Shiloh','CT','','United States','(828) 463-5829','','','',NULL,'2014-05-10 06:07:19','2016-11-19 15:50:25',1,1,NULL,NULL,'FALSE',NULL,'FALSE',0,33.4250486,-112.3982715,0),(21,'Smith','123 Main St.',NULL,'Seattle','WA','98121','United States','(206) 555-5555',NULL,NULL,NULL,NULL,'2017-04-15 17:19:26',NULL,-1,0,NULL,NULL,'FALSE',NULL,'FALSE',0,NULL,NULL,0);
/*!40000 ALTER TABLE `family_fam` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;

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
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `fundraiser_fr`
--

LOCK TABLES `fundraiser_fr` WRITE;
/*!40000 ALTER TABLE `fundraiser_fr` DISABLE KEYS */;
SET autocommit=0;
INSERT INTO `fundraiser_fr` VALUES (1,'2016-11-19','zczxc','zxczxczxc',1,'2016-11-19');
/*!40000 ALTER TABLE `fundraiser_fr` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;

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
  `grp_hasSpecialProps` tinyint(1) NOT NULL DEFAULT '0',
  `grp_active` tinyint(1) NOT NULL DEFAULT '1',
  `grp_include_email_export` tinyint(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`grp_ID`),
  UNIQUE KEY `grp_ID` (`grp_ID`),
  KEY `grp_ID_2` (`grp_ID`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `group_grp`
--

LOCK TABLES `group_grp` WRITE;
/*!40000 ALTER TABLE `group_grp` DISABLE KEYS */;
SET autocommit=0;
INSERT INTO `group_grp` VALUES (1,4,13,2,'Angels class',NULL,0,1,1),(2,4,14,2,'Class 1-3',NULL,0,1,1),(3,4,15,2,'Class 4-5',NULL,0,1,1),(4,4,16,2,'Class 6-7',NULL,0,1,1),(5,4,17,2,'High School Class',NULL,0,1,1),(6,4,18,2,'Youth Meeting',NULL,0,1,1),(7,0,19,1,'Boys Scouts',NULL,0,1,1),(8,0,20,1,'Girl Scouts',NULL,0,0,0),(9,0,21,1,'Church Board',NULL,0,1,0),(10,1,22,1,'Worship Service','',0,1,1);
/*!40000 ALTER TABLE `group_grp` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci COMMENT='Group-specific properties order, name, description, type';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `groupprop_master`
--

LOCK TABLES `groupprop_master` WRITE;
/*!40000 ALTER TABLE `groupprop_master` DISABLE KEYS */;
SET autocommit=0;
/*!40000 ALTER TABLE `groupprop_master` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;

--
-- Table structure for table `istlookup_lu`
--

DROP TABLE IF EXISTS `istlookup_lu`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `istlookup_lu` (
  `lu_fam_ID` mediumint(9) NOT NULL DEFAULT '0',
  `lu_LookupDateTime` datetime NOT NULL DEFAULT '2016-01-01 00:00:00',
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci COMMENT='US Address Verification Lookups From Intelligent Search Tech';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `istlookup_lu`
--

LOCK TABLES `istlookup_lu` WRITE;
/*!40000 ALTER TABLE `istlookup_lu` DISABLE KEYS */;
SET autocommit=0;
/*!40000 ALTER TABLE `istlookup_lu` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;

--
-- Table structure for table `kioskassginment_kasm`
--

DROP TABLE IF EXISTS `kioskassginment_kasm`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `kioskassginment_kasm` (
  `kasm_ID` mediumint(9) unsigned NOT NULL AUTO_INCREMENT,
  `kasm_kdevId` mediumint(9) DEFAULT NULL,
  `kasm_AssignmentType` mediumint(9) DEFAULT NULL,
  `kasm_EventId` mediumint(9) DEFAULT '0',
  PRIMARY KEY (`kasm_ID`),
  UNIQUE KEY `kasm_ID` (`kasm_ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `kioskassginment_kasm`
--

LOCK TABLES `kioskassginment_kasm` WRITE;
/*!40000 ALTER TABLE `kioskassginment_kasm` DISABLE KEYS */;
SET autocommit=0;
/*!40000 ALTER TABLE `kioskassginment_kasm` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;

--
-- Table structure for table `kioskdevice_kdev`
--

DROP TABLE IF EXISTS `kioskdevice_kdev`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `kioskdevice_kdev` (
  `kdev_ID` mediumint(9) unsigned NOT NULL AUTO_INCREMENT,
  `kdev_GUIDHash` char(64) COLLATE utf8_unicode_ci DEFAULT NULL,
  `kdev_Name` varchar(50) COLLATE utf8_unicode_ci DEFAULT NULL,
  `kdev_deviceType` mediumint(9) NOT NULL DEFAULT '0',
  `kdev_lastHeartbeat` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `kdev_Accepted` tinyint(1) DEFAULT NULL,
  `kdev_PendingCommands` varchar(50) COLLATE utf8_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`kdev_ID`),
  UNIQUE KEY `kdev_ID` (`kdev_ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `kioskdevice_kdev`
--

LOCK TABLES `kioskdevice_kdev` WRITE;
/*!40000 ALTER TABLE `kioskdevice_kdev` DISABLE KEYS */;
SET autocommit=0;
/*!40000 ALTER TABLE `kioskdevice_kdev` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `list_lst`
--

LOCK TABLES `list_lst` WRITE;
/*!40000 ALTER TABLE `list_lst` DISABLE KEYS */;
SET autocommit=0;
INSERT INTO `list_lst` VALUES (1,1,1,'Member'),(1,2,2,'Regular Attender'),(1,3,3,'Guest'),(1,5,4,'Non-Attender'),(1,4,5,'Non-Attender (staff)'),(2,1,1,'Head of Household'),(2,2,2,'Spouse'),(2,3,3,'Child'),(2,4,4,'Other Relative'),(2,5,5,'Non Relative'),(3,1,1,'Ministry'),(3,2,2,'Team'),(3,3,3,'Bible Study'),(3,4,4,'Sunday School Class'),(4,1,1,'True / False'),(4,2,2,'Date'),(4,3,3,'Text Field (50 char)'),(4,4,4,'Text Field (100 char)'),(4,5,5,'Text Field (Long)'),(4,6,6,'Year'),(4,7,7,'Season'),(4,8,8,'Number'),(4,9,9,'Person from Group'),(4,10,10,'Money'),(4,11,11,'Phone Number'),(4,12,12,'Custom Drop-Down List'),(5,1,1,'bAll'),(5,2,2,'bAdmin'),(5,3,3,'bAddRecords'),(5,4,4,'bEditRecords'),(5,5,5,'bDeleteRecords'),(5,6,6,'bMenuOptions'),(5,7,7,'bManageGroups'),(5,8,8,'bFinance'),(5,9,9,'bNotes'),(5,10,10,'bCommunication'),(5,11,11,'bCanvasser'),(10,1,1,'Teacher'),(10,2,2,'Student'),(11,1,1,'Member'),(12,1,1,'Teacher'),(12,2,2,'Student'),(13,1,1,'Teacher'),(13,2,2,'Student'),(14,1,1,'Teacher'),(14,2,2,'Student'),(15,1,1,'Teacher'),(15,2,2,'Student'),(16,1,1,'Teacher'),(16,2,2,'Student'),(17,1,1,'Teacher'),(17,2,2,'Student'),(18,1,1,'Teacher'),(18,2,2,'Student'),(19,1,1,'Member'),(20,1,1,'Member'),(21,1,1,'Member'),(3,5,5,'Scouts'),(22,1,1,'Member');
/*!40000 ALTER TABLE `list_lst` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;

--
-- Table structure for table `locations`
--

DROP TABLE IF EXISTS `locations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `locations` (
  `location_id` int(11) NOT NULL,
  `location_typeId` int(11) NOT NULL,
  `location_name` varchar(256) COLLATE utf8_unicode_ci NOT NULL,
  `location_address` varchar(45) COLLATE utf8_unicode_ci NOT NULL,
  `location_city` varchar(45) COLLATE utf8_unicode_ci NOT NULL,
  `location_state` varchar(45) COLLATE utf8_unicode_ci NOT NULL,
  `location_zip` varchar(45) COLLATE utf8_unicode_ci NOT NULL,
  `location_country` varchar(45) COLLATE utf8_unicode_ci NOT NULL,
  `location_phone` varchar(45) COLLATE utf8_unicode_ci DEFAULT NULL,
  `location_email` varchar(45) COLLATE utf8_unicode_ci DEFAULT NULL,
  `location_timzezone` varchar(45) COLLATE utf8_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`location_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `locations`
--

LOCK TABLES `locations` WRITE;
/*!40000 ALTER TABLE `locations` DISABLE KEYS */;
SET autocommit=0;
/*!40000 ALTER TABLE `locations` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;

--
-- Table structure for table `menu_links`
--

DROP TABLE IF EXISTS `menu_links`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `menu_links` (
  `linkId` mediumint(8) unsigned NOT NULL AUTO_INCREMENT,
  `linkName` varchar(50) COLLATE utf8_unicode_ci DEFAULT NULL,
  `linkUri` text COLLATE utf8_unicode_ci NOT NULL,
  `linkOrder` int(11) NOT NULL,
  PRIMARY KEY (`linkId`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `menu_links`
--

LOCK TABLES `menu_links` WRITE;
/*!40000 ALTER TABLE `menu_links` DISABLE KEYS */;
SET autocommit=0;
INSERT INTO `menu_links` VALUES (1,'CNN','https://www.cnn.com',0),(2,'Google','https://www.google.com',0);
/*!40000 ALTER TABLE `menu_links` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `multibuy_mb`
--

LOCK TABLES `multibuy_mb` WRITE;
/*!40000 ALTER TABLE `multibuy_mb` DISABLE KEYS */;
SET autocommit=0;
/*!40000 ALTER TABLE `multibuy_mb` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;

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
  `nte_DateEntered` datetime NOT NULL,
  `nte_DateLastEdited` datetime DEFAULT NULL,
  `nte_EnteredBy` mediumint(8) NOT NULL DEFAULT '0',
  `nte_EditedBy` mediumint(8) unsigned NOT NULL DEFAULT '0',
  `nte_Type` varchar(50) COLLATE utf8_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`nte_ID`)
) ENGINE=InnoDB AUTO_INCREMENT=299 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `note_nte`
--

LOCK TABLES `note_nte` WRITE;
/*!40000 ALTER TABLE `note_nte` DISABLE KEYS */;
SET autocommit=0;
INSERT INTO `note_nte` VALUES (1,0,1,0,'Created','2016-01-01 00:00:00','2009-12-25 07:19:06',1,0,'create'),(2,2,0,0,'Created','2016-01-01 00:00:00','2009-12-25 07:19:06',1,0,'create'),(3,3,0,0,'Created','2016-01-01 00:00:00','2006-05-18 08:07:27',1,0,'create'),(4,4,0,0,'Created','2016-01-01 00:00:00','2016-04-03 09:13:02',1,0,'create'),(5,5,0,0,'Created','2016-01-01 00:00:00','2003-06-23 04:32:34',1,0,'create'),(6,0,2,0,'Created','2016-01-01 00:00:00','2009-04-13 01:17:12',1,0,'create'),(7,6,0,0,'Created','2016-01-01 00:00:00','2009-04-13 01:17:12',1,0,'create'),(8,7,0,0,'Created','2016-01-01 00:00:00','2006-08-18 00:45:56',1,0,'create'),(9,8,0,0,'Created','2016-01-01 00:00:00','2004-11-25 05:24:25',1,0,'create'),(10,9,0,0,'Created','2016-01-01 00:00:00','2009-08-19 12:28:31',1,0,'create'),(11,10,0,0,'Created','2016-01-01 00:00:00','2003-02-22 08:03:31',1,0,'create'),(12,11,0,0,'Created','2016-01-01 00:00:00','2011-12-29 16:42:21',1,0,'create'),(13,12,0,0,'Created','2016-01-01 00:00:00','2014-10-07 16:03:06',1,0,'create'),(14,13,0,0,'Created','2016-01-01 00:00:00','2013-02-28 09:48:03',1,0,'create'),(15,0,3,0,'Created','2016-01-01 00:00:00','2007-11-19 10:08:41',1,0,'create'),(16,14,0,0,'Created','2016-01-01 00:00:00','2007-11-19 10:08:41',1,0,'create'),(17,15,0,0,'Created','2016-01-01 00:00:00','2009-12-31 07:48:03',1,0,'create'),(18,16,0,0,'Created','2016-01-01 00:00:00','2011-05-22 19:11:16',1,0,'create'),(19,17,0,0,'Created','2016-01-01 00:00:00','2003-09-03 15:13:09',1,0,'create'),(20,0,4,0,'Created','2016-01-01 00:00:00','2003-10-14 16:05:17',1,0,'create'),(21,18,0,0,'Created','2016-01-01 00:00:00','2003-10-14 16:05:17',1,0,'create'),(22,19,0,0,'Created','2016-01-01 00:00:00','2008-05-09 05:12:00',1,0,'create'),(23,20,0,0,'Created','2016-01-01 00:00:00','2011-02-18 08:54:47',1,0,'create'),(24,21,0,0,'Created','2016-01-01 00:00:00','2009-08-31 21:41:59',1,0,'create'),(25,22,0,0,'Created','2016-01-01 00:00:00','2007-06-22 01:54:41',1,0,'create'),(26,23,0,0,'Created','2016-01-01 00:00:00','2009-08-13 03:54:14',1,0,'create'),(27,24,0,0,'Created','2016-01-01 00:00:00','2016-03-23 12:51:45',1,0,'create'),(28,25,0,0,'Created','2016-01-01 00:00:00','2007-08-08 06:34:24',1,0,'create'),(29,0,5,0,'Created','2016-01-01 00:00:00','2007-09-14 23:32:06',1,0,'create'),(30,26,0,0,'Created','2016-01-01 00:00:00','2007-09-14 23:32:06',1,0,'create'),(31,27,0,0,'Created','2016-01-01 00:00:00','2004-09-21 04:59:36',1,0,'create'),(32,0,6,0,'Created','2016-01-01 00:00:00','2013-07-25 20:18:03',1,0,'create'),(33,28,0,0,'Created','2016-01-01 00:00:00','2013-07-25 20:18:03',1,0,'create'),(34,29,0,0,'Created','2016-01-01 00:00:00','2004-03-04 00:12:40',1,0,'create'),(35,30,0,0,'Created','2016-01-01 00:00:00','2014-09-29 01:36:32',1,0,'create'),(36,31,0,0,'Created','2016-01-01 00:00:00','2007-04-14 04:13:25',1,0,'create'),(37,32,0,0,'Created','2016-01-01 00:00:00','2010-07-15 12:40:49',1,0,'create'),(38,33,0,0,'Created','2016-01-01 00:00:00','2006-07-25 23:21:13',1,0,'create'),(39,0,7,0,'Created','2016-01-01 00:00:00','2011-08-17 04:00:29',1,0,'create'),(40,34,0,0,'Created','2016-01-01 00:00:00','2011-08-17 04:00:29',1,0,'create'),(41,35,0,0,'Created','2016-01-01 00:00:00','2007-12-26 21:13:22',1,0,'create'),(43,36,0,0,'Created','2016-01-01 00:00:00','2015-02-07 16:23:42',1,0,'create'),(44,37,0,0,'Created','2016-01-01 00:00:00','2005-03-04 15:19:32',1,0,'create'),(45,38,0,0,'Created','2016-01-01 00:00:00','2008-10-02 23:33:21',1,0,'create'),(46,39,0,0,'Created','2016-01-01 00:00:00','2003-04-01 17:32:42',1,0,'create'),(47,40,0,0,'Created','2016-01-01 00:00:00','2015-05-27 00:37:53',1,0,'create'),(48,41,0,0,'Created','2016-01-01 00:00:00','2003-12-11 09:28:32',1,0,'create'),(49,42,0,0,'Created','2016-01-01 00:00:00','2012-03-22 08:26:55',1,0,'create'),(50,0,9,0,'Created','2016-01-01 00:00:00','2013-04-20 15:01:05',1,0,'create'),(51,43,0,0,'Created','2016-01-01 00:00:00','2013-04-20 15:01:05',1,0,'create'),(52,44,0,0,'Created','2016-01-01 00:00:00','2012-02-09 10:41:53',1,0,'create'),(56,48,0,0,'Created','2016-01-01 00:00:00','2010-01-07 01:55:34',1,0,'create'),(57,49,0,0,'Created','2016-01-01 00:00:00','2004-06-21 14:40:43',1,0,'create'),(58,0,10,0,'Created','2016-01-01 00:00:00','2004-09-09 18:40:30',1,0,'create'),(59,50,0,0,'Created','2016-01-01 00:00:00','2004-09-09 18:40:30',1,0,'create'),(60,51,0,0,'Created','2016-01-01 00:00:00','2006-11-20 15:07:23',1,0,'create'),(61,0,11,0,'Created','2016-01-01 00:00:00','2006-10-11 03:51:16',1,0,'create'),(62,52,0,0,'Created','2016-01-01 00:00:00','2006-10-11 03:51:16',1,0,'create'),(63,53,0,0,'Created','2016-01-01 00:00:00','2006-06-21 08:18:13',1,0,'create'),(64,54,0,0,'Created','2016-01-01 00:00:00','2006-05-24 17:02:09',1,0,'create'),(65,55,0,0,'Created','2016-01-01 00:00:00','2015-10-04 01:39:08',1,0,'create'),(66,56,0,0,'Created','2016-01-01 00:00:00','2006-07-23 14:13:59',1,0,'create'),(67,0,12,0,'Created','2016-01-01 00:00:00','2014-08-31 04:21:43',1,0,'create'),(68,57,0,0,'Created','2016-01-01 00:00:00','2014-08-31 04:21:43',1,0,'create'),(69,58,0,0,'Created','2016-01-01 00:00:00','2007-11-22 02:36:13',1,0,'create'),(70,0,13,0,'Created','2016-01-01 00:00:00','2007-02-01 16:50:26',1,0,'create'),(71,59,0,0,'Created','2016-01-01 00:00:00','2007-02-01 16:50:26',1,0,'create'),(72,60,0,0,'Created','2016-01-01 00:00:00','2006-11-07 12:19:08',1,0,'create'),(73,61,0,0,'Created','2016-01-01 00:00:00','2009-03-23 09:24:30',1,0,'create'),(74,62,0,0,'Created','2016-01-01 00:00:00','2013-07-10 17:58:37',1,0,'create'),(75,63,0,0,'Created','2016-01-01 00:00:00','2004-10-13 20:53:29',1,0,'create'),(79,0,14,0,'Created','2016-01-01 00:00:00','2013-10-15 09:25:25',1,0,'create'),(80,67,0,0,'Created','2016-01-01 00:00:00','2013-10-15 09:25:25',1,0,'create'),(81,68,0,0,'Created','2016-01-01 00:00:00','2003-09-29 17:56:26',1,0,'create'),(82,69,0,0,'Created','2016-01-01 00:00:00','2010-10-03 22:37:50',1,0,'create'),(84,70,0,0,'Created','2016-01-01 00:00:00','2003-04-25 18:30:46',1,0,'create'),(85,71,0,0,'Created','2016-01-01 00:00:00','2013-07-20 08:52:02',1,0,'create'),(86,72,0,0,'Created','2016-01-01 00:00:00','2002-10-01 07:06:30',1,0,'create'),(87,73,0,0,'Created','2016-01-01 00:00:00','2006-10-24 06:38:46',1,0,'create'),(88,74,0,0,'Created','2016-01-01 00:00:00','2005-01-21 16:03:19',1,0,'create'),(89,75,0,0,'Created','2016-01-01 00:00:00','2008-02-08 09:12:55',1,0,'create'),(90,0,16,0,'Created','2016-01-01 00:00:00','2016-03-01 14:19:32',1,0,'create'),(91,76,0,0,'Created','2016-01-01 00:00:00','2016-03-01 14:19:32',1,0,'create'),(92,77,0,0,'Created','2016-01-01 00:00:00','2013-07-06 04:09:48',1,0,'create'),(93,0,17,0,'Created','2016-01-01 00:00:00','2014-09-26 00:09:54',1,0,'create'),(94,78,0,0,'Created','2016-01-01 00:00:00','2014-09-26 00:09:54',1,0,'create'),(95,79,0,0,'Created','2016-01-01 00:00:00','2007-06-20 13:29:12',1,0,'create'),(96,80,0,0,'Created','2016-01-01 00:00:00','2015-12-26 15:02:22',1,0,'create'),(97,81,0,0,'Created','2016-01-01 00:00:00','2008-02-28 11:35:15',1,0,'create'),(98,82,0,0,'Created','2016-01-01 00:00:00','2007-04-24 18:10:43',1,0,'create'),(99,83,0,0,'Created','2016-01-01 00:00:00','2011-10-07 17:53:37',1,0,'create'),(100,0,18,0,'Created','2016-01-01 00:00:00','2002-04-09 05:31:36',1,0,'create'),(101,84,0,0,'Created','2016-01-01 00:00:00','2002-04-09 05:31:36',1,0,'create'),(102,85,0,0,'Created','2016-01-01 00:00:00','2015-03-05 07:54:37',1,0,'create'),(103,86,0,0,'Created','2016-01-01 00:00:00','2003-02-10 12:19:20',1,0,'create'),(104,87,0,0,'Created','2016-01-01 00:00:00','2008-11-13 08:27:32',1,0,'create'),(105,88,0,0,'Created','2016-01-01 00:00:00','2008-06-30 07:18:09',1,0,'create'),(106,89,0,0,'Created','2016-01-01 00:00:00','2005-04-12 20:56:36',1,0,'create'),(107,90,0,0,'Created','2016-01-01 00:00:00','2007-01-20 19:02:31',1,0,'create'),(108,91,0,0,'Created','2016-01-01 00:00:00','2010-12-30 01:21:15',1,0,'create'),(109,0,19,0,'Created','2016-01-01 00:00:00','2014-11-23 09:17:25',1,0,'create'),(110,92,0,0,'Created','2016-01-01 00:00:00','2014-11-23 09:17:25',1,0,'create'),(111,93,0,0,'Created','2016-01-01 00:00:00','2011-07-16 02:59:16',1,0,'create'),(112,94,0,0,'Created','2016-01-01 00:00:00','2006-05-25 19:29:47',1,0,'create'),(113,95,0,0,'Created','2016-01-01 00:00:00','2003-07-08 08:25:48',1,0,'create'),(114,96,0,0,'Created','2016-01-01 00:00:00','2006-04-08 10:42:12',1,0,'create'),(115,97,0,0,'Created','2016-01-01 00:00:00','2010-01-28 17:10:25',1,0,'create'),(116,98,0,0,'Created','2016-01-01 00:00:00','2007-02-16 17:31:50',1,0,'create'),(117,0,20,0,'Created','2016-01-01 00:00:00','2014-05-10 06:07:19',1,0,'create'),(118,99,0,0,'Created','2016-01-01 00:00:00','2014-05-10 06:07:19',1,0,'create'),(119,100,0,0,'Created','2016-01-01 00:00:00','2004-09-29 01:37:47',1,0,'create'),(121,102,0,0,'Created','2016-01-01 00:00:00','2005-03-07 21:32:47',1,0,'create'),(122,103,0,0,'Created','2016-01-01 00:00:00','2014-05-16 11:35:23',1,0,'create'),(123,26,0,0,'Updated via Family','2016-11-19 15:23:31',NULL,1,0,'edit'),(124,27,0,0,'Updated via Family','2016-11-19 15:23:31',NULL,1,0,'edit'),(125,0,5,0,'Updated','2016-11-19 15:23:31',NULL,1,0,'edit'),(126,61,0,0,'Updated','2016-11-19 15:25:34',NULL,1,0,'edit'),(127,62,0,0,'Updated','2016-11-19 15:25:41',NULL,1,0,'edit'),(128,63,0,0,'Updated','2016-11-19 15:26:00',NULL,1,0,'edit'),(129,63,0,0,'Updated','2016-11-19 15:27:02',NULL,1,0,'edit'),(130,60,0,0,'Updated','2016-11-19 15:27:13',NULL,1,0,'edit'),(131,69,0,0,'Updated','2016-11-19 15:38:00',NULL,1,0,'edit'),(132,69,0,0,'Profile Image Deleted','2016-11-19 15:38:03',NULL,1,0,'photo'),(133,68,0,0,'Updated','2016-11-19 15:38:31',NULL,1,0,'edit'),(134,67,0,0,'Updated','2016-11-19 15:38:39',NULL,1,0,'edit'),(135,4,0,0,'Updated','2016-11-19 15:39:19',NULL,1,0,'edit'),(136,5,0,0,'Updated','2016-11-19 15:39:30',NULL,1,0,'edit'),(137,80,0,0,'Updated','2016-11-19 15:39:51',NULL,1,0,'edit'),(138,81,0,0,'Updated','2016-11-19 15:39:54',NULL,1,0,'edit'),(139,82,0,0,'Updated','2016-11-19 15:39:58',NULL,1,0,'edit'),(140,83,0,0,'Updated','2016-11-19 15:40:22',NULL,1,0,'edit'),(141,31,0,0,'Updated','2016-11-19 15:40:40',NULL,1,0,'edit'),(142,33,0,0,'Updated','2016-11-19 15:40:43',NULL,1,0,'edit'),(143,29,0,0,'Updated','2016-11-19 15:40:58',NULL,1,0,'edit'),(144,32,0,0,'Updated','2016-11-19 15:41:02',NULL,1,0,'edit'),(145,30,0,0,'Updated','2016-11-19 15:41:11',NULL,1,0,'edit'),(146,51,0,0,'Updated','2016-11-19 15:41:44',NULL,1,0,'edit'),(147,13,0,0,'Updated','2016-11-19 15:42:01',NULL,1,0,'edit'),(148,12,0,0,'Updated','2016-11-19 15:42:04',NULL,1,0,'edit'),(149,11,0,0,'Updated','2016-11-19 15:42:07',NULL,1,0,'edit'),(150,10,0,0,'Updated','2016-11-19 15:42:10',NULL,1,0,'edit'),(151,9,0,0,'Updated','2016-11-19 15:42:14',NULL,1,0,'edit'),(152,8,0,0,'Updated','2016-11-19 15:42:20',NULL,1,0,'edit'),(153,94,0,0,'Updated','2016-11-19 15:42:48',NULL,1,0,'edit'),(154,95,0,0,'Updated','2016-11-19 15:42:57',NULL,1,0,'edit'),(155,96,0,0,'Updated','2016-11-19 15:43:06',NULL,1,0,'edit'),(156,97,0,0,'Updated','2016-11-19 15:43:10',NULL,1,0,'edit'),(157,53,0,0,'Updated','2016-11-19 15:43:22',NULL,1,0,'edit'),(158,27,0,0,'Updated','2016-11-19 15:43:47',NULL,1,0,'edit'),(159,35,0,0,'Updated','2016-11-19 15:44:07',NULL,1,0,'edit'),(160,35,0,0,'Profile Image Deleted','2016-11-19 15:44:11',NULL,1,0,'photo'),(161,59,0,0,'Updated via Family','2016-11-19 15:46:56',NULL,1,0,'edit'),(162,63,0,0,'Updated via Family','2016-11-19 15:46:56',NULL,1,0,'edit'),(163,60,0,0,'Updated via Family','2016-11-19 15:46:56',NULL,1,0,'edit'),(164,61,0,0,'Updated via Family','2016-11-19 15:46:56',NULL,1,0,'edit'),(165,62,0,0,'Updated via Family','2016-11-19 15:46:56',NULL,1,0,'edit'),(166,0,13,0,'Updated','2016-11-19 15:46:56',NULL,1,0,'edit'),(167,59,0,0,'Updated via Family','2016-11-19 15:49:36',NULL,1,0,'edit'),(168,63,0,0,'Updated via Family','2016-11-19 15:49:36',NULL,1,0,'edit'),(169,60,0,0,'Updated via Family','2016-11-19 15:49:36',NULL,1,0,'edit'),(170,61,0,0,'Updated via Family','2016-11-19 15:49:36',NULL,1,0,'edit'),(171,62,0,0,'Updated via Family','2016-11-19 15:49:36',NULL,1,0,'edit'),(172,0,13,0,'Updated','2016-11-19 15:49:36',NULL,1,0,'edit'),(173,68,0,0,'Updated via Family','2016-11-19 15:49:58',NULL,1,0,'edit'),(174,67,0,0,'Updated via Family','2016-11-19 15:49:58',NULL,1,0,'edit'),(175,69,0,0,'Updated via Family','2016-11-19 15:49:58',NULL,1,0,'edit'),(176,0,14,0,'Updated','2016-11-19 15:49:58',NULL,1,0,'edit'),(177,99,0,0,'Updated via Family','2016-11-19 15:50:25',NULL,1,0,'edit'),(178,100,0,0,'Updated via Family','2016-11-19 15:50:25',NULL,1,0,'edit'),(179,102,0,0,'Updated via Family','2016-11-19 15:50:25',NULL,1,0,'edit'),(180,103,0,0,'Updated via Family','2016-11-19 15:50:25',NULL,1,0,'edit'),(181,0,20,0,'Updated','2016-11-19 15:50:25',NULL,1,0,'edit'),(182,2,0,0,'Updated via Family','2016-11-19 15:50:57',NULL,1,0,'edit'),(183,3,0,0,'Updated via Family','2016-11-19 15:50:57',NULL,1,0,'edit'),(184,4,0,0,'Updated via Family','2016-11-19 15:50:57',NULL,1,0,'edit'),(185,5,0,0,'Updated via Family','2016-11-19 15:50:57',NULL,1,0,'edit'),(186,0,1,0,'Updated','2016-11-19 15:50:57',NULL,1,0,'edit'),(187,78,0,0,'Updated via Family','2016-11-19 15:51:35',NULL,1,0,'edit'),(188,79,0,0,'Updated via Family','2016-11-19 15:51:35',NULL,1,0,'edit'),(189,80,0,0,'Updated via Family','2016-11-19 15:51:35',NULL,1,0,'edit'),(190,81,0,0,'Updated via Family','2016-11-19 15:51:35',NULL,1,0,'edit'),(191,82,0,0,'Updated via Family','2016-11-19 15:51:35',NULL,1,0,'edit'),(192,0,17,0,'Updated','2016-11-19 15:51:35',NULL,1,0,'edit'),(193,43,0,0,'Updated via Family','2016-11-19 15:52:17',NULL,1,0,'edit'),(194,44,0,0,'Updated via Family','2016-11-19 15:52:17',NULL,1,0,'edit'),(195,48,0,0,'Updated via Family','2016-11-19 15:52:17',NULL,1,0,'edit'),(196,49,0,0,'Updated via Family','2016-11-19 15:52:17',NULL,1,0,'edit'),(197,0,9,0,'Updated','2016-11-19 15:52:17',NULL,1,0,'edit'),(198,28,0,0,'Updated via Family','2016-11-19 15:52:44',NULL,1,0,'edit'),(199,30,0,0,'Updated via Family','2016-11-19 15:52:44',NULL,1,0,'edit'),(200,0,6,0,'Updated','2016-11-19 15:52:44',NULL,1,0,'edit'),(201,50,0,0,'Updated via Family','2016-11-19 15:52:50',NULL,1,0,'edit'),(202,0,10,0,'Updated','2016-11-19 15:52:50',NULL,1,0,'edit'),(203,6,0,0,'Updated via Family','2016-11-19 15:53:10',NULL,1,0,'edit'),(204,7,0,0,'Updated via Family','2016-11-19 15:53:10',NULL,1,0,'edit'),(205,8,0,0,'Updated via Family','2016-11-19 15:53:10',NULL,1,0,'edit'),(206,9,0,0,'Updated via Family','2016-11-19 15:53:10',NULL,1,0,'edit'),(207,10,0,0,'Updated via Family','2016-11-19 15:53:10',NULL,1,0,'edit'),(208,11,0,0,'Updated via Family','2016-11-19 15:53:10',NULL,1,0,'edit'),(209,12,0,0,'Updated via Family','2016-11-19 15:53:10',NULL,1,0,'edit'),(210,13,0,0,'Updated via Family','2016-11-19 15:53:10',NULL,1,0,'edit'),(211,0,2,0,'Updated','2016-11-19 15:53:10',NULL,1,0,'edit'),(212,92,0,0,'Updated via Family','2016-11-19 15:53:40',NULL,1,0,'edit'),(213,93,0,0,'Updated via Family','2016-11-19 15:53:40',NULL,1,0,'edit'),(214,98,0,0,'Updated via Family','2016-11-19 15:53:40',NULL,1,0,'edit'),(215,0,19,0,'Updated','2016-11-19 15:53:40',NULL,1,0,'edit'),(216,76,0,0,'Updated via Family','2016-11-19 15:53:48',NULL,1,0,'edit'),(217,77,0,0,'Updated via Family','2016-11-19 15:53:48',NULL,1,0,'edit'),(218,0,16,0,'Updated','2016-11-19 15:53:48',NULL,1,0,'edit'),(219,14,0,0,'Updated via Family','2016-11-19 15:54:09',NULL,1,0,'edit'),(220,15,0,0,'Updated via Family','2016-11-19 15:54:09',NULL,1,0,'edit'),(221,16,0,0,'Updated via Family','2016-11-19 15:54:09',NULL,1,0,'edit'),(222,17,0,0,'Updated via Family','2016-11-19 15:54:09',NULL,1,0,'edit'),(223,0,3,0,'Updated','2016-11-19 15:54:09',NULL,1,0,'edit'),(224,52,0,0,'Updated via Family','2016-11-19 15:54:22',NULL,1,0,'edit'),(225,54,0,0,'Updated via Family','2016-11-19 15:54:22',NULL,1,0,'edit'),(226,55,0,0,'Updated via Family','2016-11-19 15:54:22',NULL,1,0,'edit'),(227,56,0,0,'Updated via Family','2016-11-19 15:54:22',NULL,1,0,'edit'),(228,0,11,0,'Updated','2016-11-19 15:54:22',NULL,1,0,'edit'),(229,57,0,0,'Updated via Family','2016-11-19 15:54:30',NULL,1,0,'edit'),(230,58,0,0,'Updated via Family','2016-11-19 15:54:30',NULL,1,0,'edit'),(231,0,12,0,'Updated','2016-11-19 15:54:30',NULL,1,0,'edit'),(232,18,0,0,'Updated via Family','2016-11-19 15:55:11',NULL,1,0,'edit'),(233,19,0,0,'Updated via Family','2016-11-19 15:55:11',NULL,1,0,'edit'),(234,20,0,0,'Updated via Family','2016-11-19 15:55:11',NULL,1,0,'edit'),(235,21,0,0,'Updated via Family','2016-11-19 15:55:11',NULL,1,0,'edit'),(236,22,0,0,'Updated via Family','2016-11-19 15:55:11',NULL,1,0,'edit'),(237,23,0,0,'Updated via Family','2016-11-19 15:55:11',NULL,1,0,'edit'),(238,24,0,0,'Updated via Family','2016-11-19 15:55:11',NULL,1,0,'edit'),(239,25,0,0,'Updated via Family','2016-11-19 15:55:11',NULL,1,0,'edit'),(240,0,4,0,'Updated','2016-11-19 15:55:11',NULL,1,0,'edit'),(241,84,0,0,'Updated via Family','2016-11-19 15:56:04',NULL,1,0,'edit'),(242,85,0,0,'Updated via Family','2016-11-19 15:56:04',NULL,1,0,'edit'),(243,86,0,0,'Updated via Family','2016-11-19 15:56:04',NULL,1,0,'edit'),(244,87,0,0,'Updated via Family','2016-11-19 15:56:04',NULL,1,0,'edit'),(245,88,0,0,'Updated via Family','2016-11-19 15:56:04',NULL,1,0,'edit'),(246,89,0,0,'Updated via Family','2016-11-19 15:56:04',NULL,1,0,'edit'),(247,90,0,0,'Updated via Family','2016-11-19 15:56:04',NULL,1,0,'edit'),(248,91,0,0,'Updated via Family','2016-11-19 15:56:04',NULL,1,0,'edit'),(249,0,18,0,'Updated','2016-11-19 15:56:04',NULL,1,0,'edit'),(250,26,0,0,'Updated via Family','2016-11-19 15:56:49',NULL,1,0,'edit'),(251,0,5,0,'Updated','2016-11-19 15:56:49',NULL,1,0,'edit'),(252,34,0,0,'Updated via Family','2016-11-19 15:56:55',NULL,1,0,'edit'),(253,0,7,0,'Updated','2016-11-19 15:56:55',NULL,1,0,'edit'),(254,3,0,0,'Updated','2016-11-19 16:07:39',NULL,1,0,'edit'),(255,84,0,0,'Updated via Family','2016-11-19 16:42:37',NULL,1,0,'edit'),(256,85,0,0,'Updated via Family','2016-11-19 16:42:37',NULL,1,0,'edit'),(257,87,0,0,'Updated via Family','2016-11-19 16:42:37',NULL,1,0,'edit'),(258,88,0,0,'Updated via Family','2016-11-19 16:42:37',NULL,1,0,'edit'),(259,89,0,0,'Updated via Family','2016-11-19 16:42:37',NULL,1,0,'edit'),(260,90,0,0,'Updated via Family','2016-11-19 16:42:37',NULL,1,0,'edit'),(261,91,0,0,'Updated via Family','2016-11-19 16:42:37',NULL,1,0,'edit'),(262,86,0,0,'Updated via Family','2016-11-19 16:42:37',NULL,1,0,'edit'),(263,0,18,0,'Updated','2016-11-19 16:42:37',NULL,1,0,'edit'),(264,84,0,0,'Updated via Family','2016-11-19 16:43:48',NULL,1,0,'edit'),(265,85,0,0,'Updated via Family','2016-11-19 16:43:48',NULL,1,0,'edit'),(266,87,0,0,'Updated via Family','2016-11-19 16:43:48',NULL,1,0,'edit'),(267,88,0,0,'Updated via Family','2016-11-19 16:43:48',NULL,1,0,'edit'),(268,89,0,0,'Updated via Family','2016-11-19 16:43:48',NULL,1,0,'edit'),(269,90,0,0,'Updated via Family','2016-11-19 16:43:48',NULL,1,0,'edit'),(270,91,0,0,'Updated via Family','2016-11-19 16:43:48',NULL,1,0,'edit'),(271,86,0,0,'Updated via Family','2016-11-19 16:43:48',NULL,1,0,'edit'),(272,0,18,0,'Updated','2016-11-19 16:43:48',NULL,1,0,'edit'),(273,0,21,0,'Created','0000-00-00 00:00:00','2017-04-15 17:19:26',-1,0,'create'),(274,104,0,0,'Created','2017-04-15 17:20:21',NULL,-1,0,'create'),(275,105,0,0,'Created','2017-04-15 17:20:21',NULL,-1,0,'create'),(276,106,0,0,'Created','2017-04-15 17:20:21',NULL,-1,0,'create'),(277,107,0,0,'Created','2017-04-15 17:20:21',NULL,-1,0,'create'),(278,14,0,0,'Updated via Family','2017-04-15 17:21:40',NULL,1,0,'edit'),(279,15,0,0,'Updated via Family','2017-04-15 17:21:40',NULL,1,0,'edit'),(280,16,0,0,'Updated via Family','2017-04-15 17:21:40',NULL,1,0,'edit'),(281,17,0,0,'Updated via Family','2017-04-15 17:21:40',NULL,1,0,'edit'),(282,0,3,0,'Updated','2017-04-15 17:21:40',NULL,1,0,'edit'),(283,26,0,0,'Updated via Family','2017-04-15 17:22:04',NULL,1,0,'edit'),(284,0,5,0,'Updated','2017-04-15 17:22:04',NULL,1,0,'edit'),(285,50,0,0,'Updated via Family','2017-04-15 17:22:19',NULL,1,0,'edit'),(286,0,10,0,'Updated','2017-04-15 17:22:19',NULL,1,0,'edit'),(287,0,4,0,'Updated','0000-00-00 00:00:00','2016-11-19 15:55:11',1,0,'edit'),(288,0,4,0,'Deactivated the Family','2017-04-15 17:34:50',NULL,1,0,'edit'),(289,3,0,0,'system user password reset','2017-12-18 00:43:09',NULL,1,0,'user'),(290,3,0,0,'system user password changed by admin','2017-12-18 00:43:33',NULL,1,0,'user'),(291,3,0,0,'system user password reset','2017-12-18 00:45:44',NULL,1,0,'user'),(292,3,0,0,'system user password reset','2017-12-18 00:47:43',NULL,1,0,'user'),(293,96,0,0,'Deleted from group: ','2017-12-23 14:46:01',NULL,1,0,'group'),(294,96,0,0,'Deleted from group: Class 4-5','2017-12-23 14:46:59',NULL,1,0,'group'),(295,96,0,0,'Added to group: High School Class','2017-12-23 14:48:34',NULL,1,0,'group'),(296,3,0,0,'system user password changed by admin','2017-12-23 16:49:29',NULL,1,0,'user'),(297,35,0,0,'Updated','2018-01-01 19:25:55',NULL,1,0,'edit'),(298,1,0,0,NULL,'2018-02-19 17:11:42',NULL,1,0,'user');
/*!40000 ALTER TABLE `note_nte` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `paddlenum_pn`
--

LOCK TABLES `paddlenum_pn` WRITE;
/*!40000 ALTER TABLE `paddlenum_pn` DISABLE KEYS */;
SET autocommit=0;
/*!40000 ALTER TABLE `paddlenum_pn` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `person2group2role_p2g2r`
--

LOCK TABLES `person2group2role_p2g2r` WRITE;
/*!40000 ALTER TABLE `person2group2role_p2g2r` DISABLE KEYS */;
SET autocommit=0;
INSERT INTO `person2group2role_p2g2r` VALUES (2,9,1),(4,1,1),(5,1,2),(7,6,1),(8,1,2),(9,1,2),(10,2,2),(11,3,2),(12,5,2),(13,2,2),(16,5,2),(21,6,2),(22,4,2),(24,4,2),(25,5,2),(26,7,1),(30,3,2),(60,7,1),(63,1,1),(63,8,1),(67,2,1),(79,5,1),(79,9,1),(80,2,2),(80,8,1),(82,2,2),(83,9,1),(93,6,1),(95,8,1),(96,5,2),(100,4,1);
/*!40000 ALTER TABLE `person2group2role_p2g2r` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `person2volunteeropp_p2vo`
--

LOCK TABLES `person2volunteeropp_p2vo` WRITE;
/*!40000 ALTER TABLE `person2volunteeropp_p2vo` DISABLE KEYS */;
SET autocommit=0;
/*!40000 ALTER TABLE `person2volunteeropp_p2vo` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;

--
-- Table structure for table `person_custom`
--

DROP TABLE IF EXISTS `person_custom`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `person_custom` (
  `per_ID` mediumint(9) NOT NULL DEFAULT '0',
  PRIMARY KEY (`per_ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `person_custom`
--

LOCK TABLES `person_custom` WRITE;
/*!40000 ALTER TABLE `person_custom` DISABLE KEYS */;
SET autocommit=0;
/*!40000 ALTER TABLE `person_custom` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;

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
  `type_ID` tinyint(4) NOT NULL DEFAULT '0',
  PRIMARY KEY (`custom_Field`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `person_custom_master`
--

LOCK TABLES `person_custom_master` WRITE;
/*!40000 ALTER TABLE `person_custom_master` DISABLE KEYS */;
SET autocommit=0;
/*!40000 ALTER TABLE `person_custom_master` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;

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
  `per_DateEntered` datetime NOT NULL,
  `per_EnteredBy` smallint(5) NOT NULL DEFAULT '0',
  `per_EditedBy` smallint(5) unsigned DEFAULT '0',
  `per_FriendDate` date DEFAULT NULL,
  `per_Flags` mediumint(9) NOT NULL DEFAULT '0',
  `per_FacebookID` bigint(20) unsigned DEFAULT NULL,
  `per_Twitter` varchar(50) COLLATE utf8_unicode_ci DEFAULT NULL,
  `per_LinkedIn` varchar(50) COLLATE utf8_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`per_ID`),
  KEY `per_ID` (`per_ID`)
) ENGINE=InnoDB AUTO_INCREMENT=108 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `person_per`
--

LOCK TABLES `person_per` WRITE;
/*!40000 ALTER TABLE `person_per` DISABLE KEYS */;
SET autocommit=0;
INSERT INTO `person_per` VALUES (1,NULL,'Church',NULL,'Admin',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,0,0,'0000',NULL,0,0,0,0,NULL,NULL,'2004-08-25 18:00:00',1,0,NULL,0,NULL,NULL,NULL),(2,'Mr','Mathew','','Campbell','','3259 Daisy Dr',NULL,'Denton','AR',NULL,'USA','(728)-139-0768',NULL,'(003)-025-4087','mathew.campbell@example.com',NULL,6,15,'1970',NULL,1,1,1,1,NULL,NULL,'2009-12-25 07:19:06',1,0,NULL,0,NULL,NULL,NULL),(3,'Mr','Tony','','Campbell','','9889 Lone Wolf Trail','','Worcester','ME','','USA','(221) 294-5282','','(820) 203-5704','tony.wade@example.com','',3,3,'1945',NULL,1,0,2,0,0,'2016-11-19 16:07:39','2006-05-18 08:07:27',1,1,NULL,0,NULL,NULL,NULL),(4,'Mr','Darren','','Campbell','','8142 Locust Rd','','Tucson','WV','','USA','(336) 819-5290','','(800) 502-3720','darren.freeman@example.com','',7,23,'1910',NULL,1,4,5,1,0,'2016-11-19 15:39:19','2016-04-03 09:13:02',1,1,NULL,0,NULL,NULL,NULL),(5,'Mr','Albert','','Campbell','','2560 Edwards Rd','','Garland','MT','','USA','(389) 860-7800','','(591) 749-3007','albert.garcia@example.com','',9,9,'2013',NULL,1,3,1,1,0,'2016-11-19 15:39:30','2003-06-23 04:32:34',1,1,NULL,0,NULL,NULL,NULL),(6,'Mrs','Constance','','Hart','','4878 Valley View Ln',NULL,'Grand Rapids','ND',NULL,'USA','(042)-989-4488',NULL,'(385)-141-5437','constance.hart@example.com',NULL,7,8,'1966',NULL,2,1,1,2,NULL,NULL,'2009-04-13 01:17:12',1,0,NULL,0,NULL,NULL,NULL),(7,'Mr','Marion','','Hart','','9211 Railroad St',NULL,'Rockford','ME',NULL,'USA','(178)-657-2640',NULL,'(167)-162-6079','marion.sutton@example.com',NULL,11,11,'1960',NULL,1,2,1,2,NULL,NULL,'2006-08-18 00:45:56',1,0,NULL,0,NULL,NULL,NULL),(8,'Ms','Herminia','','Hart','','9302 Mcclellan Rd','','Anna','AR','','USA','(628) 153-9939','','(568) 461-7599','herminia.bennett@example.com','',11,1,'2016',NULL,2,3,1,2,0,'2016-11-19 15:42:20','2004-11-25 05:24:25',1,1,NULL,0,NULL,NULL,NULL),(9,'Mrs','Jean','','Hart','','1952 E Sandy Lake Rd','','Grand Prairie','VA','','USA','(079) 999-6246','','(604) 581-1182','jean.williams@example.com','',6,7,'2015',NULL,2,3,1,2,0,'2016-11-19 15:42:14','2009-08-19 12:28:31',1,1,NULL,0,NULL,NULL,NULL),(10,'Mr','Tom','','Hart','','3822 Ranchview Dr','','Yakima','MT','','USA','(527) 157-1824','','(295) 083-9014','tom.gardner@example.com','',6,29,'2014',NULL,1,3,1,2,0,'2016-11-19 15:42:10','2003-02-22 08:03:31',1,1,NULL,0,NULL,NULL,NULL),(11,'Miss','Isabella','','Hart','','1062 W Campbell Ave','','Orange','AZ','','USA','(812) 239-4176','','(370) 444-2204','isabella.murphy@example.com','',11,1,'2013',NULL,2,3,1,2,0,'2016-11-19 15:42:07','2011-12-29 16:42:21',1,1,NULL,0,NULL,NULL,NULL),(12,'Miss','Hannah','','Hart','','8339 W Campbell Ave','','Cincinnati','MA','','USA','(807) 426-5975','','(438) 063-5382','hannah.dean@example.com','',11,10,'2012',NULL,2,3,1,2,0,'2016-11-19 15:42:04','2014-10-07 16:03:06',1,1,NULL,0,NULL,NULL,NULL),(13,'Mr','Ivan','','Hart','','9113 Wheeler Ridge Dr','','Glendale','FL','','USA','(087) 032-9002','','(722) 034-5627','ivan.hayes@example.com','',2,8,'2010',NULL,1,3,1,2,0,'2016-11-19 15:42:01','2013-02-28 09:48:03',1,1,NULL,0,NULL,NULL,NULL),(14,'Mr','Nathan','','Lewis','','2379 Northaven Rd',NULL,'Detroit','WV',NULL,'USA','(609)-441-0871',NULL,'(289)-209-6037','nathan.lewis@example.com',NULL,9,5,'1947',NULL,1,1,1,3,NULL,NULL,'2007-11-19 10:08:41',1,0,NULL,0,NULL,NULL,NULL),(15,'Miss','Vivan','','Lewis','','4179 Nowlin Rd',NULL,'Burkburnett','CO',NULL,'USA','(705)-700-7379',NULL,'(673)-131-2063','vivan.stone@example.com',NULL,1,7,'1953',NULL,2,2,2,3,NULL,NULL,'2009-12-31 07:48:03',1,0,NULL,0,NULL,NULL,NULL),(16,'Miss','Alicia','','Lewis','','9671 Crockett St',NULL,'Antioch','AK',NULL,'USA','(207)-652-5270',NULL,'(101)-986-5384','alicia.wood@example.com',NULL,5,8,'2001',NULL,2,3,2,3,NULL,NULL,'2011-05-22 19:11:16',1,0,NULL,0,NULL,NULL,NULL),(17,'Mr','Edwin','','Lewis','','8088 Westheimer Rd',NULL,'New Haven','OK',NULL,'USA','(282)-503-9925',NULL,'(564)-714-4633','edwin.adams@example.com',NULL,4,3,'1960',NULL,1,4,5,3,NULL,NULL,'2003-09-03 15:13:09',1,0,NULL,0,NULL,NULL,NULL),(18,'Miss','Kathryn','','Ray','','4212 Parker Rd',NULL,'Chesapeake','WI',NULL,'USA','(220)-345-1335',NULL,'(698)-371-0398','kathryn.ray@example.com',NULL,1,1,'1961',NULL,2,1,1,4,NULL,NULL,'2003-10-14 16:05:17',1,0,NULL,0,NULL,NULL,NULL),(19,'Ms','Laurie','','Ray','','8886 Edwards Rd',NULL,'Burkburnett','OK',NULL,'USA','(729)-498-1504',NULL,'(325)-373-6885','laurie.spencer@example.com',NULL,12,4,'1945',NULL,2,2,1,4,NULL,NULL,'2008-05-09 05:12:00',1,0,NULL,0,NULL,NULL,NULL),(20,'Mr','Ruben','','Ray','','4029 Marsh Ln',NULL,'Laredo','PA',NULL,'USA','(025)-226-8939',NULL,'(860)-989-5496','ruben.robertson@example.com',NULL,7,31,'1947',NULL,1,4,2,4,NULL,NULL,'2011-02-18 08:54:47',1,0,NULL,0,NULL,NULL,NULL),(21,'Mr','Anthony','','Ray','','1384 Mockingbird Hill',NULL,'Hartford','TX',NULL,'USA','(338)-134-5037',NULL,'(824)-719-7975','anthony.romero@example.com',NULL,8,11,'2001',NULL,1,3,2,4,NULL,NULL,'2009-08-31 21:41:59',1,0,NULL,0,NULL,NULL,NULL),(22,'Ms','Tiffany','','Ray','','5628 College St',NULL,'Houston','WI',NULL,'USA','(345)-423-2511',NULL,'(290)-521-0022','tiffany.mitchelle@example.com',NULL,9,11,'2003',NULL,2,3,2,4,NULL,NULL,'2007-06-22 01:54:41',1,0,NULL,0,NULL,NULL,NULL),(23,'Miss','Leah','','Ray','','3962 Preston Rd',NULL,'Seattle','IN',NULL,'USA','(062)-298-8987',NULL,'(460)-918-7940','leah.romero@example.com',NULL,2,28,'2008',NULL,2,3,2,4,NULL,NULL,'2009-08-13 03:54:14',1,0,NULL,0,NULL,NULL,NULL),(24,'Mr','Clyde','','Ray','','6667 Elgin St',NULL,'Flint','ID',NULL,'USA','(033)-156-6960',NULL,'(880)-267-2598','clyde.prescott@example.com',NULL,8,11,'2007',NULL,1,3,2,4,NULL,NULL,'2016-03-23 12:51:45',1,0,NULL,0,NULL,NULL,NULL),(25,'Mrs','Peyton','','Ray','','2168 Daisy Dr',NULL,'Ontario','CT',NULL,'USA','(872)-942-4541',NULL,'(575)-806-0327','peyton.caldwell@example.com',NULL,12,18,'2005',NULL,2,3,2,4,NULL,NULL,'2007-08-08 06:34:24',1,0,NULL,0,NULL,NULL,NULL),(26,'Mr','Paul','','Smith','','5572 Robinson Rd',NULL,'Santa Clarita','KY',NULL,'USA','(886)-863-1106',NULL,'(619)-224-1159','paul.robertson@example.com',NULL,11,21,'1983',NULL,1,1,1,5,NULL,NULL,'2007-09-14 23:32:06',1,0,NULL,0,NULL,NULL,NULL),(27,'Mr','Isaac','','Murry','','4358 W Pecan St','','Columbus','IN','','USA','(094) 366-3908','','(776) 335-5061','isaac.medina@example.com','',7,18,'1967',NULL,1,0,0,0,0,'2016-11-19 15:43:47','2004-09-21 04:59:36',1,1,NULL,0,NULL,NULL,NULL),(28,'Mr','Rafael','','Dixon','','6730 Mockingbird Hill',NULL,'Roanoke','IL',NULL,'USA','(449)-349-7865',NULL,'(868)-958-9892','rafael.dixon@example.com',NULL,12,4,'1990',NULL,1,1,2,6,NULL,NULL,'2013-07-25 20:18:03',1,0,NULL,0,NULL,NULL,NULL),(29,'Mr','Flenn','','Dixon','','1240 Preston Rd','','Cary','MS','','USA','(003) 336-4322','','(053) 745-5201','flenn.watts@example.com','',10,28,'1959',NULL,1,0,0,0,0,'2016-11-19 15:40:58','2004-03-04 00:12:40',1,1,NULL,0,NULL,NULL,NULL),(30,'Miss','Kenzi','','Dixon','','8077 Mcclellan Rd','','Lancaster','PA','','USA','(644) 129-3577','','(930) 426-1283','kenzi.fields@example.com','',4,30,'2012',NULL,2,3,2,6,0,'2016-11-19 15:41:11','2014-09-29 01:36:32',1,1,NULL,0,NULL,NULL,NULL),(31,'Miss','Paula','','Dixon','','5240 Brown Terrace','','Orange','TN','','USA','(306) 612-2729','','(669) 399-3249','paula.rice@example.com','',9,10,'1973',NULL,2,0,0,0,0,'2016-11-19 15:40:40','2007-04-14 04:13:25',1,1,NULL,0,NULL,NULL,NULL),(32,'Mr','Chad','','Dixon','','3902 Robinson Rd','','Fort Worth','AZ','','USA','(933) 231-0313','','(525) 406-0888','chad.fuller@example.com','',2,20,'1992',NULL,1,0,0,0,0,'2016-11-19 15:41:02','2010-07-15 12:40:49',1,1,NULL,0,NULL,NULL,NULL),(33,'Ms','Riley','','Dixon','','2739 E Sandy Lake Rd','','Frederick','AZ','','USA','(601) 891-3110','','(283) 918-7088','riley.reed@example.com','',9,23,'1961',NULL,2,0,0,0,0,'2016-11-19 15:40:43','2006-07-25 23:21:13',1,1,NULL,0,NULL,NULL,NULL),(34,'Mr','Alvin','','Stewart','','7045 Wycliff Ave',NULL,'Gainesville','SD',NULL,'USA','(813)-837-2427',NULL,'(919)-556-7534','alvin.stewart@example.com',NULL,12,16,'1974',NULL,1,1,1,7,NULL,NULL,'2011-08-17 04:00:29',1,0,NULL,0,NULL,NULL,NULL),(35,'Mr','Shane','','Stewart','','9986 Blossom Hill Rd','','Hayward','KY','','USA','(988) 219-6753','','(271) 473-8298','tony.wade@example.com','',1,16,'1951',NULL,1,0,0,0,0,'2018-01-01 19:25:55','2007-12-26 21:13:22',1,1,NULL,0,0,'',''),(36,'Miss','Kathryn',NULL,'Robertson',NULL,'7218 E Pecan St',NULL,'Gilbert','NE',NULL,'USA','(109)-946-0677',NULL,'(493)-248-4574','kathryn.robertson@example.com',NULL,1,14,'1977',NULL,2,1,0,0,NULL,NULL,'2015-02-07 16:23:42',1,0,NULL,0,NULL,NULL,NULL),(37,'Mr','Wayne',NULL,'Robertson',NULL,'5100 Bollinger Rd',NULL,'Bridgeport','WV',NULL,'USA','(584)-942-4752',NULL,'(947)-378-9079','wayne.jacobs@example.com',NULL,3,10,'1982',NULL,1,2,0,0,NULL,NULL,'2005-03-04 15:19:32',1,0,NULL,0,NULL,NULL,NULL),(38,'Miss','Mae',NULL,'Robertson',NULL,'5205 Saddle Dr',NULL,'Fort Wayne','PA',NULL,'USA','(572)-934-2319',NULL,'(625)-704-9068','mae.robinson@example.com',NULL,8,19,'1979',NULL,2,3,0,0,NULL,NULL,'2008-10-02 23:33:21',1,0,NULL,0,NULL,NULL,NULL),(39,'Mr','Soham',NULL,'Robertson',NULL,'3619 Daisy Dr',NULL,'San Jose','AL',NULL,'USA','(789)-386-8063',NULL,'(314)-889-8596','soham.vargas@example.com',NULL,10,3,'1961',NULL,1,3,0,0,NULL,NULL,'2003-04-01 17:32:42',1,0,NULL,0,NULL,NULL,NULL),(40,'Mr','Austin',NULL,'Robertson',NULL,'5722 Adams St',NULL,'Rochester','NJ',NULL,'USA','(728)-300-2942',NULL,'(359)-620-0341','austin.chapman@example.com',NULL,7,13,'1977',NULL,1,3,0,0,NULL,NULL,'2015-05-27 00:37:53',1,0,NULL,0,NULL,NULL,NULL),(41,'Mrs','Stella',NULL,'Robertson',NULL,'5862 Brown Terrace',NULL,'Centennial','AK',NULL,'USA','(751)-040-3371',NULL,'(979)-231-1273','stella.steward@example.com',NULL,2,15,'1956',NULL,2,3,0,0,NULL,NULL,'2003-12-11 09:28:32',1,0,NULL,0,NULL,NULL,NULL),(42,'Mr','Clinton',NULL,'Robertson',NULL,'1916 Lone Wolf Trail',NULL,'Glendale','OR',NULL,'USA','(253)-317-5937',NULL,'(954)-007-6834','clinton.black@example.com',NULL,10,20,'1969',NULL,1,3,0,0,NULL,NULL,'2012-03-22 08:26:55',1,0,NULL,0,NULL,NULL,NULL),(43,'Ms','Natalie','','Diaz','','1158 Harrison Ct',NULL,'Hialeah','IA',NULL,'USA','(613)-399-6088',NULL,'(129)-829-3141','natalie.diaz@example.com',NULL,5,26,'1970',NULL,2,1,3,9,NULL,NULL,'2013-04-20 15:01:05',1,0,NULL,0,NULL,NULL,NULL),(44,'Mrs','Rhonda','','Diaz','','7320 Cackson St',NULL,'Murrieta','NH',NULL,'USA','(169)-598-1721',NULL,'(839)-859-9851','rhonda.mcdonalid@example.com',NULL,4,4,'1973',NULL,2,2,3,9,NULL,NULL,'2012-02-09 10:41:53',1,0,NULL,0,NULL,NULL,NULL),(48,'Mr','Kurt','','Diaz','','5324 Adams St',NULL,'Richardson','WI',NULL,'USA','(284)-936-1476',NULL,'(220)-137-4630','kurt.hernandez@example.com',NULL,6,20,'2005',NULL,1,3,3,9,NULL,NULL,'2010-01-07 01:55:34',1,0,NULL,0,NULL,NULL,NULL),(49,'Ms','Lorraine','','Diaz','','4771 Crockett St',NULL,'Las Cruces','MA',NULL,'USA','(344)-896-8256',NULL,'(825)-077-7472','lorraine.craig@example.com',NULL,7,26,'2007',NULL,2,3,3,9,NULL,NULL,'2004-06-21 14:40:43',1,0,NULL,0,NULL,NULL,NULL),(50,'Mrs','Sherri','','Gordon','','1255 Brown Terrace',NULL,'Louisville','MI',NULL,'USA','(215)-006-0420',NULL,'(730)-313-8457','sherri.gordon@example.com',NULL,12,18,'1972',NULL,2,1,2,10,NULL,NULL,'2004-09-09 18:40:30',1,0,NULL,0,NULL,NULL,NULL),(51,'Ms','Rebecca','','Gordon','','6049 Lone Wolf Trail','','Fayetteville','VA','','USA','(808) 292-7083','','(134) 378-9433','rebecca.walker@example.com','',11,28,'1960',NULL,2,0,0,0,0,'2016-11-19 15:41:44','2006-11-20 15:07:23',1,1,NULL,0,NULL,NULL,NULL),(52,'Mr','Vernon','','Newman','','5427 Stevens Creek Blvd',NULL,'Orlando','MN',NULL,'USA','(792)-676-7007',NULL,'(427)-110-3793','vernon.newman@example.com',NULL,8,24,'1961',NULL,1,1,0,11,NULL,NULL,'2006-10-11 03:51:16',1,0,NULL,0,NULL,NULL,NULL),(53,'Mr','George','','Newman','','9425 Lovers Ln','','Madison','LA','','USA','(321) 940-9019','','(782) 311-7625','george.powell@example.com','',5,5,'1988',NULL,1,0,0,0,0,'2016-11-19 15:43:22','2006-06-21 08:18:13',1,1,NULL,0,NULL,NULL,NULL),(54,'Mr','Connor','','Newman','','7304 Parker Rd',NULL,'Forney','CA',NULL,'USA','(838)-504-2399',NULL,'(354)-932-3933','connor.silva@example.com',NULL,5,12,'1977',NULL,1,3,0,11,NULL,NULL,'2006-05-24 17:02:09',1,0,NULL,0,NULL,NULL,NULL),(55,'Mrs','Marie','','Newman','','3717 Lovers Ln',NULL,'Hialeah','NE',NULL,'USA','(833)-140-7523',NULL,'(740)-971-1815','marie.king@example.com',NULL,10,20,'1950',NULL,2,4,0,11,NULL,NULL,'2015-10-04 01:39:08',1,0,NULL,0,NULL,NULL,NULL),(56,'Mr','Norman','','Newman','','8751 E North St',NULL,'Norwalk','TX',NULL,'USA','(185)-313-3302',NULL,'(936)-644-4251','norman.rice@example.com',NULL,3,9,'1970',NULL,1,4,0,11,NULL,NULL,'2006-07-23 14:13:59',1,0,NULL,0,NULL,NULL,NULL),(57,'Mr','Jon','','Olson','','1272 Shady Ln Dr',NULL,'Toledo','MT',NULL,'USA','(698)-235-3995',NULL,'(242)-098-1494','jon.olson@example.com',NULL,1,20,'1981',NULL,1,1,1,12,NULL,NULL,'2014-08-31 04:21:43',1,0,NULL,0,NULL,NULL,NULL),(58,'Ms','Sandra','','Olson','','6590 Ranchview Dr',NULL,'Mcallen','RI',NULL,'USA','(071)-579-9864',NULL,'(654)-049-3879','sandra.vasquez@example.com',NULL,12,28,'1992',NULL,2,2,1,12,NULL,NULL,'2007-11-22 02:36:13',1,0,NULL,0,NULL,NULL,NULL),(59,'Mr','Franklin','','Beck','','6381 Valwood Pkwy',NULL,'Buffalo','ME',NULL,'USA','(237)-926-6342',NULL,'(742)-524-0575','franklin.beck@example.com',NULL,7,30,'1974',NULL,1,1,0,13,NULL,NULL,'2007-02-01 16:50:26',1,0,NULL,0,NULL,NULL,NULL),(60,'Mr','Joel','','Beck','','2334 Daisy Dr','','Yakima','UT','','USA','(206) 919-0303','','(748) 409-3185','joel.murray@example.com','',6,26,'2011',NULL,1,3,0,13,0,'2016-11-19 15:27:13','2006-11-07 12:19:08',1,1,NULL,0,NULL,NULL,NULL),(61,'Miss','Stella','','Beck','','9568 Mockingbird Hill','','Inglewood','IL','','USA','(830) 356-0875','','(970) 626-0902','stella.soto@example.com','',7,18,'2010',NULL,2,3,0,13,0,'2016-11-19 15:25:34','2009-03-23 09:24:30',1,1,NULL,0,NULL,NULL,NULL),(62,'Ms','Emma','','Beck','','8890 Hunters Creek Dr','','Vallejo','MT','','USA','(623) 770-5283','','(447) 069-6505','emma.rice@example.com','',11,30,'2015',NULL,2,3,0,13,0,'2016-11-19 15:25:41','2013-07-10 17:58:37',1,1,NULL,0,NULL,NULL,NULL),(63,'Miss','Julie','','Beck','','9648 Frances Ct','','Amarillo','OK','','USA','(523) 696-1294','','(107) 329-0388','julie.gregory@example.com','',8,21,'1982',NULL,2,2,0,13,0,'2016-11-19 15:27:02','2004-10-13 20:53:29',1,1,NULL,0,NULL,NULL,NULL),(67,'Miss','Brianna','','Berry','','1931 Edwards Rd','','Riverside','PA','','USA','(174) 272-0341','','(310) 207-4173','brianna.berry@example.com','',10,17,'1977',NULL,2,2,1,14,0,'2016-11-19 15:38:39','2013-10-15 09:25:25',1,1,NULL,0,NULL,NULL,NULL),(68,'Mr','Salvador','','Berry','','1487 W Sherman Dr','','Portland','NJ','','USA','(004) 784-0725','','(696) 118-7224','salvador.robertson@example.com','',11,9,'1944',NULL,1,1,1,14,0,'2016-11-19 15:38:31','2003-09-29 17:56:26',1,1,NULL,0,NULL,NULL,NULL),(69,'Mr','Salvador','','Berry','','7928 Country Club Rd','','Lincoln','FL','','USA','(873) 186-8043','','(063) 874-1910','salvador.steward@example.com','',11,1,'2011',NULL,1,3,1,14,0,'2016-11-19 15:38:00','2010-10-03 22:37:50',1,1,NULL,0,NULL,NULL,NULL),(70,'Ms','Edith',NULL,'Simmons',NULL,'3395 N Stelling Rd',NULL,'Murrieta','KY',NULL,'USA','(228)-690-1613',NULL,'(602)-815-9891','edith.simmons@example.com',NULL,3,25,'1977',NULL,2,1,0,0,NULL,NULL,'2003-04-25 18:30:46',1,0,NULL,0,NULL,NULL,NULL),(71,'Mr','Dave',NULL,'Simmons',NULL,'7581 Central St',NULL,'Roseville','RI',NULL,'USA','(196)-952-9025',NULL,'(850)-773-0796','dave.bradley@example.com',NULL,2,17,'1965',NULL,1,2,0,0,NULL,NULL,'2013-07-20 08:52:02',1,0,NULL,0,NULL,NULL,NULL),(72,'Mr','Jonathan',NULL,'Simmons',NULL,'4096 Elgin St',NULL,'Saint Paul','OR',NULL,'USA','(267)-764-5383',NULL,'(359)-187-3057','jonathan.holland@example.com',NULL,3,10,'1983',NULL,1,3,0,0,NULL,NULL,'2002-10-01 07:06:30',1,0,NULL,0,NULL,NULL,NULL),(73,'Mr','Allen',NULL,'Simmons',NULL,'5097 Sunset St',NULL,'Belen','CO',NULL,'USA','(084)-776-1439',NULL,'(549)-426-1692','allen.howard@example.com',NULL,12,18,'1983',NULL,1,3,0,0,NULL,NULL,'2006-10-24 06:38:46',1,0,NULL,0,NULL,NULL,NULL),(74,'Mr','Micheal',NULL,'Simmons',NULL,'9703 Harrison Ct',NULL,'Alexandria','MD',NULL,'USA','(025)-038-3863',NULL,'(542)-730-3571','micheal.gordon@example.com',NULL,7,27,'1987',NULL,1,3,0,0,NULL,NULL,'2005-01-21 16:03:19',1,0,NULL,0,NULL,NULL,NULL),(75,'Mr','Kelly',NULL,'Simmons',NULL,'7765 Marsh Ln',NULL,'Oxnard','UT',NULL,'USA','(637)-815-9511',NULL,'(747)-456-7524','kelly.foster@example.com',NULL,12,9,'1960',NULL,1,3,0,0,NULL,NULL,'2008-02-08 09:12:55',1,0,NULL,0,NULL,NULL,NULL),(76,'Mr','Leroy','','Larson','','3866 Edwards Rd',NULL,'Inglewood','CO',NULL,'USA','(663)-858-8880',NULL,'(731)-811-2661','leroy.larson@example.com',NULL,3,17,'1993',NULL,1,1,2,16,NULL,NULL,'2016-03-01 14:19:32',1,0,NULL,0,NULL,NULL,NULL),(77,'Ms','Natalie','','Larson','','5739 Hunters Creek Dr',NULL,'Charleston','NM',NULL,'USA','(209)-144-2421',NULL,'(265)-630-7859','natalie.lynch@example.com',NULL,5,23,'1977',NULL,2,2,2,16,NULL,NULL,'2013-07-06 04:09:48',1,0,NULL,0,NULL,NULL,NULL),(78,'Mr','Norman','','Cooper','','1782 Daisy Dr',NULL,'Oxnard','GA',NULL,'USA','(718)-878-3276',NULL,'(912)-550-0265','norman.cooper@example.com',NULL,2,15,'1967',NULL,1,1,1,17,NULL,NULL,'2014-09-26 00:09:54',1,0,NULL,0,NULL,NULL,NULL),(79,'Mr','Bradley','','Cooper','','9496 Cackson St',NULL,'El Monte','ME',NULL,'USA','(020)-152-0784',NULL,'(129)-121-8642','bradley.spencer@example.com',NULL,10,28,'1990',NULL,1,2,5,17,NULL,NULL,'2007-06-20 13:29:12',1,0,NULL,0,NULL,NULL,NULL),(80,'Miss','Judy','','Cooper','','5515 Lakeshore Rd','','Forney','AZ','','USA','(538) 341-4356','','(784) 554-0404','judy.douglas@example.com','',1,3,'2010',NULL,2,3,1,17,0,'2016-11-19 15:39:51','2015-12-26 15:02:22',1,1,NULL,0,NULL,NULL,NULL),(81,'Mrs','Isobel','','Cooper','','9849 Fairview St','','Edgewood','MD','','USA','(375) 417-6951','','(086) 565-1921','isobel.jimenez@example.com','',11,5,'2012',NULL,2,3,1,17,0,'2016-11-19 15:39:54','2008-02-28 11:35:15',1,1,NULL,0,NULL,NULL,NULL),(82,'Mr','Jesse','','Cooper','','4450 Brown Terrace','','Aurora','FL','','USA','(036) 874-8797','','(725) 279-2801','jesse.morales@example.com','',2,20,'2014',NULL,1,3,1,17,0,'2016-11-19 15:39:58','2007-04-24 18:10:43',1,1,NULL,0,NULL,NULL,NULL),(83,'Ms','Terry','','Cooper','','6299 Daisy Dr','','St. Louis','CT','','USA','(948) 039-8020','','(800) 640-6871','terry.kim@example.com','',9,20,'1982',NULL,2,0,0,0,0,'2016-11-19 15:40:22','2011-10-07 17:53:37',1,1,NULL,0,NULL,NULL,NULL),(84,'Mr','Randall','','Riley','','1403 Avondale Ave',NULL,'Scottsdale','ID',NULL,'USA','(055)-343-0760',NULL,'(217)-027-5703','randall.riley@example.com',NULL,9,10,'1982',NULL,1,1,1,18,NULL,NULL,'2002-04-09 05:31:36',1,0,NULL,0,NULL,NULL,NULL),(85,'Mr','Bernard','','Riley','','6129 Mockingbird Ln',NULL,'Portland','SC',NULL,'USA','(753)-827-4801',NULL,'(367)-872-9300','bernard.shaw@example.com',NULL,5,31,'1988',NULL,1,2,1,18,NULL,NULL,'2015-03-05 07:54:37',1,0,NULL,0,NULL,NULL,NULL),(86,'Ms','Charlene','','Riley','','2764 Mcgowen St',NULL,'Van Alstyne','ME',NULL,'USA','(351)-644-7769',NULL,'(272)-375-0289','charlene.holmes@example.com',NULL,2,24,'1994',NULL,2,4,2,18,NULL,NULL,'2003-02-10 12:19:20',1,0,NULL,0,NULL,NULL,NULL),(87,'Mr','Scott','','Riley','','6514 Lovers Ln',NULL,'Greeley','SC',NULL,'USA','(225)-752-3695',NULL,'(811)-926-7831','scott.curtis@example.com',NULL,9,12,'2011',NULL,1,3,2,18,NULL,NULL,'2008-11-13 08:27:32',1,0,NULL,0,NULL,NULL,NULL),(88,'Mr','Cecil','','Riley','','3843 Locust Rd',NULL,'Modesto','MS',NULL,'USA','(195)-545-9986',NULL,'(997)-692-8061','cecil.brooks@example.com',NULL,12,29,'2012',NULL,1,3,2,18,NULL,NULL,'2008-06-30 07:18:09',1,0,NULL,0,NULL,NULL,NULL),(89,'Mr','Charlie','','Riley','','7456 Walnut Hill Ln',NULL,'Berkeley','AR',NULL,'USA','(683)-044-6359',NULL,'(243)-373-2589','charlie.steward@example.com',NULL,11,28,'2013',NULL,1,3,2,18,NULL,NULL,'2005-04-12 20:56:36',1,0,NULL,0,NULL,NULL,NULL),(90,'Mr','Tomothy','','Riley','','1151 First Street',NULL,'Santa Ana','KS',NULL,'USA','(428)-265-4577',NULL,'(314)-894-6418','tomothy.morris@example.com',NULL,5,20,'2015',NULL,1,3,2,18,NULL,NULL,'2007-01-20 19:02:31',1,0,NULL,0,NULL,NULL,NULL),(91,'Mrs','Lydia','','Riley','','5887 Daisy Dr',NULL,'Sacramento','MS',NULL,'USA','(979)-417-1072',NULL,'(082)-882-0459','lydia.beck@example.com',NULL,10,18,'2016',NULL,2,3,2,18,NULL,NULL,'2010-12-30 01:21:15',1,0,NULL,0,NULL,NULL,NULL),(92,'Mr','Bruce','','Kennedy','','9481 Wycliff Ave',NULL,'Long Beach','KY',NULL,'USA','(306)-408-4342',NULL,'(414)-142-2127','bruce.kennedy@example.com',NULL,3,14,'1968',NULL,1,1,1,19,NULL,NULL,'2014-11-23 09:17:25',1,0,NULL,0,NULL,NULL,NULL),(93,'Ms','Katie','','Kennedy','','8164 W Sherman Dr',NULL,'Miramar','MA',NULL,'USA','(130)-036-3270',NULL,'(993)-725-1216','katie.hoffman@example.com',NULL,8,20,'1959',NULL,2,2,1,19,NULL,NULL,'2011-07-16 02:59:16',1,0,NULL,0,NULL,NULL,NULL),(94,'Mr','Rick','','Kennedy','','7581 Country Club Rd','','Vancouver','MN','','USA','(045) 695-1038','','(534) 739-9684','rick.simpson@example.com','',7,25,'1983',NULL,1,0,0,0,0,'2016-11-19 15:42:48','2006-05-25 19:29:47',1,1,NULL,0,NULL,NULL,NULL),(95,'Mrs','Judith','','Kennedy','','2874 Paddock Way','','Anaheim','IA','','USA','(773) 685-8434','','(384) 824-9114','judith.matthews@example.com','',8,1,'1978',NULL,0,0,0,0,0,'2016-11-19 15:42:57','2003-07-08 08:25:48',1,1,NULL,0,NULL,NULL,NULL),(96,'Mr','Curtis','','Kennedy','','8606 Cherry St','','Bernalillo','GA','','USA','(461) 623-8151','','(361) 282-0616','curtis.ross@example.com','',8,5,'1976',NULL,1,0,0,0,0,'2016-11-19 15:43:06','2006-04-08 10:42:12',1,1,NULL,0,NULL,NULL,NULL),(97,'Mr','Erik','','Kennedy','','9277 Pockrus Page Rd','','Detroit','ND','','USA','(867) 583-3910','','(526) 231-1258','erik.mitchell@example.com','',9,25,'1964',NULL,1,0,0,0,0,'2016-11-19 15:43:10','2010-01-28 17:10:25',1,1,NULL,0,NULL,NULL,NULL),(98,'Mrs','Carrie','','Kennedy','','7713 First Street',NULL,'Hayward','FL',NULL,'USA','(060)-528-8391',NULL,'(465)-239-8937','carrie.knight@example.com',NULL,4,19,'1960',NULL,2,5,5,19,NULL,NULL,'2007-02-16 17:31:50',1,0,NULL,0,NULL,NULL,NULL),(99,'Miss','Amanda','','Black','','4307 Avondale Ave',NULL,'Shiloh','CT',NULL,'USA','(828)-463-5829',NULL,'(207)-736-7509','amanda.black@example.com',NULL,5,18,'1966',NULL,2,1,2,20,NULL,NULL,'2014-05-10 06:07:19',1,0,NULL,0,NULL,NULL,NULL),(100,'Miss','Lena','','Black','','4858 Taylor St',NULL,'Manchester','UT',NULL,'USA','(747)-292-0200',NULL,'(006)-199-3045','lena.walker@example.com',NULL,12,24,'1971',NULL,2,2,2,20,NULL,NULL,'2004-09-29 01:37:47',1,0,NULL,0,NULL,NULL,NULL),(102,'Ms','Samantha','','Black','','9227 Plum St',NULL,'Coppell','MA',NULL,'USA','(374)-404-6562',NULL,'(060)-500-5393','samantha.duncan@example.com',NULL,8,10,'2010',NULL,2,3,2,20,NULL,NULL,'2005-03-07 21:32:47',1,0,NULL,0,NULL,NULL,NULL),(103,'Mrs','Serenity','','Black','','1122 W Belt Line Rd',NULL,'Norman','NY',NULL,'USA','(795)-160-6735',NULL,'(993)-000-0313','serenity.banks@example.com',NULL,12,17,'2013',NULL,2,3,2,20,NULL,NULL,'2014-05-16 11:35:23',1,0,NULL,0,NULL,NULL,NULL),(104,NULL,'Mark',NULL,'Smith',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'(206) 555-1234','mark.smith@example.com',NULL,3,31,'1980',NULL,1,1,0,21,NULL,NULL,'2017-04-15 17:20:21',-1,0,NULL,0,NULL,NULL,NULL),(105,NULL,'Mary',NULL,'Smith',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'','',NULL,3,30,'1985',NULL,1,2,0,21,NULL,NULL,'2017-04-15 17:20:21',-1,0,NULL,1,NULL,NULL,NULL),(106,NULL,'Sam',NULL,'Smith',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'','',NULL,0,0,NULL,NULL,1,3,0,21,NULL,NULL,'2017-04-15 17:20:21',-1,0,NULL,0,NULL,NULL,NULL),(107,NULL,'Tony',NULL,'Smith',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'','',NULL,0,0,NULL,NULL,1,3,0,21,NULL,NULL,'2017-04-15 17:20:21',-1,0,NULL,0,NULL,NULL,NULL);
/*!40000 ALTER TABLE `person_per` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;

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
  `plg_DateLastEdited` date NOT NULL DEFAULT '2016-01-01',
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
) ENGINE=InnoDB AUTO_INCREMENT=20 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `pledge_plg`
--

LOCK TABLES `pledge_plg` WRITE;
/*!40000 ALTER TABLE `pledge_plg` DISABLE KEYS */;
SET autocommit=0;
INSERT INTO `pledge_plg` VALUES (1,13,22,'2018-03-04',30.00,'Once','CASH','','2018-03-04',1,'Payment',1,1,NULL,NULL,'',0,0,0,0.00,'cash|0|13|1|2018-03-04'),(2,13,22,'2018-03-04',20.00,'Once','CASH','','2018-03-04',1,'Payment',2,1,NULL,NULL,'',0,0,0,0.00,'cash|0|13|1|2018-03-04'),(3,13,22,'2018-03-04',100.00,'Once','CASH','','2018-03-04',1,'Payment',3,1,NULL,NULL,'',0,0,0,0.00,'cash|0|13|1|2018-03-04'),(4,14,22,'2018-03-04',100.00,'Once','CHECK','','2018-03-04',1,'Payment',1,1,127,NULL,'',0,0,0,0.00,'127|0|14|1|2018-03-04'),(5,20,22,'2018-03-04',500.00,'Once','CHECK','','2018-03-04',1,'Payment',1,2,100,NULL,'',0,0,0,0.00,'100|0|20|1|2018-03-04'),(6,20,22,'2018-03-04',100.00,'Once','CHECK','','2018-03-04',1,'Payment',2,2,100,NULL,'',0,0,0,0.00,'100|0|20|1|2018-03-04'),(7,20,22,'2018-03-04',100.00,'Once','CHECK','','2018-03-04',1,'Payment',3,2,100,NULL,'',0,0,0,0.00,'100|0|20|1|2018-03-04'),(8,20,22,'2018-03-04',25.00,'Once','CHECK','','2018-03-04',1,'Payment',1,3,5532,NULL,'',0,0,0,0.00,'5532|0|20|1|2018-03-04'),(9,20,22,'2018-03-04',25.00,'Once','CHECK','','2018-03-04',1,'Payment',2,3,5532,NULL,'',0,0,0,0.00,'5532|0|20|1|2018-03-04'),(10,20,22,'2018-03-04',25.00,'Once','CHECK','','2018-03-04',1,'Payment',3,3,5532,NULL,'',0,0,0,0.00,'5532|0|20|1|2018-03-04'),(11,1,22,'2018-03-04',300.00,'Once','CHECK','','2018-03-04',1,'Payment',1,3,773,NULL,'',0,0,0,0.00,'773|0|1|1|2018-03-04'),(12,1,22,'2018-03-04',26.00,'Once','CHECK','','2018-03-04',1,'Payment',2,3,773,NULL,'',0,0,0,0.00,'773|0|1|1|2018-03-04'),(13,1,22,'2018-03-04',20.00,'Once','CHECK','','2018-03-04',1,'Payment',3,3,773,NULL,'',0,0,0,0.00,'773|0|1|1|2018-03-04'),(14,9,22,'2018-03-04',50.00,'Once','CASH','','2018-03-04',1,'Payment',1,4,NULL,NULL,'',0,0,0,0.00,'cash|0|9|1|2018-03-04'),(15,6,22,'2018-03-04',100.00,'Once','CASH','','2018-03-04',1,'Payment',1,5,NULL,NULL,'',0,0,0,0.00,'cash|0|6|1|2018-03-04'),(16,6,22,'2018-03-04',20.00,'Once','CASH','','2018-03-04',1,'Payment',3,5,NULL,NULL,'',0,0,0,0.00,'cash|0|6|1|2018-03-04'),(17,10,22,'2018-03-04',90.00,'Once','CASH','','2018-03-04',1,'Payment',1,5,NULL,NULL,'',0,0,0,0.00,'cash|0|10|1|2018-03-04'),(18,10,22,'2018-03-04',140.00,'Once','CASH','','2018-03-04',1,'Payment',2,5,NULL,NULL,'',0,0,0,0.00,'cash|0|10|1|2018-03-04'),(19,10,22,'2018-03-04',95.00,'Once','CASH','','2018-03-04',1,'Payment',3,5,NULL,NULL,'',0,0,0,0.00,'cash|0|10|1|2018-03-04');
/*!40000 ALTER TABLE `pledge_plg` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;

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
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `property_pro`
--

LOCK TABLES `property_pro` WRITE;
/*!40000 ALTER TABLE `property_pro` DISABLE KEYS */;
SET autocommit=0;
INSERT INTO `property_pro` VALUES (1,'p',1,'Disabled','has a disability.','What is the nature of the disability?'),(2,'f',2,'Single Parent','is a single-parent household.',''),(3,'g',3,'Youth','is youth-oriented.',''),(4,'g',3,'Scouts','','');
/*!40000 ALTER TABLE `property_pro` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;

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
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `propertytype_prt`
--

LOCK TABLES `propertytype_prt` WRITE;
/*!40000 ALTER TABLE `propertytype_prt` DISABLE KEYS */;
SET autocommit=0;
INSERT INTO `propertytype_prt` VALUES (1,'p','General','General Person Properties'),(2,'f','General','General Family Properties'),(3,'g','General','General Group Properties');
/*!40000 ALTER TABLE `propertytype_prt` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;

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
) ENGINE=InnoDB AUTO_INCREMENT=201 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `query_qry`
--

LOCK TABLES `query_qry` WRITE;
/*!40000 ALTER TABLE `query_qry` DISABLE KEYS */;
SET autocommit=0;
INSERT INTO `query_qry` VALUES (1,'SELECT CONCAT(\'<a href=FamilyView.php?FamilyID=\',fam_ID,\'>\',fam_Name,\'</a>\') AS \'Family Name\'   FROM family_fam Where fam_WorkPhone != \"\"','Family Member Count','Returns each family and the total number of people assigned to them.',0),(3,'SELECT CONCAT(\'<a href=FamilyView.php?FamilyID=\',fam_ID,\'>\',fam_Name,\'</a>\') AS \'Family Name\', COUNT(*) AS \'No.\'\nFROM person_per\nINNER JOIN family_fam\nON fam_ID = per_fam_ID\nGROUP BY per_fam_ID\nORDER BY \'No.\' DESC','Family Member Count','Returns each family and the total number of people assigned to them.',0),(4,'SELECT per_ID as AddToCart,CONCAT(\'<a\r\nhref=PersonView.php?PersonID=\',per_ID,\'>\',per_FirstName,\'\r\n\',per_LastName,\'</a>\') AS Name,\r\nCONCAT(per_BirthMonth,\'/\',per_BirthDay,\'/\',per_BirthYear) AS \'Birth Date\',\r\nDATE_FORMAT(FROM_DAYS(TO_DAYS(NOW())-TO_DAYS(CONCAT(per_BirthYear,\'-\',per_BirthMonth,\'-\',per_BirthDay))),\'%Y\')+0 AS  \'Age\'\r\nFROM person_per\r\nWHERE\r\nDATE_ADD(CONCAT(per_BirthYear,\'-\',per_BirthMonth,\'-\',per_BirthDay),INTERVAL\r\n~min~ YEAR) <= CURDATE()\r\nAND\r\nDATE_ADD(CONCAT(per_BirthYear,\'-\',per_BirthMonth,\'-\',per_BirthDay),INTERVAL\r\n(~max~ + 1) YEAR) >= CURDATE()','Person by Age','Returns any person records with ages between two given ages.',1),(6,'SELECT COUNT(per_ID) AS Total FROM person_per WHERE per_Gender = ~gender~','Total By Gender','Total of records matching a given gender.',0),(7,'SELECT per_ID as AddToCart, CONCAT(per_FirstName,\' \',per_LastName) AS Name FROM person_per WHERE per_fmr_ID = ~role~ AND per_Gender = ~gender~','Person by Role and Gender','Selects person records with the family role and gender specified.',1),(9,'SELECT \r\nper_ID as AddToCart, \r\nCONCAT(per_FirstName,\' \',per_LastName) AS Name, \r\nCONCAT(r2p_Value,\' \') AS Value\r\nFROM person_per,record2property_r2p\r\nWHERE per_ID = r2p_record_ID\r\nAND r2p_pro_ID = ~PropertyID~\r\nORDER BY per_LastName','Person by Property','Returns person records which are assigned the given property.',1),(15,'SELECT per_ID as AddToCart, CONCAT(\'<a href=PersonView.php?PersonID=\',per_ID,\'>\',COALESCE(`per_FirstName`,\'\'),\' \',COALESCE(`per_MiddleName`,\'\'),\' \',COALESCE(`per_LastName`,\'\'),\'</a>\') AS Name, fam_City as City, fam_State as State, fam_Zip as ZIP, per_HomePhone as HomePhone, per_Email as Email, per_WorkEmail as WorkEmail FROM person_per RIGHT JOIN family_fam ON family_fam.fam_id = person_per.per_fam_id WHERE ~searchwhat~ LIKE \'%~searchstring~%\'','Advanced Search','Search by any part of Name, City, State, Zip, Home Phone, Email, or Work Email.',1),(18,'SELECT per_ID as AddToCart, per_BirthDay as Day, CONCAT(per_FirstName,\' \',per_LastName) AS Name FROM person_per WHERE per_cls_ID=~percls~ AND per_BirthMonth=~birthmonth~ ORDER BY per_BirthDay','Birthdays','People with birthdays in a particular month',0),(21,'SELECT per_ID as AddToCart, CONCAT(\'<a href=PersonView.php?PersonID=\',per_ID,\'>\',per_FirstName,\' \',per_LastName,\'</a>\') AS Name FROM person_per LEFT JOIN person2group2role_p2g2r ON per_id = p2g2r_per_ID WHERE p2g2r_grp_ID=~group~ ORDER BY per_LastName','Registered students','Find Registered students',1),(22,'SELECT per_ID as AddToCart, DAYOFMONTH(per_MembershipDate) as Day, per_MembershipDate AS DATE, CONCAT(per_FirstName,\' \',per_LastName) AS Name FROM person_per WHERE per_cls_ID=1 AND MONTH(per_MembershipDate)=~membermonth~ ORDER BY per_MembershipDate','Membership anniversaries','Members who joined in a particular month',0),(23,'SELECT usr_per_ID as AddToCart, CONCAT(a.per_FirstName,\' \',a.per_LastName) AS Name FROM user_usr LEFT JOIN person_per a ON per_ID=usr_per_ID ORDER BY per_LastName','Select database users','People who are registered as database users',0),(24,'SELECT per_ID as AddToCart, CONCAT(\'<a href=PersonView.php?PersonID=\',per_ID,\'>\',per_FirstName,\' \',per_LastName,\'</a>\') AS Name FROM person_per WHERE per_cls_id =1','Select all members','People who are members',0),(25,'SELECT per_ID as AddToCart, CONCAT(\'<a href=PersonView.php?PersonID=\',per_ID,\'>\',per_FirstName,\' \',per_LastName,\'</a>\') AS Name FROM person_per LEFT JOIN person2volunteeropp_p2vo ON per_id = p2vo_per_ID WHERE p2vo_vol_ID = ~volopp~ ORDER BY per_LastName','Volunteers','Find volunteers for a particular opportunity',1),(26,'SELECT per_ID as AddToCart, CONCAT(per_FirstName,\' \',per_LastName) AS Name FROM person_per WHERE DATE_SUB(NOW(),INTERVAL ~friendmonths~ MONTH)<per_FriendDate ORDER BY per_MembershipDate','Recent friends','Friends who signed up in previous months',0),(27,'SELECT per_ID as AddToCart, CONCAT(per_FirstName,\' \',per_LastName) AS Name FROM person_per inner join family_fam on per_fam_ID=fam_ID where per_fmr_ID<>3 AND fam_OkToCanvass=\"TRUE\" ORDER BY fam_Zip','Families to Canvass','People in families that are ok to canvass.',0),(28,'SELECT fam_Name, a.plg_amount as PlgFY1, b.plg_amount as PlgFY2 from family_fam left join pledge_plg a on a.plg_famID = fam_ID and a.plg_FYID=~fyid1~ and a.plg_PledgeOrPayment=\'Pledge\' left join pledge_plg b on b.plg_famID = fam_ID and b.plg_FYID=~fyid2~ and b.plg_PledgeOrPayment=\'Pledge\' order by fam_Name','Pledge comparison','Compare pledges between two fiscal years',1),(30,'SELECT per_ID as AddToCart, CONCAT(per_FirstName,\' \',per_LastName) AS Name, fam_address1, fam_city, fam_state, fam_zip FROM person_per join family_fam on per_fam_id=fam_id where per_fmr_id<>3 and per_fam_id in (select fam_id from family_fam inner join pledge_plg a on a.plg_famID=fam_ID and a.plg_FYID=~fyid1~ and a.plg_amount>0) and per_fam_id not in (select fam_id from family_fam inner join pledge_plg b on b.plg_famID=fam_ID and b.plg_FYID=~fyid2~ and b.plg_amount>0)','Missing pledges','Find people who pledged one year but not another',1),(31,'select per_ID as AddToCart, per_FirstName, per_LastName, per_email from person_per, autopayment_aut where aut_famID=per_fam_ID and aut_CreditCard!=\"\" and per_email!=\"\" and (per_fmr_ID=1 or per_fmr_ID=2 or per_cls_ID=1)','Credit Card People','People who are configured to pay by credit card.',0),(32,'SELECT fam_Name, fam_Envelope, b.fun_Name as Fund_Name, a.plg_amount as Pledge from family_fam left join pledge_plg a on a.plg_famID = fam_ID and a.plg_FYID=~fyid~ and a.plg_PledgeOrPayment=\'Pledge\' and a.plg_amount>0 join donationfund_fun b on b.fun_ID = a.plg_fundID order by fam_Name, a.plg_fundID','Family Pledge by Fiscal Year','Pledge summary by family name for each fund for the selected fiscal year',1),(100,'SELECT a.per_ID as AddToCart, CONCAT(\'<a href=PersonView.php?PersonID=\',a.per_ID,\'>\',a.per_FirstName,\' \',a.per_LastName,\'</a>\') AS Name FROM person_per AS a LEFT JOIN person2volunteeropp_p2vo p2v1 ON (a.per_id = p2v1.p2vo_per_ID AND p2v1.p2vo_vol_ID = ~volopp1~) LEFT JOIN person2volunteeropp_p2vo p2v2 ON (a.per_id = p2v2.p2vo_per_ID AND p2v2.p2vo_vol_ID = ~volopp2~) WHERE p2v1.p2vo_per_ID=p2v2.p2vo_per_ID ORDER BY per_LastName','Volunteers','Find volunteers for who match two specific opportunity codes',1),(200,'SELECT a.per_ID as AddToCart, CONCAT(\'<a href=PersonView.php?PersonID=\',a.per_ID,\'>\',a.per_FirstName,\' \',a.per_LastName,\'</a>\') AS Name FROM person_per AS a LEFT JOIN person_custom pc ON a.per_id = pc.per_ID WHERE pc.~custom~=\'~value~\' ORDER BY per_LastName','CustomSearch','Find people with a custom field value',1);
/*!40000 ALTER TABLE `query_qry` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;

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
  `qpo_Value` varchar(255) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  PRIMARY KEY (`qpo_ID`),
  UNIQUE KEY `qpo_ID` (`qpo_ID`)
) ENGINE=InnoDB AUTO_INCREMENT=37 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `queryparameteroptions_qpo`
--

LOCK TABLES `queryparameteroptions_qpo` WRITE;
/*!40000 ALTER TABLE `queryparameteroptions_qpo` DISABLE KEYS */;
SET autocommit=0;
INSERT INTO `queryparameteroptions_qpo` VALUES (1,4,'Male','1'),(2,4,'Female','2'),(3,6,'Male','1'),(4,6,'Female','2'),(5,15,'Name','CONCAT(COALESCE(`per_FirstName`,\'),COALESCE(`per_MiddleName`,\'),COALESCE(`per_LastName`,\'))'),(6,15,'Zip Code','fam_Zip'),(7,15,'State','fam_State'),(8,15,'City','fam_City'),(9,15,'Home Phone','per_HomePhone'),(10,27,'2012/2013','17'),(11,27,'2013/2014','18'),(12,27,'2014/2015','19'),(13,27,'2015/2016','20'),(14,28,'2012/2013','17'),(15,28,'2013/2014','18'),(16,28,'2014/2015','19'),(17,28,'2015/2016','20'),(18,30,'2012/2013','17'),(19,30,'2013/2014','18'),(20,30,'2014/2015','19'),(21,30,'2015/2016','20'),(22,31,'2012/2013','17'),(23,31,'2013/2014','18'),(24,31,'2014/2015','19'),(25,31,'2015/2016','20'),(26,15,'Email','per_Email'),(27,15,'WorkEmail','per_WorkEmail'),(28,32,'2012/2013','17'),(29,32,'2013/2014','18'),(30,32,'2014/2015','19'),(31,32,'2015/2016','20'),(32,33,'Member','1'),(33,33,'Regular Attender','2'),(34,33,'Guest','3'),(35,33,'Non-Attender','4'),(36,33,'Non-Attender (staff)','5');
/*!40000 ALTER TABLE `queryparameteroptions_qpo` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;

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
) ENGINE=InnoDB AUTO_INCREMENT=202 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `queryparameters_qrp`
--

LOCK TABLES `queryparameters_qrp` WRITE;
/*!40000 ALTER TABLE `queryparameters_qrp` DISABLE KEYS */;
SET autocommit=0;
INSERT INTO `queryparameters_qrp` VALUES (1,4,0,NULL,'Minimum Age','The minimum age for which you want records returned.','min','0',0,5,'n',120,0,NULL,NULL),(2,4,0,NULL,'Maximum Age','The maximum age for which you want records returned.','max','120',1,5,'n',120,0,NULL,NULL),(4,6,1,'','Gender','The desired gender to search the database for.','gender','1',1,0,'',0,0,0,0),(5,7,2,'SELECT lst_OptionID as Value, lst_OptionName as Display FROM list_lst WHERE lst_ID=2 ORDER BY lst_OptionSequence','Family Role','Select the desired family role.','role','1',0,0,'',0,0,0,0),(6,7,1,'','Gender','The gender for which you would like records returned.','gender','1',1,0,'',0,0,0,0),(8,9,2,'SELECT pro_ID AS Value, pro_Name as Display \r\nFROM property_pro\r\nWHERE pro_Class= \'p\' \r\nORDER BY pro_Name ','Property','The property for which you would like person records returned.','PropertyID','0',1,0,'',0,0,0,0),(9,10,2,'SELECT distinct don_date as Value, don_date as Display FROM donations_don ORDER BY don_date ASC','Beginning Date','Please select the beginning date to calculate total contributions for each member (i.e. YYYY-MM-DD). NOTE: You can only choose dates that conatain donations.','startdate','1',1,0,'0',0,0,0,0),(10,10,2,'SELECT distinct don_date as Value, don_date as Display FROM donations_don\r\nORDER BY don_date DESC','Ending Date','Please enter the last date to calculate total contributions for each member (i.e. YYYY-MM-DD).','enddate','1',1,0,'',0,0,0,0),(14,15,0,'','Search','Enter any part of the following: Name, City, State, Zip, Home Phone, Email, or Work Email.','searchstring','',1,0,'',0,0,0,0),(15,15,1,'','Field','Select field to search for.','searchwhat','1',1,0,'',0,0,0,0),(16,11,2,'SELECT distinct don_date as Value, don_date as Display FROM donations_don ORDER BY don_date ASC','Beginning Date','Please select the beginning date to calculate total contributions for each member (i.e. YYYY-MM-DD). NOTE: You can only choose dates that conatain donations.','startdate','1',1,0,'0',0,0,0,0),(17,11,2,'SELECT distinct don_date as Value, don_date as Display FROM donations_don\r\nORDER BY don_date DESC','Ending Date','Please enter the last date to calculate total contributions for each member (i.e. YYYY-MM-DD).','enddate','1',1,0,'',0,0,0,0),(18,18,0,'','Month','The birthday month for which you would like records returned.','birthmonth','1',1,0,'',12,1,1,2),(19,19,2,'SELECT grp_ID AS Value, grp_Name AS Display FROM group_grp ORDER BY grp_Type','Class','The sunday school class for which you would like records returned.','group','1',1,0,'',12,1,1,2),(20,20,2,'SELECT grp_ID AS Value, grp_Name AS Display FROM group_grp ORDER BY grp_Type','Class','The sunday school class for which you would like records returned.','group','1',1,0,'',12,1,1,2),(21,21,2,'SELECT grp_ID AS Value, grp_Name AS Display FROM group_grp ORDER BY grp_Type','Registered students','Group of registered students','group','1',1,0,'',12,1,1,2),(22,22,0,'','Month','The membership anniversary month for which you would like records returned.','membermonth','1',1,0,'',12,1,1,2),(25,25,2,'SELECT vol_ID AS Value, vol_Name AS Display FROM volunteeropportunity_vol ORDER BY vol_Name','Volunteer opportunities','Choose a volunteer opportunity','volopp','1',1,0,'',12,1,1,2),(26,26,0,'','Months','Number of months since becoming a friend','friendmonths','1',1,0,'',24,1,1,2),(27,28,1,'','First Fiscal Year','First fiscal year for comparison','fyid1','9',1,0,'',12,9,0,0),(28,28,1,'','Second Fiscal Year','Second fiscal year for comparison','fyid2','9',1,0,'',12,9,0,0),(30,30,1,'','First Fiscal Year','Pledged this year','fyid1','9',1,0,'',12,9,0,0),(31,30,1,'','Second Fiscal Year','but not this year','fyid2','9',1,0,'',12,9,0,0),(32,32,1,'','Fiscal Year','Fiscal Year.','fyid','9',1,0,'',12,9,0,0),(33,18,1,'','Classification','Member, Regular Attender, etc.','percls','1',1,0,'',12,1,1,2),(100,100,2,'SELECT vol_ID AS Value, vol_Name AS Display FROM volunteeropportunity_vol ORDER BY vol_Name','Volunteer opportunities','First volunteer opportunity choice','volopp1','1',1,0,'',12,1,1,2),(101,100,2,'SELECT vol_ID AS Value, vol_Name AS Display FROM volunteeropportunity_vol ORDER BY vol_Name','Volunteer opportunities','Second volunteer opportunity choice','volopp2','1',1,0,'',12,1,1,2),(200,200,2,'SELECT custom_field as Value, custom_Name as Display FROM person_custom_master','Custom field','Choose customer person field','custom','1',0,0,'',0,0,0,0),(201,200,0,'','Field value','Match custom field to this value','value','1',0,0,'',0,0,0,0);
/*!40000 ALTER TABLE `queryparameters_qrp` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `record2property_r2p`
--

LOCK TABLES `record2property_r2p` WRITE;
/*!40000 ALTER TABLE `record2property_r2p` DISABLE KEYS */;
SET autocommit=0;
INSERT INTO `record2property_r2p` VALUES (4,7,''),(4,8,'');
/*!40000 ALTER TABLE `record2property_r2p` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `result_res`
--

LOCK TABLES `result_res` WRITE;
/*!40000 ALTER TABLE `result_res` DISABLE KEYS */;
SET autocommit=0;
/*!40000 ALTER TABLE `result_res` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;

--
-- Table structure for table `tokens`
--

DROP TABLE IF EXISTS `tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tokens` (
  `token` varchar(99) COLLATE utf8_unicode_ci NOT NULL,
  `type` varchar(50) COLLATE utf8_unicode_ci DEFAULT NULL,
  `reference_id` int(9) NOT NULL,
  `valid_until_date` datetime DEFAULT NULL,
  `remainingUses` int(2) DEFAULT NULL,
  PRIMARY KEY (`token`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tokens`
--

LOCK TABLES `tokens` WRITE;
/*!40000 ALTER TABLE `tokens` DISABLE KEYS */;
SET autocommit=0;
/*!40000 ALTER TABLE `tokens` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;

--
-- Table structure for table `user_usr`
--

DROP TABLE IF EXISTS `user_usr`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `user_usr` (
  `usr_per_ID` mediumint(9) unsigned NOT NULL DEFAULT '0',
  `usr_Password` varchar(500) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  `usr_NeedPasswordChange` tinyint(3) unsigned NOT NULL DEFAULT '1',
  `usr_LastLogin` datetime NOT NULL DEFAULT '2016-01-01 00:00:00',
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
  `usr_showSince` date NOT NULL DEFAULT '2016-01-01',
  `usr_defaultFY` mediumint(9) NOT NULL DEFAULT '10',
  `usr_currentDeposit` mediumint(9) NOT NULL DEFAULT '0',
  `usr_UserName` varchar(32) COLLATE utf8_unicode_ci DEFAULT NULL,
  `usr_apiKey` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
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
  UNIQUE KEY `usr_apiKey_unique` (`usr_apiKey`),
  KEY `usr_per_ID` (`usr_per_ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `user_usr`
--

LOCK TABLES `user_usr` WRITE;
/*!40000 ALTER TABLE `user_usr` DISABLE KEYS */;
SET autocommit=0;
INSERT INTO `user_usr` VALUES (1,'4bdf3fba58c956fc3991a1fde84929223f968e2853de596e49ae80a91499609b',0,'2018-04-09 12:03:10',23,0,0,0,0,0,0,0,0,0,1,580,9,10,'skin-red',0,0,'2016-01-01',22,0,'Admin','ajGwpy8Pdai22XDUpqjC5Ob04v0eG7EGgb4vz2bD2juT8YDmfM',0,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,0,0),(3,'598e1814d6b8493f2ad688c634c8b22bb31ac7539b3f79438b91aab2470f574f',0,'2017-12-23 19:03:25',8,0,1,1,1,1,1,0,0,1,0,NULL,NULL,10,'skin-green',0,0,'2016-01-01',21,0,'tony.wade@example.com',NULL,1,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,0,0),(76,'e57a2fc7529930d46edee4d20ee17e70001fd51a267c11768f9a0dc6dab2fdc1',1,'2016-11-19 16:10:16',2,0,0,0,0,1,0,0,0,0,0,NULL,NULL,10,'skin-blue',0,0,'2016-01-01',20,0,'leroy.larson@example.com',NULL,1,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,0),(95,'ea1a2d06bbb09a6ea84f918fdb18ac17615365afa5ff09ac73eaf6e68cb5352f',1,'2016-11-19 16:09:53',1,6,1,1,0,0,0,0,0,0,0,NULL,NULL,10,'skin-blue',0,0,'2016-01-01',20,0,'judith.matthews@example.com',NULL,1,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,0);
/*!40000 ALTER TABLE `user_usr` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `userconfig_ucfg`
--

LOCK TABLES `userconfig_ucfg` WRITE;
/*!40000 ALTER TABLE `userconfig_ucfg` DISABLE KEYS */;
SET autocommit=0;
INSERT INTO `userconfig_ucfg` VALUES (0,0,'bEmailMailto','1','boolean','User permission to send email via mailto: links','TRUE',''),(0,1,'sMailtoDelimiter',',','text','Delimiter to separate emails in mailto: links','TRUE',''),(0,5,'bCreateDirectory','0','boolean','User permission to create directories','FALSE','SECURITY'),(0,6,'bExportCSV','0','boolean','User permission to export CSV files','FALSE','SECURITY'),(0,7,'bUSAddressVerification','0','boolean','User permission to use IST Address Verification','FALSE',''),(0,10,'bAddEvent','0','boolean','Allow user to add new event','FALSE','SECURITY'),(1,0,'bEmailMailto','1','boolean','User permission to send email via mailto: links','TRUE',''),(1,1,'sMailtoDelimiter',',','text','user permission to send email via mailto: links','TRUE',''),(1,5,'bCreateDirectory','1','boolean','User permission to create directories','TRUE',''),(1,6,'bExportCSV','1','boolean','User permission to export CSV files','TRUE',''),(1,7,'bUSAddressVerification','1','boolean','User permission to use IST Address Verification','TRUE',''),(3,0,'bEmailMailto','1','boolean','User permission to send email via mailto: links','TRUE',''),(3,1,'sMailtoDelimiter',',','text','Delimiter to separate emails in mailto: links','TRUE',''),(3,5,'bCreateDirectory','','boolean','User permission to create directories','FALSE','SECURITY'),(3,6,'bExportCSV','','boolean','User permission to export CSV files','FALSE','SECURITY'),(3,7,'bUSAddressVerification','','boolean','User permission to use IST Address Verification','FALSE',''),(3,10,'bAddEvent','','boolean','Allow user to add new event','FALSE','SECURITY'),(76,0,'bEmailMailto','1','boolean','User permission to send email via mailto: links','TRUE',''),(76,1,'sMailtoDelimiter',',','text','Delimiter to separate emails in mailto: links','TRUE',''),(76,5,'bCreateDirectory','','boolean','User permission to create directories','FALSE','SECURITY'),(76,6,'bExportCSV','','boolean','User permission to export CSV files','FALSE','SECURITY'),(76,7,'bUSAddressVerification','','boolean','User permission to use IST Address Verification','FALSE',''),(76,10,'bAddEvent','','boolean','Allow user to add new event','FALSE','SECURITY'),(95,0,'bEmailMailto','1','boolean','User permission to send email via mailto: links','TRUE',''),(95,1,'sMailtoDelimiter',',','text','Delimiter to separate emails in mailto: links','TRUE',''),(95,5,'bCreateDirectory','','boolean','User permission to create directories','FALSE','SECURITY'),(95,6,'bExportCSV','','boolean','User permission to export CSV files','FALSE','SECURITY'),(95,7,'bUSAddressVerification','','boolean','User permission to use IST Address Verification','FALSE',''),(95,10,'bAddEvent','','boolean','Allow user to add new event','FALSE','SECURITY');
/*!40000 ALTER TABLE `userconfig_ucfg` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;

--
-- Table structure for table `version_ver`
--

DROP TABLE IF EXISTS `version_ver`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `version_ver` (
  `ver_ID` mediumint(9) unsigned NOT NULL AUTO_INCREMENT,
  `ver_version` varchar(50) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  `ver_update_start` datetime DEFAULT NULL,
  `ver_update_end` datetime DEFAULT NULL,
  PRIMARY KEY (`ver_ID`),
  UNIQUE KEY `ver_version` (`ver_version`)
) ENGINE=InnoDB AUTO_INCREMENT=17 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `version_ver`
--

LOCK TABLES `version_ver` WRITE;
/*!40000 ALTER TABLE `version_ver` DISABLE KEYS */;
SET autocommit=0;
INSERT INTO `version_ver` VALUES (1,'3.3.1','2019-02-10 20:14:23',NULL);
/*!40000 ALTER TABLE `version_ver` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `volunteeropportunity_vol`
--

LOCK TABLES `volunteeropportunity_vol` WRITE;
/*!40000 ALTER TABLE `volunteeropportunity_vol` DISABLE KEYS */;
SET autocommit=0;
/*!40000 ALTER TABLE `volunteeropportunity_vol` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `whycame_why`
--

LOCK TABLES `whycame_why` WRITE;
/*!40000 ALTER TABLE `whycame_why` DISABLE KEYS */;
SET autocommit=0;
/*!40000 ALTER TABLE `whycame_why` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;

--
-- Stand-In structure for view `email_count`
--

DROP TABLE IF EXISTS `email_count`;
/*!50001 DROP VIEW IF EXISTS `email_count`*/;
CREATE TABLE IF NOT EXISTS `email_count` (
`email` varchar(100)
,`total` bigint(21)
);
--
-- Stand-In structure for view `email_list`
--

DROP TABLE IF EXISTS `email_list`;
/*!50001 DROP VIEW IF EXISTS `email_list`*/;
CREATE TABLE IF NOT EXISTS `email_list` (
`email` varchar(100)
,`type` varchar(11)
,`id` mediumint(9) unsigned
);
--
-- View structure for view `email_count`
--

DROP TABLE IF EXISTS `email_count`;
/*!50001 DROP VIEW IF EXISTS `email_count`*/;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`churchcrm`@`%` SQL SECURITY DEFINER */
/*!50001 VIEW `email_count` AS select `email_list`.`email` AS `email`,count(0) AS `total` from `email_list` group by `email_list`.`email` */;

--
-- View structure for view `email_list`
--

DROP TABLE IF EXISTS `email_list`;
/*!50001 DROP VIEW IF EXISTS `email_list`*/;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`churchcrm`@`%` SQL SECURITY DEFINER */
/*!50001 VIEW `email_list` AS select `family_fam`.`fam_Email` AS `email`,'family' AS `type`,`family_fam`.`fam_ID` AS `id` from `family_fam` where ((`family_fam`.`fam_Email` is not null) and (`family_fam`.`fam_Email` <> '')) union select `person_per`.`per_Email` AS `email`,'person_home' AS `type`,`person_per`.`per_ID` AS `id` from `person_per` where ((`person_per`.`per_Email` is not null) and (`person_per`.`per_Email` <> '')) union select `person_per`.`per_WorkEmail` AS `email`,'person_work' AS `type`,`person_per`.`per_ID` AS `id` from `person_per` where ((`person_per`.`per_WorkEmail` is not null) and (`person_per`.`per_WorkEmail` <> '')) */;

/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;
/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on: Mon, 09 Apr 2018 12:04:25 -0400
