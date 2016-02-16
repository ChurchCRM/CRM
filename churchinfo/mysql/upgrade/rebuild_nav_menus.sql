-- phpMyAdmin SQL Dump
-- version 4.3.8
-- http://www.phpmyadmin.net
--
-- Host: localhost
-- Generation Time: Feb 13, 2015 at 08:45 AM
-- Server version: 5.5.40-36.1
-- PHP Version: 5.4.23

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8 */;

--
-- Table structure for table `menuconfig_mcf`
--

DROP TABLE IF EXISTS menuconfig_mcf;

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
(2, 'sundayschool-dash', 'sundayschool', 0, 'Dashboard', 'Dashboard', 'Reports/SundaySchoolClassList.php', '', 'bAll', NULL, 0, 0, NULL, 1, 2, NULL),
(3, 'sundayschool', 'root', 1, 'Sunday School', 'Sunday School', '', '', 'bAll', NULL, 0, 0, NULL, 1, 4, 'fa-stack-overflow'),
(7, 'editusers', 'admin', 0, 'System Users', 'System Users', 'UserList.php', '', 'bAdmin', NULL, 0, 0, NULL, 1, 1, NULL),
(9, 'custompersonfld', 'admin', 0, 'Edit Custom Person Fields', 'Edit Custom Person Fields', 'PersonCustomFieldsEditor.php', '', 'bAdmin', NULL, 0, 0, NULL, 1, 3, NULL),
(10, 'donationfund', 'admin', 0, 'Edit Donation Funds', 'Edit Donation Funds', 'DonationFundEditor.php', '', 'bAdmin', NULL, 0, 0, NULL, 1, 4, NULL),
(11, 'dbbackup', 'admin', 0, 'Backup Database', 'Backup Database', 'BackupDatabase.php', '', 'bAdmin', NULL, 0, 0, NULL, 1, 5, NULL),
(12, 'cvsimport', 'admin', 0, 'CSV Import', 'CSV Import', 'CSVImport.php', '', 'bAdmin', NULL, 0, 0, NULL, 1, 6, NULL),
(13, 'accessreport', 'admin', 0, 'Access report', 'Access report', 'AccessReport.php', '', 'bAdmin', NULL, 0, 0, NULL, 1, 7, NULL),
(14, 'generalsetting', 'admin', 0, 'Edit General Settings', 'Edit General Settings', 'SettingsGeneral.php', '', 'bAdmin', NULL, 0, 0, NULL, 1, 8, NULL),
(15, 'reportsetting', 'admin', 0, 'Edit Report Settings', 'Edit Report Settings', 'SettingsReport.php', '', 'bAdmin', NULL, 0, 0, NULL, 1, 9, NULL),
(17, 'envelopmgr', 'deposit', 0, 'Envelope Manager', 'Envelope Manager', 'ManageEnvelopes.php', '', 'bAdmin', NULL, 0, 0, NULL, 1, 11, NULL),
(18, 'register', 'admin', 0, 'Please select this option to register ChurchCRM after configuring.', 'Update registration', 'Register.php', '', 'bAdmin', NULL, 0, 0, NULL, 1, 12, NULL),
(19, 'people', 'root', 1, 'Members', 'Members', '', 'Members', 'bAll', NULL, 0, 0, NULL, 1, 3, 'fa-users'),
(20, 'membdash', 'people', 0, 'Dashboard', 'Dashboard', 'MembersDashboard.php', '', 'bAddRecords', NULL, 0, 0, NULL, 1, 1, NULL),
(21, 'newperson', 'people', 0, 'Add New Person', 'Add New Person', 'PersonEditor.php', '', 'bAddRecords', NULL, 0, 0, NULL, 1, 2, NULL),
(22, 'viewperson', 'people', 0, 'View All Persons', 'View All Persons', 'SelectList.php?mode=person', '', 'bAll', NULL, 0, 0, NULL, 1, 3, NULL),
(23, 'newfamily', 'people', 0, 'Add New Family', 'Add New Family', 'FamilyEditor.php', '', 'bAddRecords', NULL, 0, 0, NULL, 1, 4, NULL),
(24, 'viewfamily', 'people', 0, 'View All Families', 'View All Families', 'FamilyList.php', '', 'bAll', NULL, 0, 0, NULL, 1, 5, NULL),
(31, 'events', 'root', 1, 'Events', 'Events', '', 'Events', 'bAll', NULL, 0, 0, NULL, 1, 9, 'fa-ticket'),
(32, 'listevent', 'events', 0, 'List Church Events', 'List Church Events', 'ListEvents.php', 'List Church Events', 'bAll', NULL, 0, 0, NULL, 1, 1, NULL),
(33, 'addevent', 'events', 0, 'Add Church Event', 'Add Church Event', 'EventNames.php', 'Add Church Event', 'bAll', NULL, 0, 0, NULL, 1, 2, NULL),
(34, 'eventype', 'events', 0, 'List Event Types', 'List Event Types', 'EventNames.php', '', 'bAdmin', NULL, 0, 0, NULL, 1, 3, NULL),
(83, 'eventcheckin', 'events', 0, 'Check-in and Check-out', 'Check-in and Check-out', 'Checkin.php', '', 'bAll', NULL, 0, 0, NULL, 1, 4, NULL),
(35, 'deposit', 'root', 1, 'Deposit', 'Deposit', '', '', 'bFinance', NULL, 0, 0, NULL, 1, 10, 'fa-bank'),
(36, 'newdeposit', 'deposit', 0, 'Create New Deposit', 'Create New Deposit', 'DepositSlipEditor.php?DepositType=Bank', '', 'bFinance', NULL, 0, 0, NULL, 1, 1, NULL),
(37, 'viewdeposit', 'deposit', 0, 'View All Deposits', 'View All Deposits', 'FindDepositSlip.php', '', 'bFinance', NULL, 0, 0, NULL, 1, 2, NULL),
(38, 'depositreport', 'deposit', 0, 'Deposit Reports', 'Deposit Reports', 'FinancialReports.php', '', 'bFinance', NULL, 0, 0, NULL, 1, 3, NULL),
(40, 'depositslip', 'deposit', 0, 'Edit Deposit Slip', 'Edit Deposit Slip', 'DepositSlipEditor.php', '', 'bFinance', 'iCurrentDeposit', 1, 1, 'DepositSlipID', 1, 5, NULL),
(84, 'fundraiser', 'root', 1, 'Fundraiser', 'Fundraiser', '', '', 'bAll', NULL, 0, 0, NULL, 1, 11, 'fa-money'),
(85, 'newfundraiser', 'fundraiser', 0, 'Create New Fundraiser', 'Create New Fundraiser', 'FundRaiserEditor.php?FundRaiserID=-1', '', 'bAll', NULL, 0, 0, NULL, 1, 1, NULL),
(86, 'viewfundraiser', 'fundraiser', 0, 'View All Fundraisers', 'View All Fundraisers', 'FindFundRaiser.php', '', 'bAll', NULL, 0, 0, NULL, 1, 1, NULL),
(87, 'editfundraiser', 'fundraiser', 0, 'Edit Fundraiser', 'Edit Fundraiser', 'FundRaiserEditor.php', '', 'bAll', 'iCurrentFundraiser', 1, 1, 'FundRaiserID', 1, 5, NULL),
(88, 'viewbuyers', 'fundraiser', 0, 'View Buyers', 'View Buyers', 'PaddleNumList.php', '', 'bAll', 'iCurrentFundraiser', 1, 1, 'FundRaiserID', 1, 5, NULL),
(89, 'adddonors', 'fundraiser', 0, 'Add Donors to Buyer List', 'Add Donors to Buyer List', 'AddDonors.php', '', 'bAll', 'iCurrentFundraiser', 1, 1, 'FundRaiserID', 1, 5, NULL),
(47, 'report', 'root', 1, 'Data/Reports', 'Data/Reports', '', '', 'bAll', NULL, 0, 0, NULL, 1, 8, 'fa-file-pdf-o'),
(48, 'cvsexport', 'report', 0, 'CSV Export Records', 'CSV Export Records', 'CSVExport.php', '', 'bAll', NULL, 0, 0, NULL, 1, 1, NULL),
(49, 'querymenu', 'report', 0, 'Query Menu', 'Query Menu', 'QueryList.php', '', 'bAll', NULL, 0, 0, NULL, 1, 2, NULL),
(50, 'reportmenu', 'report', 0, 'Reports Menu', 'Reports Menu', 'ReportList.php', '', 'bAll', NULL, 0, 0, NULL, 1, 3, NULL),
(51, 'groups', 'root', 1, 'Groups', 'Groups', '', '', 'bAll', NULL, 0, 0, NULL, 1, 7, 'fa-tag'),
(52, 'listgroups', 'groups', 0, 'List Groups', 'List Groups', 'GroupList.php', '', 'bAll', NULL, 0, 0, NULL, 1, 1, NULL),
(53, 'newgroup', 'groups', 0, 'Add a New Group', 'Add a New Group', 'GroupEditor.php', '', 'bManageGroups', NULL, 0, 0, NULL, 1, 2, NULL),
(54, 'editgroup', 'groups', 0, 'Edit Group Types', 'Edit Group Types', 'OptionManager.php?mode=grptypes', '', 'bMenuOptions', NULL, 0, 0, NULL, 1, 3, NULL),
(55, 'assigngroup', 'groups', 0, 'Group Assignment Helper', 'Group Assignment Helper', 'SelectList.php?mode=groupassign', '', 'bAll', NULL, 0, 0, NULL, 1, 4, NULL),
(56, 'properties', 'root', 1, 'Properties', 'Properties', '', '', 'bAll', NULL, 0, 0, NULL, 1, 12, 'fa-cogs'),
(57, 'peopleproperty', 'properties', 0, 'People Properties', 'People Properties', 'PropertyList.php?Type=p', '', 'bAll', NULL, 0, 0, NULL, 1, 1, NULL),
(58, 'familyproperty', 'properties', 0, 'Family Properties', 'Family Properties', 'PropertyList.php?Type=f', '', 'bAll', NULL, 0, 0, NULL, 1, 2, NULL),
(59, 'groupproperty', 'properties', 0, 'Group Properties', 'Group Properties', 'PropertyList.php?Type=g', '', 'bAll', NULL, 0, 0, NULL, 1, 3, NULL),
(60, 'propertytype', 'properties', 0, 'Property Types', 'Property Types', 'PropertyTypeList.php', '', 'bAll', NULL, 0, 0, NULL, 1, 4, NULL),
(82, 'customfamilyfld', 'admin', 0, 'Edit Custom Family Fields', 'Edit Custom Family Fields', 'FamilyCustomFieldsEditor.php', '', 'bAdmin', NULL, 0, 0, NULL, 1, 3, NULL),
(91, 'seeddata', 'admin', 0, 'Generate Seed Data', 'Generate Seed Data', 'GenerateSeedData.php', '', 'bAdmin', NULL, 0, 0, NULL, 1, 14, NULL),
(100, 'mailchimp', 'root', 0, 'MailChimp', 'MailChimp', 'mailchimp/MailChimpDashboard.php', '', 'bAll', NULL, 0, 0, NULL, 1, 4, 'fa-envelope'),
(101, 'dbrestore', 'admin', 0, 'Restore Database', 'Restore Database', 'RestoreDatabase.php', '', 'bAdmin', NULL, 0, 0, NULL, 1, 5, NULL);

UPDATE menuconfig_mcf SET content=content_english;
--
-- Indexes for dumped tables
--


--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `menuconfig_mcf`
--
ALTER TABLE `menuconfig_mcf`
  MODIFY `mid` int(11) NOT NULL AUTO_INCREMENT,AUTO_INCREMENT=102;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
