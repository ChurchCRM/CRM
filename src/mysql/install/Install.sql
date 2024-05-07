--
-- Table structure for table `version_ver`
--

CREATE TABLE `version_ver` (
  `ver_ID` mediumint(9) unsigned NOT NULL auto_increment,
  `ver_version` varchar(50) NOT NULL default '',
  `ver_update_start` datetime default NULL,
  `ver_update_end` datetime default NULL,
  PRIMARY KEY  (`ver_ID`),
  UNIQUE KEY `ver_version` (`ver_version`)
) ENGINE=InnoDB CHARACTER SET utf8 COLLATE utf8_unicode_ci  AUTO_INCREMENT=1 ;


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
  PRIMARY KEY  (`can_ID`)
) ENGINE=InnoDB CHARACTER SET utf8 COLLATE utf8_unicode_ci AUTO_INCREMENT=1 ;

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
  PRIMARY KEY  (`cfg_id`),
  UNIQUE KEY `cfg_name` (`cfg_name`)
) ENGINE=InnoDB CHARACTER SET utf8 COLLATE utf8_unicode_ci;

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
) ENGINE=InnoDB CHARACTER SET utf8 COLLATE utf8_unicode_ci PACK_KEYS=0 AUTO_INCREMENT=1 ;

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
  PRIMARY KEY  (`fun_ID`)
) ENGINE=InnoDB CHARACTER SET utf8 COLLATE utf8_unicode_ci  AUTO_INCREMENT=2 ;

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
) ENGINE=InnoDB CHARACTER SET utf8 COLLATE utf8_unicode_ci;

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
) ENGINE=InnoDB CHARACTER SET utf8 COLLATE utf8_unicode_ci;

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
) ENGINE=InnoDB CHARACTER SET utf8 COLLATE utf8_unicode_ci  AUTO_INCREMENT=7 ;

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
) ENGINE=InnoDB CHARACTER SET utf8 COLLATE utf8_unicode_ci;

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
  `event_start` datetime NOT NULL,
  `event_end` datetime NOT NULL,
  `inactive` int(1) NOT NULL default '0',
  `location_id` INT DEFAULT NULL,
  `primary_contact_person_id` INT DEFAULT NULL,
  `secondary_contact_person_id` INT DEFAULT NULL,
  `event_url` text,
  PRIMARY KEY  (`event_id`)
) ENGINE=InnoDB CHARACTER SET utf8 COLLATE utf8_unicode_ci AUTO_INCREMENT=1;

--
-- Dumping data for table `events_event`
--


-- --------------------------------------------------------

--
-- Table structure for table `event_audience`
--

CREATE TABLE `event_audience` (
  `event_id` INT NOT NULL,
  `group_id` INT NOT NULL,
  PRIMARY KEY (`event_id`,`group_id`)
) ENGINE=InnoDB CHARACTER SET utf8 COLLATE utf8_unicode_ci;

--
-- Table structure for table `event_attend`
--

CREATE TABLE `event_attend` (
  `attend_id` int(11) NOT NULL auto_increment,
  `event_id` int(11) NOT NULL default '0',
  `person_id` int(11) NOT NULL default '0',
  `checkin_date` datetime default NULL,
  `checkin_id` int(11) default NULL,
  `checkout_date` datetime default NULL,
  `checkout_id` int(11) default NULL,
  PRIMARY KEY  (`attend_id`),
  UNIQUE KEY `event_id` (`event_id`,`person_id`)
) ENGINE=InnoDB CHARACTER SET utf8 COLLATE utf8_unicode_ci;

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
  `type_defrecurDOY` date NOT NULL default '2000-01-01',
  `type_active` int(1) NOT NULL default '1',
  `type_grpid` mediumint(9),

  PRIMARY KEY  (`type_id`)
) ENGINE=InnoDB CHARACTER SET utf8 COLLATE utf8_unicode_ci  AUTO_INCREMENT=3 ;

--
-- Dumping data for table `event_types`
--

INSERT INTO `event_types` (`type_id`, `type_name`, `type_defstarttime`, `type_defrecurtype`, `type_defrecurDOW`, `type_defrecurDOM`, `type_defrecurDOY`, `type_active`) VALUES
  (1, 'Church Service', '10:30:00', 'weekly', 'Sunday', '', '2016-01-01', 1),
  (2, 'Sunday School', '09:30:00', 'weekly', 'Sunday', '', '2016-01-01', 1);

-- --------------------------------------------------------


CREATE TABLE `calendars` (
  `calendar_id` INT NOT NULL auto_increment,
  `name` VARCHAR(128) NOT NULL,
  `accesstoken` VARCHAR(255),
  `foregroundColor` VARCHAR(6),
  `backgroundColor` VARCHAR(6),
  PRIMARY KEY (`calendar_id`),
  UNIQUE KEY `accesstoken` (`accesstoken`)
) ENGINE=InnoDB CHARACTER SET utf8 COLLATE utf8_unicode_ci;

INSERT INTO `calendars` (`calendar_id`,`name`,`accesstoken`,`foregroundColor`,`backgroundColor`) VALUES
 (1,"Public Calendar",NULL,"FFFFFF","00AA00"),
 (2,"Private Calendar",NULL,"FFFFFF","0000AA");

# This is a join-table to link an event with a calendar
CREATE TABLE `calendar_events` (
  `calendar_id` INT NOT NULL,
  `event_id` INT NOT NULL,
  PRIMARY KEY (`calendar_id`,`event_id`)
) ENGINE=InnoDB CHARACTER SET utf8 COLLATE utf8_unicode_ci;

--
-- Table structure for table `family_custom`
--

CREATE TABLE `family_custom` (
  `fam_ID` mediumint(9) NOT NULL default '0',
  PRIMARY KEY  (`fam_ID`)
) ENGINE=InnoDB CHARACTER SET utf8 COLLATE utf8_unicode_ci;

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
  `fam_custom_FieldSec` tinyint(4) NOT NULL default '1',
  `type_ID` tinyint(4) NOT NULL default '0'
) ENGINE=InnoDB CHARACTER SET utf8 COLLATE utf8_unicode_ci;

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
  `fam_DateEntered` datetime NOT NULL,
  `fam_DateLastEdited` datetime default NULL,
  `fam_EnteredBy` smallint(5) NOT NULL default '0',
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
  PRIMARY KEY  (`fam_ID`)
) ENGINE=InnoDB CHARACTER SET utf8 COLLATE utf8_unicode_ci AUTO_INCREMENT=1 ;

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
) ENGINE=InnoDB CHARACTER SET utf8 COLLATE utf8_unicode_ci COMMENT='Group-specific properties order, name, description, type';

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
  `grp_hasSpecialProps` BOOLEAN NOT NULL default 0,
  `grp_active` BOOLEAN NOT NULL default 1,
  `grp_include_email_export` BOOLEAN NOT NULL default 1,
  PRIMARY KEY  (`grp_ID`)
) ENGINE=InnoDB CHARACTER SET utf8 COLLATE utf8_unicode_ci AUTO_INCREMENT=1 ;

--
-- Dumping data for table `group_grp`
--


-- --------------------------------------------------------

--
-- Table structure for table `istlookup_lu`
--

CREATE TABLE `istlookup_lu` (
  `lu_fam_ID` mediumint(9) NOT NULL default '0',
  `lu_LookupDateTime` datetime NOT NULL default '2000-01-01 00:00:00',
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
) ENGINE=InnoDB CHARACTER SET utf8 COLLATE utf8_unicode_ci COMMENT='US Address Verification Lookups From Intelligent Search Tech';

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
) ENGINE=InnoDB CHARACTER SET utf8 COLLATE utf8_unicode_ci;

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
  (5, 7, 7, 'bManageGroups'),
  (5, 8, 8, 'bFinance'),
  (5, 9, 9, 'bNotes'),
  (5, 11, 11, 'bCanvasser'),
  (10, 1, 1, 'Teacher'),
  (10, 2, 2, 'Student'),
  (11, 1, 1, 'Member'),
  (12, 1, 1, 'Teacher'),
  (12, 2, 2, 'Student');

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
  `nte_DateEntered` datetime NOT NULL,
  `nte_DateLastEdited` datetime default NULL,
  `nte_EnteredBy` mediumint(8) NOT NULL default '0',
  `nte_EditedBy` mediumint(8) unsigned NOT NULL default '0',
  `nte_Type` varchar(50) DEFAULT NULL,
  PRIMARY KEY  (`nte_ID`)
) ENGINE=InnoDB CHARACTER SET utf8 COLLATE utf8_unicode_ci AUTO_INCREMENT=1 ;

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
) ENGINE=InnoDB CHARACTER SET utf8 COLLATE utf8_unicode_ci;

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
  PRIMARY KEY  (`p2vo_ID`)
) ENGINE=InnoDB CHARACTER SET utf8 COLLATE utf8_unicode_ci AUTO_INCREMENT=1 ;

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
) ENGINE=InnoDB CHARACTER SET utf8 COLLATE utf8_unicode_ci;

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
  `custom_FieldSec` tinyint(4) NOT NULL,
  `type_ID` tinyint(4) NOT NULL default '0',
  PRIMARY KEY (`custom_Field`)
) ENGINE=InnoDB CHARACTER SET utf8 COLLATE utf8_unicode_ci;

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
  `per_BirthYear` smallint(4) unsigned default NULL,
  `per_MembershipDate` date default NULL,
  `per_Gender` tinyint(1) unsigned NOT NULL default '0',
  `per_fmr_ID` tinyint(3) unsigned NOT NULL default '0',
  `per_cls_ID` tinyint(3) unsigned NOT NULL default '0',
  `per_fam_ID` smallint(5) unsigned NOT NULL default '0',
  `per_Envelope` smallint(5) unsigned default NULL,
  `per_DateLastEdited` datetime default NULL,
  `per_DateEntered` datetime NOT NULL,
  `per_EnteredBy` smallint(5)  NOT NULL default '0',
  `per_EditedBy` smallint(5) unsigned default '0',
  `per_FriendDate` date default NULL,
  `per_Flags` mediumint(9) NOT NULL default '0',
  `per_Facebook` varchar(50) default NULL,
  `per_Twitter` varchar(50) default NULL,
  `per_LinkedIn` varchar(50) default NULL,
  PRIMARY KEY  (`per_ID`)
) ENGINE=InnoDB CHARACTER SET utf8 COLLATE utf8_unicode_ci  AUTO_INCREMENT=2 ;

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
  `plg_DateLastEdited` date NOT NULL default '2016-01-01',
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
) ENGINE=InnoDB CHARACTER SET utf8 COLLATE utf8_unicode_ci AUTO_INCREMENT=1 ;

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
  PRIMARY KEY  (`prt_ID`)
) ENGINE=InnoDB CHARACTER SET utf8 COLLATE utf8_unicode_ci  AUTO_INCREMENT=4 ;

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
  PRIMARY KEY  (`pro_ID`)
) ENGINE=InnoDB CHARACTER SET utf8 COLLATE utf8_unicode_ci  AUTO_INCREMENT=4 ;

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
  `qpo_Value` varchar(255) NOT NULL default '',
  PRIMARY KEY  (`qpo_ID`)
) ENGINE=InnoDB CHARACTER SET utf8 COLLATE utf8_unicode_ci  AUTO_INCREMENT=28 ;

--
-- Dumping data for table `queryparameteroptions_qpo`
--

INSERT INTO `queryparameteroptions_qpo` (`qpo_qrp_ID`, `qpo_Display`, `qpo_Value`) VALUES
  (4, 'Male', '1'),
  (4, 'Female', '2'),
  (6, 'Male', '1'),
  (6, 'Female', '2'),
  (15, 'Name', 'CONCAT(COALESCE(`per_FirstName`,''''),COALESCE(`per_MiddleName`,''''),COALESCE(`per_LastName`,''''))'),
  (15, 'Zip Code', 'fam_Zip'),
  (15, 'State', 'fam_State'),
  (15, 'City', 'fam_City'),
  (15, 'Home Phone', 'per_HomePhone'),
  (27, '2012/2013', '17'),
  (27, '2013/2014', '18'),
  (27, '2014/2015', '19'),
  (27, '2015/2016', '20'),
  (27, '2016/2017', '21'),
  (27, '2017/2018', '22'),
  (27, '2018/2019', '23'),
  (27, '2019/2020', '24'),
  (27, '2020/2021', '25'),
  (27, '2021/2022', '26'),
  (27, '2022/2023', '27'),
  (28, '2012/2013', '17'),
  (28, '2013/2014', '18'),
  (28, '2014/2015', '19'),
  (28, '2015/2016', '20'),
  (28, '2016/2017', '21'),
  (28, '2017/2018', '22'),
  (28, '2018/2019', '23'),
  (28, '2019/2020', '24'),
  (28, '2020/2021', '25'),
  (28, '2021/2022', '26'),
  (28, '2022/2023', '27'),
  (30, '2012/2013', '17'),
  (30, '2013/2014', '18'),
  (30, '2014/2015', '19'),
  (30, '2015/2016', '20'),
  (30, '2016/2017', '21'),
  (30, '2017/2018', '22'),
  (30, '2018/2019', '23'),
  (30, '2019/2020', '24'),
  (30, '2020/2021', '25'),
  (30, '2021/2022', '26'),
  (30, '2022/2023', '27'),
  (31, '2012/2013', '17'),
  (31, '2013/2014', '18'),
  (31, '2014/2015', '19'),
  (31, '2015/2016', '20'),
  (31, '2016/2017', '21'),
  (31, '2017/2018', '22'),
  (31, '2018/2019', '23'),
  (31, '2019/2020', '24'),
  (31, '2020/2021', '25'),
  (31, '2021/2022', '26'),
  (31, '2022/2023', '27'),
  (15, 'Email', 'per_Email'),
  (15, 'WorkEmail', 'per_WorkEmail'),
  (32, '2012/2013', '17'),
  (32, '2013/2014', '18'),
  (32, '2014/2015', '19'),
  (32, '2015/2016', '20'),
  (32, '2016/2017', '21'),
  (32, '2017/2018', '22'),
  (32, '2018/2019', '23'),
  (32, '2019/2020', '24'),
  (32, '2020/2021', '25'),
  (32, '2021/2022', '26'),
  (32, '2022/2023', '27'),
  (33, 'Member', '1'),
  (33, 'Regular Attender', '2'),
  (33, 'Guest', '3'),
  (33, 'Non-Attender', '4'),
  (33, 'Non-Attender (staff)', '5');

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
  KEY `qrp_qry_ID` (`qrp_qry_ID`)
) ENGINE=InnoDB CHARACTER SET utf8 COLLATE utf8_unicode_ci  AUTO_INCREMENT=102 ;

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
  (9, 10, 2, 'SELECT distinct don_date as Value, don_date as Display FROM donations_don ORDER BY don_date ASC', 'Beginning Date', 'Please select the beginning date to calculate total contributions for each member (i.e. YYYY-MM-DD). NOTE: You can only choose dates that contain donations.', 'startdate', '1', 1, 0, '0', 0, 0, 0, 0),
  (10, 10, 2, 'SELECT distinct don_date as Value, don_date as Display FROM donations_don\r\nORDER BY don_date DESC', 'Ending Date', 'Please enter the last date to calculate total contributions for each member (i.e. YYYY-MM-DD).', 'enddate', '1', 1, 0, '', 0, 0, 0, 0),
  (14, 15, 0, '', 'Search', 'Enter any part of the following: Name, City, State, Zip, Home Phone, Email, or Work Email.', 'searchstring', '', 1, 0, '', 0, 0, 0, 0),
  (15, 15, 1, '', 'Field', 'Select field to search for.', 'searchwhat', '1', 1, 0, '', 0, 0, 0, 0),
  (16, 11, 2, 'SELECT distinct don_date as Value, don_date as Display FROM donations_don ORDER BY don_date ASC', 'Beginning Date', 'Please select the beginning date to calculate total contributions for each member (i.e. YYYY-MM-DD). NOTE: You can only choose dates that contain donations.', 'startdate', '1', 1, 0, '0', 0, 0, 0, 0),
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
  (201, 200, 0, '', 'Field value', 'Match custom field to this value', 'value', '1', 0, 0, '', 0, 0, 0, 0),
  (202, 201, 3, 'SELECT event_id as Value, event_title as Display FROM events_event ORDER BY event_start DESC', 'Event', 'Select the desired event', 'event', '', 1, 0, '', 0, 0, 0, 0);

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
  PRIMARY KEY  (`qry_ID`)
) ENGINE=InnoDB CHARACTER SET utf8 COLLATE utf8_unicode_ci  AUTO_INCREMENT=101 ;

--
-- Dumping data for table `query_qry`
--

INSERT INTO `query_qry` (`qry_ID`, `qry_SQL`, `qry_Name`, `qry_Description`, `qry_Count`) VALUES
  (3, 'SELECT CONCAT(''<a href=v2/family/'',fam_ID,''>'',fam_Name,''</a>'') AS ''Family Name'', COUNT(*) AS ''No.''\nFROM person_per\nINNER JOIN family_fam\nON fam_ID = per_fam_ID\nGROUP BY per_fam_ID\nORDER BY ''No.'' DESC', 'Family Member Count', 'Returns each family and the total number of people assigned to them.', 0),
  (4, 'SELECT per_ID as AddToCart,CONCAT(''<a\r\nhref=PersonView.php?PersonID='',per_ID,''>'',per_FirstName,''\r\n'',per_LastName,''</a>'') AS Name,\r\nCONCAT(per_BirthMonth,''/'',per_BirthDay,''/'',per_BirthYear) AS ''Birth Date'',\r\nDATE_FORMAT(FROM_DAYS(TO_DAYS(NOW())-TO_DAYS(CONCAT(per_BirthYear,''-'',per_BirthMonth,''-'',per_BirthDay))),''%Y'')+0 AS  ''Age''\r\nFROM person_per\r\nWHERE\r\nDATE_ADD(CONCAT(per_BirthYear,''-'',per_BirthMonth,''-'',per_BirthDay),INTERVAL\r\n~min~ YEAR) <= CURDATE()\r\nAND\r\nDATE_ADD(CONCAT(per_BirthYear,''-'',per_BirthMonth,''-'',per_BirthDay),INTERVAL\r\n(~max~ + 1) YEAR) >= CURDATE()', 'Person by Age', 'Returns any person records with ages between two given ages.', 1),
  (6, 'SELECT COUNT(per_ID) AS Total FROM person_per WHERE per_Gender = ~gender~', 'Total By Gender', 'Total of records matching a given gender.', 0),
  (7, 'SELECT per_ID as AddToCart, CONCAT(per_FirstName,'' '',per_LastName) AS Name FROM person_per WHERE per_fmr_ID = ~role~ AND per_Gender = ~gender~', 'Person by Role and Gender', 'Selects person records with the family role and gender specified.', 1),
  (9, 'SELECT \r\nper_ID as AddToCart, \r\nCONCAT(per_FirstName,'' '',per_LastName) AS Name, \r\nCONCAT(r2p_Value,'' '') AS Value\r\nFROM person_per,record2property_r2p\r\nWHERE per_ID = r2p_record_ID\r\nAND r2p_pro_ID = ~PropertyID~\r\nORDER BY per_LastName', 'Person by Property', 'Returns person records which are assigned the given property.', 1),
  (15, 'SELECT per_ID as AddToCart, CONCAT(''<a href=PersonView.php?PersonID='',per_ID,''>'',COALESCE(`per_FirstName`,''''),'' '',COALESCE(`per_MiddleName`,''''),'' '',COALESCE(`per_LastName`,''''),''</a>'') AS Name, fam_City as City, fam_State as State, fam_Zip as ZIP, per_HomePhone as HomePhone, per_Email as Email, per_WorkEmail as WorkEmail FROM person_per RIGHT JOIN family_fam ON family_fam.fam_id = person_per.per_fam_id WHERE ~searchwhat~ LIKE ''%~searchstring~%''', 'Advanced Search', 'Search by any part of Name, City, State, Zip, Home Phone, Email, or Work Email.', 1),
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
  (32, 'SELECT fam_Name, fam_Envelope, b.fun_Name as Fund_Name, a.plg_amount as Pledge from family_fam left join pledge_plg a on a.plg_famID = fam_ID and a.plg_FYID=~fyid~ and a.plg_PledgeOrPayment=\'Pledge\' and a.plg_amount>0 join donationfund_fun b on b.fun_ID = a.plg_fundID order by fam_Name, a.plg_fundID', 'Family Pledge by Fiscal Year', 'Pledge summary by family name for each fund for the selected fiscal year', 1),
  (100, 'SELECT a.per_ID as AddToCart, CONCAT(''<a href=PersonView.php?PersonID='',a.per_ID,''>'',a.per_FirstName,'' '',a.per_LastName,''</a>'') AS Name FROM person_per AS a LEFT JOIN person2volunteeropp_p2vo p2v1 ON (a.per_id = p2v1.p2vo_per_ID AND p2v1.p2vo_vol_ID = ~volopp1~) LEFT JOIN person2volunteeropp_p2vo p2v2 ON (a.per_id = p2v2.p2vo_per_ID AND p2v2.p2vo_vol_ID = ~volopp2~) WHERE p2v1.p2vo_per_ID=p2v2.p2vo_per_ID ORDER BY per_LastName', 'Volunteers', 'Find volunteers for who match two specific opportunity codes', 1),
  (200, 'SELECT a.per_ID as AddToCart, CONCAT(''<a href=PersonView.php?PersonID='',a.per_ID,''>'',a.per_FirstName,'' '',a.per_LastName,''</a>'') AS Name FROM person_per AS a LEFT JOIN person_custom pc ON a.per_id = pc.per_ID WHERE pc.~custom~=''~value~'' ORDER BY per_LastName', 'CustomSearch', 'Find people with a custom field value', 1),
  (201, 'SELECT per_ID as AddToCart, CONCAT(''<a href=PersonView.php?PersonID='',per_ID,''>'',per_FirstName,'',per_LastName,''</a>'') AS Name, per_LastName AS Lastname FROM person_per LEFT OUTER JOIN (SELECT event_attend.attend_id, event_attend.person_id FROM event_attend WHERE event_attend.event_id IN (~event~)) a ON person_per.per_ID = a.person_id WHERE a.attend_id is NULL ORDER BY person_per.per_LastName, person_per.per_FirstName', 'Missing people', 'Find people who didn''t attend an event', 1);

-- --------------------------------------------------------

--
-- Table structure for table `record2property_r2p`
--

CREATE TABLE `record2property_r2p` (
  `r2p_pro_ID` mediumint(8) unsigned NOT NULL default '0',
  `r2p_record_ID` mediumint(8) unsigned NOT NULL default '0',
  `r2p_Value` text NOT NULL
) ENGINE=InnoDB CHARACTER SET utf8 COLLATE utf8_unicode_ci;

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
) ENGINE=InnoDB CHARACTER SET utf8 COLLATE utf8_unicode_ci AUTO_INCREMENT=1 ;

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
) ENGINE=InnoDB CHARACTER SET utf8 COLLATE utf8_unicode_ci;

--
-- Dumping data for table `userconfig_ucfg`
--

INSERT INTO `userconfig_ucfg` (`ucfg_per_id`, `ucfg_id`, `ucfg_name`, `ucfg_value`, `ucfg_type`, `ucfg_tooltip`, `ucfg_permission`, `ucfg_cat`) VALUES

  (0, 0, 'bEmailMailto', '1', 'boolean', 'User permission to send email via mailto: links', 'TRUE', ''),
  (0, 1, 'sMailtoDelimiter', ',', 'text', 'Delimiter to separate emails in mailto: links', 'TRUE', ''),
  (0, 5, 'bCreateDirectory', '0', 'boolean', 'User permission to create directories', 'FALSE', 'SECURITY'),
  (0, 6, 'bExportCSV', '0', 'boolean', 'User permission to export CSV files', 'FALSE', 'SECURITY'),
  (0, 10, 'bAddEvent', '0', 'boolean', 'Allow user to add new event', 'FALSE', 'SECURITY'),
  (1, 0, 'bEmailMailto', '1', 'boolean', 'User permission to send email via mailto: links', 'TRUE', ''),
  (1, 1, 'sMailtoDelimiter', ',', 'text', 'user permission to send email via mailto: links', 'TRUE', ''),
  (1, 5, 'bCreateDirectory', '1', 'boolean', 'User permission to create directories', 'TRUE', ''),
  (1, 6, 'bExportCSV', '1', 'boolean', 'User permission to export CSV files', 'TRUE', '');


-- --------------------------------------------------------

--
-- Table structure for table `user_usr`
--

CREATE TABLE `user_usr` (
  `usr_per_ID` mediumint(9) unsigned NOT NULL default '0',
  `usr_Password` varchar(500) NOT NULL default '',
  `usr_NeedPasswordChange` tinyint(1) unsigned NOT NULL default '1',
  `usr_LastLogin` datetime NOT NULL default '2000-01-01 00:00:00',
  `usr_LoginCount` smallint(5) unsigned NOT NULL default '0',
  `usr_FailedLogins` tinyint(3) unsigned NOT NULL default '0',
  `usr_AddRecords` tinyint(1) unsigned NOT NULL default '0',
  `usr_EditRecords` tinyint(1) unsigned NOT NULL default '0',
  `usr_DeleteRecords` tinyint(1) unsigned NOT NULL default '0',
  `usr_MenuOptions` tinyint(1) unsigned NOT NULL default '0',
  `usr_ManageGroups` tinyint(1) unsigned NOT NULL default '0',
  `usr_Finance` tinyint(1) unsigned NOT NULL default '0',
  `usr_Notes` tinyint(1) unsigned NOT NULL default '0',
  `usr_Admin` tinyint(1) unsigned NOT NULL default '0',
  `usr_SearchLimit` tinyint(4) default '10',
  `usr_Style` varchar(50) default 'Style.css',
  `usr_showPledges` tinyint(1) NOT NULL default '0',
  `usr_showPayments` tinyint(1) NOT NULL default '0',
  `usr_showSince` date NOT NULL default '2016-01-01',
  `usr_defaultFY` mediumint(9) NOT NULL default '10',
  `usr_currentDeposit` mediumint(9) NOT NULL default '0',
  `usr_UserName` varchar(50) default NULL,
  `usr_apiKey` VARCHAR(255) default NULL,
  `usr_EditSelf` tinyint(1) unsigned NOT NULL default '0',
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
  `usr_Canvasser` tinyint(1) NOT NULL default '0',
  `usr_TwoFactorAuthSecret` VARCHAR(255) NULL,
  `usr_TwoFactorAuthLastKeyTimestamp` INT NULL,
  `usr_TwoFactorAuthRecoveryCodes` TEXT NULL,
  PRIMARY KEY  (`usr_per_ID`),
  UNIQUE KEY `usr_UserName` (`usr_UserName`),
  UNIQUE KEY `usr_apiKey` (`usr_apiKey`)
) ENGINE=InnoDB CHARACTER SET utf8 COLLATE utf8_unicode_ci;

--
-- Dumping data for table `user_usr`
--

INSERT INTO `user_usr` (`usr_per_ID`, `usr_Password`, `usr_NeedPasswordChange`, `usr_LastLogin`,
                        `usr_LoginCount`, `usr_FailedLogins`, `usr_AddRecords`, `usr_EditRecords`, `usr_DeleteRecords`,
                        `usr_MenuOptions`, `usr_ManageGroups`, `usr_Finance`, `usr_Notes`, `usr_Admin`,
                        `usr_SearchLimit`, `usr_Style`, `usr_showPledges`,
                        `usr_showPayments`, `usr_showSince`, `usr_defaultFY`, `usr_currentDeposit`, `usr_UserName`, `usr_EditSelf`,
                        `usr_CalStart`, `usr_CalEnd`, `usr_CalNoSchool1`, `usr_CalNoSchool2`, `usr_CalNoSchool3`, `usr_CalNoSchool4`,
                        `usr_CalNoSchool5`, `usr_CalNoSchool6`, `usr_CalNoSchool7`, `usr_CalNoSchool8`, `usr_SearchFamily`,
                        `usr_Canvasser`)
VALUES
  (1, '4bdf3fba58c956fc3991a1fde84929223f968e2853de596e49ae80a91499609b', 1, '2016-01-01 00:00:00', 0, 0, 0, 0, 0, 0, 0, 0, 0, 1, 10, 'skin-red', 0, 0, '2016-01-01', 10, 0, 'Admin', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0);


--
-- Table structure for table `user_settings`
--

CREATE TABLE `user_settings` (
  `user_id` int(11) NOT NULL,
  `setting_name` varchar(50) NOT NULL,
  `setting_value` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `user_settings`
--
ALTER TABLE `user_settings`
  ADD PRIMARY KEY (`user_id`,`setting_name`);


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
  PRIMARY KEY  (`vol_ID`)
) ENGINE=InnoDB CHARACTER SET utf8 COLLATE utf8_unicode_ci AUTO_INCREMENT=1 ;

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
) ENGINE=InnoDB CHARACTER SET utf8 COLLATE utf8_unicode_ci AUTO_INCREMENT=1 ;

--
-- Fundraiser support added 4/11/2009 Michael Wilt
--

CREATE TABLE `paddlenum_pn` (
  `pn_ID` mediumint(9) unsigned NOT NULL auto_increment,
  `pn_fr_ID` mediumint(9) unsigned,
  `pn_Num` mediumint(9) unsigned,
  `pn_per_ID` mediumint(9) NOT NULL default '0',
  PRIMARY KEY  (`pn_ID`)
) ENGINE=InnoDB CHARACTER SET utf8 COLLATE utf8_unicode_ci AUTO_INCREMENT=1 ;

CREATE TABLE `fundraiser_fr` (
  `fr_ID` mediumint(9) unsigned NOT NULL auto_increment,
  `fr_date` date default NULL,
  `fr_title` varchar(128) NOT NULL,
  `fr_description` text,
  `fr_EnteredBy` smallint(5) unsigned NOT NULL default '0',
  `fr_EnteredDate` date NOT NULL,
  PRIMARY KEY  (`fr_ID`),
  UNIQUE KEY `fr_ID` (`fr_ID`)
) ENGINE=InnoDB CHARACTER SET utf8 COLLATE utf8_unicode_ci AUTO_INCREMENT=1 ;

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
  PRIMARY KEY  (`di_ID`)
) ENGINE=InnoDB CHARACTER SET utf8 COLLATE utf8_unicode_ci AUTO_INCREMENT=1 ;

CREATE TABLE `multibuy_mb` (
  `mb_ID` mediumint(9) unsigned NOT NULL auto_increment,
  `mb_per_ID` mediumint(9) NOT NULL default '0',
  `mb_item_ID` mediumint(9) NOT NULL default '0',
  `mb_count` decimal(8,0) default NULL,
  PRIMARY KEY  (`mb_ID`)
) ENGINE=InnoDB CHARACTER SET utf8 COLLATE utf8_unicode_ci AUTO_INCREMENT=1 ;

CREATE TABLE `egive_egv` (
  `egv_egiveID` varchar(16) character set utf8 NOT NULL,
  `egv_famID` int(11) NOT NULL,
  `egv_DateEntered` datetime NOT NULL,
  `egv_DateLastEdited` datetime NOT NULL,
  `egv_EnteredBy` smallint(6) NOT NULL default '0',
  `egv_EditedBy` smallint(6) NOT NULL default '0'
) ENGINE=InnoDB CHARACTER SET utf8 COLLATE utf8_unicode_ci;

CREATE TABLE `kioskdevice_kdev` (
  `kdev_ID` mediumint(9) unsigned NOT NULL AUTO_INCREMENT,
  `kdev_GUIDHash` char(64) DEFAULT NULL,
  `kdev_Name` varchar(50) DEFAULT NULL,
  `kdev_deviceType` mediumint(9) NOT NULL DEFAULT 0,
  `kdev_lastHeartbeat` TIMESTAMP,
  `kdev_Accepted` BOOLEAN,
  `kdev_PendingCommands` varchar(50),

  PRIMARY KEY  (`kdev_ID`)
) ENGINE=InnoDB CHARACTER SET utf8 COLLATE utf8_unicode_ci;

CREATE TABLE `kioskassginment_kasm` (
  `kasm_ID` mediumint(9) unsigned NOT NULL AUTO_INCREMENT,
  `kasm_kdevId` mediumint(9) DEFAULT NULL,
  `kasm_AssignmentType` mediumint(9) DEFAULT NULL,
`kasm_EventId` mediumint(9) DEFAULT 0,

  PRIMARY KEY  (`kasm_ID`),
  UNIQUE KEY `kasm_ID` (`kasm_ID`)
) ENGINE=InnoDB CHARACTER SET utf8 COLLATE utf8_unicode_ci;



CREATE TABLE `tokens` (
  `token` VARCHAR(99) NOT NULL,
  `type` VARCHAR(50) NOT NULL,
  `reference_id` INT(9) NOT NULL,
  `valid_until_date` datetime NULL,
  `remainingUses` INT(2) NULL,
  PRIMARY KEY (`token`)
) ENGINE=InnoDB CHARACTER SET utf8 COLLATE utf8_unicode_ci;

CREATE TABLE `locations` (
  `location_id` INT NOT NULL AUTO_INCREMENT,
  `location_typeId` INT NOT NULL,
  `location_name` VARCHAR(256) NOT NULL,
  `location_address` VARCHAR(45) NOT NULL,
  `location_city` VARCHAR(45) NOT NULL,
  `location_state` VARCHAR(45) NOT NULL,
  `location_zip` VARCHAR(45) NOT NULL,
  `location_country` VARCHAR(45) NOT NULL,
  `location_phone` VARCHAR(45) NULL,
  `location_email` VARCHAR(45) NULL,
  `location_timzezone` VARCHAR(45) NULL,
  PRIMARY KEY (`location_id`)
) ENGINE=InnoDB CHARACTER SET utf8 COLLATE utf8_unicode_ci;


CREATE TABLE `church_location_person` (
  `location_id` INT NOT NULL,
  `person_id` INT NOT NULL,
  `order` INT NOT NULL,
  `person_location_role_id` INT NOT NULL,  #This will be referenced to user-defined roles such as clergey, pastor, member, etc for non-denominational use
  PRIMARY KEY (`location_id`, `person_id`)
) ENGINE=InnoDB CHARACTER SET utf8 COLLATE utf8_unicode_ci;

CREATE TABLE `church_location_role` (
  `location_id` INT NOT NULL,
  `role_id` INT NOT NULL,
  `role_order` INT NOT NULL,
  `role_title` INT NOT NULL,  #Thi
  PRIMARY KEY (`location_id`, `role_id`)
) ENGINE=InnoDB CHARACTER SET utf8 COLLATE utf8_unicode_ci;

--
-- Table structure for table `menu_links`
--

CREATE TABLE `menu_links` (
  `linkId` mediumint(8) unsigned NOT NULL AUTO_INCREMENT,
  `linkName` varchar(50) COLLATE utf8_unicode_ci DEFAULT NULL,
  `linkUri` text COLLATE utf8_unicode_ci NOT NULL,
  `linkOrder` INT NOT NULL,
  PRIMARY KEY (`linkId`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

--
-- Table structure for table `permissions`
--

CREATE TABLE `permissions` (
  `permission_id` int(11) NOT NULL,
  `permission_name` varchar(50) NOT NULL,
  `permission_desc` varchar(250) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Dumping data for table `permissions`
--

INSERT INTO `permissions` (`permission_id`, `permission_name`, `permission_desc`) VALUES
(1, 'addPeople', 'Add People'),
(3, 'updatePeople', 'Update People'),
(4, 'deletePeopleRecords', 'Delete People Records'),
(5, 'curdProperties', 'Manage Properties '),
(6, 'crudClassifications', 'Manage Classifications'),
(7, 'crudGroups', 'Manage Groups'),
(8, 'crudRoles', 'Manage Roles'),
(9, 'crudDonations', 'Manage Donations'),
(10, 'curdFinance', 'Manage Finance'),
(11, 'curdNotes', 'Manage Notes'),
(12, 'canvasser', 'Canvasser volunteer'),
(13, 'editSelf', 'Edit own family only'),
(14, 'emailMailto', 'Allow to see Mailto Links'),
(15, 'createDirectory', 'Create Directories'),
(16, 'exportCSV', 'Export CSV files'),
(18, 'crudEvent', 'Manage Events');

-- --------------------------------------------------------

--
-- Table structure for table `person_permission`
--

CREATE TABLE `person_permission` (
  `per_id` int(11) NOT NULL,
  `permission_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `person_roles`
--

CREATE TABLE `person_roles` (
  `per_id` int(11) NOT NULL,
  `role_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `roles`
--

CREATE TABLE `roles` (
  `role_id` int(11) NOT NULL,
  `role_name` varchar(50) NOT NULL,
  `role_desc` varchar(250) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Dumping data for table `roles`
--

INSERT INTO `roles` (`role_id`, `role_name`, `role_desc`) VALUES
(1, 'Welcome Committee', NULL),
(2, 'Clergy', NULL);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `permissions`
--
ALTER TABLE `permissions`
  ADD PRIMARY KEY (`permission_id`),
  ADD UNIQUE KEY `permission_name` (`permission_name`);

--
-- Indexes for table `person_permission`
--
ALTER TABLE `person_permission`
  ADD PRIMARY KEY (`per_id`,`permission_id`);

--
-- Indexes for table `person_roles`
--
ALTER TABLE `person_roles`
  ADD PRIMARY KEY (`per_id`,`role_id`);

--
-- Indexes for table `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`role_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `permissions`
--
ALTER TABLE `permissions`
  MODIFY `permission_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `roles`
--
ALTER TABLE `roles`
  MODIFY `role_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

update version_ver set ver_update_end = now();
