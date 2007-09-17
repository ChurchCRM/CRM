
CREATE TABLE IF NOT EXISTS autopayment_aut (
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

  `aut_BankName` varchar (50) default NULL,
  `aut_Route` varchar (30) default NULL,
  `aut_Account` varchar (30) default NULL,

  `aut_DateLastEdited` datetime default NULL,
  `aut_EditedBy` smallint(5) unsigned default '0',

  `aut_Serial` mediumint(9) NOT NULL default '1',

  PRIMARY KEY  (`aut_ID`),
  UNIQUE KEY `aut_ID` (`aut_ID`)
) TYPE=MyISAM;

CREATE TABLE IF NOT EXISTS `canvassdata_can` (
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
) TYPE=MyISAM;

CREATE TABLE IF NOT EXISTS `deposit_dep` (
  `dep_ID` mediumint(9) unsigned NOT NULL auto_increment,
  `dep_Date` date default NULL,
  `dep_Comment` text,
  `dep_EnteredBy` mediumint(9) unsigned default NULL,
  `dep_Closed` tinyint(1) NOT NULL default '0',
  `dep_Type` enum('Bank','CreditCard','BankDraft') NOT NULL default 'Bank',
  PRIMARY KEY  (`dep_ID`)
) TYPE=MyISAM PACK_KEYS=0;

CREATE TABLE IF NOT EXISTS `donationfund_fun` (
  `fun_ID` tinyint(3) NOT NULL auto_increment,
  `fun_Active` enum('true','false') NOT NULL default 'true',
  `fun_Name` varchar(30) default NULL,
  `fun_Description` varchar(100) default NULL,
  PRIMARY KEY  (`fun_ID`),
  UNIQUE KEY `fun_ID` (`fun_ID`)
) TYPE=MyISAM;

INSERT IGNORE INTO `donationfund_fun` (fun_ID, fun_Active, fun_Name, fun_Description) VALUES (1, 'true', 'Pledges', 'Pledge income for the operating budget');

CREATE TABLE IF NOT EXISTS `volunteeropportunity_vol` (
  `vol_ID` tinyint(3) NOT NULL auto_increment,
  `vol_Active` enum('true','false') NOT NULL default 'true',
  `vol_Name` varchar(30) default NULL,
  `vol_Description` varchar(100) default NULL,
  PRIMARY KEY  (`vol_ID`),
  UNIQUE KEY `vol_ID` (`vol_ID`)
) TYPE=MyISAM;

CREATE TABLE IF NOT EXISTS `person2volunteeropp_p2vo` (
  `p2vo_ID` mediumint(9) NOT NULL auto_increment,
  `p2vo_per_ID` mediumint(9),
  `p2vo_vol_ID` mediumint(9),
  PRIMARY KEY (`p2vo_ID`),
  UNIQUE KEY (`p2vo_ID`)
) TYPE=MyISAM;

CREATE TABLE IF NOT EXISTS `family_fam` (
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
  KEY fam_ID (`fam_ID`)
) TYPE=MyISAM;

CREATE TABLE IF NOT EXISTS `group_grp` (
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
) TYPE=MyISAM;


CREATE TABLE IF NOT EXISTS `note_nte` (
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
) TYPE=MyISAM;


CREATE TABLE IF NOT EXISTS `person2group2role_p2g2r` (
  `p2g2r_per_ID` mediumint(8) unsigned NOT NULL default '0',
  `p2g2r_grp_ID` mediumint(8) unsigned NOT NULL default '0',
  `p2g2r_rle_ID` mediumint(8) unsigned NOT NULL default '0',
  PRIMARY KEY  (`p2g2r_per_ID`,`p2g2r_grp_ID`),
  KEY `p2g2r_per_ID` (`p2g2r_per_ID`,`p2g2r_grp_ID`,`p2g2r_rle_ID`)
) TYPE=MyISAM;


CREATE TABLE IF NOT EXISTS `person_per` (
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
  KEY per_ID (`per_ID`)
) TYPE=MyISAM;


INSERT IGNORE INTO `person_per` VALUES (1,NULL,'ChurchInfo',NULL,'Admin',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,0,0,0000,NULL,0,0,0,0,NULL,NULL,'2004-08-25 18:00:00',0,0,NULL,0);

CREATE TABLE IF NOT EXISTS `pledge_plg` (
  `plg_plgID` mediumint(9) NOT NULL auto_increment,
  `plg_FamID` mediumint(9) default NULL,
  `plg_FYID` mediumint(9) default NULL,
  `plg_date` date default NULL,
  `plg_amount` decimal(8,2) default NULL,
  `plg_schedule` enum('Monthly','Quarterly','Once','Other') default NULL,
  `plg_method` enum('CREDITCARD','CHECK','CASH','BANKDRAFT') default NULL,
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
  `plg_NonDeductible` DECIMAL( 8, 2 ) NOT NULL,
  PRIMARY KEY  (`plg_plgID`)
) TYPE=MyISAM;

CREATE TABLE IF NOT EXISTS `property_pro` (
  `pro_ID` mediumint(8) unsigned NOT NULL auto_increment,
  `pro_Class` varchar(10) NOT NULL default '',
  `pro_prt_ID` mediumint(8) unsigned NOT NULL default '0',
  `pro_Name` varchar(200) NOT NULL default '0',
  `pro_Description` text NOT NULL,
  `pro_Prompt` varchar(255) default NULL,
  PRIMARY KEY  (`pro_ID`),
  UNIQUE KEY pro_ID (`pro_ID`),
  KEY pro_ID_2 (`pro_ID`)
) TYPE=MyISAM;


INSERT IGNORE INTO `property_pro` VALUES (1,'p',1,'Disabled','has a disability.','What is the nature of the disability?');
INSERT IGNORE INTO `property_pro` VALUES (2,'f',2,'Single Parent','is a single-parent household.','');
INSERT IGNORE INTO `property_pro` VALUES (3,'g',3,'Youth','is youth-oriented.','');


CREATE TABLE IF NOT EXISTS `propertytype_prt` (
  `prt_ID` mediumint(9) NOT NULL auto_increment,
  `prt_Class` varchar(10) NOT NULL default '',
  `prt_Name` varchar(50) NOT NULL default '',
  `prt_Description` text NOT NULL,
  PRIMARY KEY  (`prt_ID`),
  UNIQUE KEY prt_ID (`prt_ID`),
  KEY prt_ID_2 (`prt_ID`)
) TYPE=MyISAM;


INSERT IGNORE INTO `propertytype_prt` VALUES (1,'p','General','General Person Properties');
INSERT IGNORE INTO `propertytype_prt` VALUES (2,'f','General','General Family Properties');
INSERT IGNORE INTO `propertytype_prt` VALUES (3,'g','General','General Group Properties');


CREATE TABLE IF NOT EXISTS `query_qry` (
  `qry_ID` mediumint(8) unsigned NOT NULL auto_increment,
  `qry_SQL` text NOT NULL,
  `qry_Name` varchar(255) NOT NULL default '',
  `qry_Description` text NOT NULL,
  `qry_Count` tinyint(1) unsigned NOT NULL default '0',
  PRIMARY KEY  (`qry_ID`),
  UNIQUE KEY qry_ID (`qry_ID`),
  KEY qry_ID_2 (`qry_ID`)
) TYPE=MyISAM;

INSERT IGNORE INTO `query_qry` VALUES (2,'SELECT COUNT(per_ID)\nAS \'Count\'\nFROM person_per','Person Count','Returns the total number of people in the database.',0);
INSERT IGNORE INTO `query_qry` VALUES (3,'SELECT CONCAT(\'<a href=FamilyView.php?FamilyID=\',fam_ID,\'>\',fam_Name,\'</a>\') AS \'Family Name\', COUNT(*) AS \'No.\'\nFROM person_per\nINNER JOIN family_fam\nON fam_ID = per_fam_ID\nGROUP BY per_fam_ID\nORDER BY \'No.\' DESC','Family Member Count','Returns each family and the total number of people assigned to them.',0);
INSERT IGNORE INTO `query_qry` VALUES (4,'SELECT per_ID as AddToCart,CONCAT(\'<a href=PersonView.php?PersonID=\',per_ID,\'>\',per_FirstName,\' \',per_LastName,\'</a>\') AS Name, CONCAT(per_BirthMonth,\'/\',per_BirthDay,\'/\',per_BirthYear) AS \'Birth Date\', \nYEAR(CURRENT_DATE) - per_BirthYear AS \'Age\'\nFROM person_per\nWHERE\nDATE_ADD(CONCAT(per_BirthYear,\'-\',per_BirthMonth,\'-\',per_BirthDay),INTERVAL ~min~ YEAR) <= CURDATE()\nAND\nDATE_ADD(CONCAT(per_BirthYear,\'-\',per_BirthMonth,\'-\',per_BirthDay),INTERVAL ~max~ YEAR) >= CURDATE()','Person by Age','Returns any person records with ages between two given ages.',1);
INSERT IGNORE INTO `query_qry` VALUES (6,'SELECT COUNT(per_ID) AS Total FROM person_per WHERE per_Gender = ~gender~','Total By Gender','Total of records matching a given gender.',0);
INSERT IGNORE INTO `query_qry` VALUES (7,'SELECT per_ID as AddToCart, CONCAT(per_FirstName,\' \',per_LastName) AS Name FROM person_per WHERE per_fmr_ID = ~role~ AND per_Gender = ~gender~','Person by Role and Gender','Selects person records with the family role and gender specified.',1);
INSERT IGNORE INTO `query_qry` VALUES (9,'SELECT \r\nper_ID as AddToCart, \r\nCONCAT(per_FirstName,\' \',per_LastName) AS Name, \r\nCONCAT(r2p_Value,\' \') AS Value\r\nFROM person_per,record2property_r2p\r\nWHERE per_ID = r2p_record_ID\r\nAND r2p_pro_ID = ~PropertyID~\r\nORDER BY per_LastName','Person by Property','Returns person records which are assigned the given property.',1);
INSERT IGNORE INTO `query_qry` VALUES (15, 'SELECT per_ID as AddToCart, CONCAT(\'<a href=PersonView.php?PersonID=\',per_ID,\'>\',per_FirstName,\' \',per_MiddleName,\' \',per_LastName,\'</a>\') AS Name, \r\nper_City as City, per_State as State,\r\nper_Zip as ZIP, per_HomePhone as HomePhone\r\nFROM person_per \r\nWHERE ~searchwhat~ LIKE \'%~searchstring~%\'', 'Advanced Search', 'Search by any part of Name, City, State, Zip, or Home Phone.', 1);
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

INSERT IGNORE INTO `query_qry` VALUES (30,'SELECT per_ID as AddToCart, CONCAT(per_FirstName,\' \',per_LastName) AS Name, fam_address1, fam_city, fam_state, fam_zip FROM person_per join family_fam on per_fam_id=fam_id where per_fmr_id<>3 and per_fam_id in (select fam_id from family_fam inner join pledge_plg a on a.plg_famID=fam_ID and a.plg_FYID=~fyid1~ and a.plg_amount>0) and per_fam_id not in (select fam_id from family_fam inner join pledge_plg b on b.plg_famID=fam_ID and b.plg_FYID=~fyid2~ and b.plg_amount>0)','Missing pledges','Find people who pledged one year but not another',1);

INSERT IGNORE INTO `query_qry` VALUES (31, 'select per_ID as AddToCart, per_FirstName, per_LastName, per_email from person_per, autopayment_aut where aut_famID=per_fam_ID and aut_CreditCard!="" and per_email!="" and (per_fmr_ID=1 or per_fmr_ID=2 or per_cls_ID=1)', 'Credit Cart People', 'People who are configured to pay by credit card.', 0);

CREATE TABLE IF NOT EXISTS `queryparameteroptions_qpo` (
  `qpo_ID` smallint(5) unsigned NOT NULL auto_increment,
  `qpo_qrp_ID` mediumint(8) unsigned NOT NULL default '0',
  `qpo_Display` varchar(50) NOT NULL default '',
  `qpo_Value` varchar(50) NOT NULL default '',
  PRIMARY KEY  (`qpo_ID`),
  UNIQUE KEY `qpo_ID` (`qpo_ID`)
) TYPE=MyISAM;


INSERT IGNORE INTO `queryparameteroptions_qpo` VALUES (1,4,'Male','1');
INSERT IGNORE INTO `queryparameteroptions_qpo` VALUES (2,4,'Female','2');
INSERT IGNORE INTO `queryparameteroptions_qpo` VALUES (3,6,'Male','1');
INSERT IGNORE INTO `queryparameteroptions_qpo` VALUES (4,6,'Female','2');
INSERT IGNORE INTO `queryparameteroptions_qpo` VALUES (5, 15, 'Name', 'CONCAT(per_FirstName,per_MiddleName,per_LastName)');
INSERT IGNORE INTO `queryparameteroptions_qpo` VALUES (6, 15, 'Zip Code', 'per_Zip');
INSERT IGNORE INTO `queryparameteroptions_qpo` VALUES (7, 15, 'State', 'per_State');
INSERT IGNORE INTO `queryparameteroptions_qpo` VALUES (8, 15, 'City', 'per_City');
INSERT IGNORE INTO `queryparameteroptions_qpo` VALUES (9, 15, 'Home Phone', 'per_HomePhone');

INSERT IGNORE INTO `queryparameteroptions_qpo` VALUES (10, 27, '2005/2006', '10');
INSERT IGNORE INTO `queryparameteroptions_qpo` VALUES (11, 27, '2006/2007', '11');
INSERT IGNORE INTO `queryparameteroptions_qpo` VALUES (12, 27, '2007/2008', '12');
INSERT IGNORE INTO `queryparameteroptions_qpo` VALUES (13, 27, '2008/2009', '13');

INSERT IGNORE INTO `queryparameteroptions_qpo` VALUES (14, 28, '2005/2006', '10');
INSERT IGNORE INTO `queryparameteroptions_qpo` VALUES (15, 28, '2006/2007', '11');
INSERT IGNORE INTO `queryparameteroptions_qpo` VALUES (16, 28, '2007/2008', '12');
INSERT IGNORE INTO `queryparameteroptions_qpo` VALUES (17, 28, '2008/2009', '13');

INSERT IGNORE INTO `queryparameteroptions_qpo` VALUES (18, 30, '2005/2006', '10');
INSERT IGNORE INTO `queryparameteroptions_qpo` VALUES (19, 30, '2006/2007', '11');
INSERT IGNORE INTO `queryparameteroptions_qpo` VALUES (20, 30, '2007/2008', '12');
INSERT IGNORE INTO `queryparameteroptions_qpo` VALUES (21, 30, '2008/2009', '13');

INSERT IGNORE INTO `queryparameteroptions_qpo` VALUES (22, 31, '2005/2006', '10');
INSERT IGNORE INTO `queryparameteroptions_qpo` VALUES (23, 31, '2006/2007', '11');
INSERT IGNORE INTO `queryparameteroptions_qpo` VALUES (24, 31, '2007/2008', '12');
INSERT IGNORE INTO `queryparameteroptions_qpo` VALUES (25, 31, '2008/2009', '13');

CREATE TABLE IF NOT EXISTS `queryparameters_qrp` (
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
  UNIQUE KEY qrp_ID (`qrp_ID`),
  KEY qrp_ID_2 (`qrp_ID`),
  KEY qrp_qry_ID (`qrp_qry_ID`)
) TYPE=MyISAM;

INSERT IGNORE INTO `queryparameters_qrp` VALUES (1,4,0,NULL,'Minimum Age','The minimum age for which you want records returned.','min','0',0,5,'n',120,0,NULL,NULL);
INSERT IGNORE INTO `queryparameters_qrp` VALUES (2,4,0,NULL,'Maximum Age','The maximum age for which you want records returned.','max','120',1,5,'n',120,0,NULL,NULL);
INSERT IGNORE INTO `queryparameters_qrp` VALUES (4,6,1,'','Gender','The desired gender to search the database for.','gender','1',1,0,'',0,0,0,0);
INSERT IGNORE INTO `queryparameters_qrp` VALUES (5,7,2,'SELECT lst_OptionID as Value, lst_OptionName as Display FROM list_lst WHERE lst_ID=2 ORDER BY lst_OptionSequence','Family Role','Select the desired family role.','role','1',0,0,'',0,0,0,0);
INSERT IGNORE INTO `queryparameters_qrp` VALUES (6,7,1,'','Gender','The gender for which you would like records returned.','gender','1',1,0,'',0,0,0,0);
INSERT IGNORE INTO `queryparameters_qrp` VALUES (8,9,2,'SELECT pro_ID AS Value, pro_Name as Display \r\nFROM property_pro\r\nWHERE pro_Class= \'p\' \r\nORDER BY pro_Name ','Property','The property for which you would like person records returned.','PropertyID','0',1,0,'',0,0,0,0);
INSERT IGNORE INTO `queryparameters_qrp` VALUES (9, 10, 2, 'SELECT distinct don_date as Value, don_date as Display FROM donations_don ORDER BY don_date ASC', 'Beginning Date', 'Please select the beginning date to calculate total contributions for each member (i.e. YYYY-MM-DD). NOTE: You can only choose dates that conatain donations.', 'startdate', '1', 1, 0, '0', 0, 0, 0, 0);
INSERT IGNORE INTO `queryparameters_qrp` VALUES (10, 10, 2, 'SELECT distinct don_date as Value, don_date as Display FROM donations_don\r\nORDER BY don_date DESC', 'Ending Date', 'Please enter the last date to calculate total contributions for each member (i.e. YYYY-MM-DD).', 'enddate', '1', 1, 0, '', 0, 0, 0, 0);
INSERT IGNORE INTO `queryparameters_qrp` VALUES (14, 15, 0, '', 'Search', 'Enter any part of the following: Name, City, State, Zip, or Home Phone.', 'searchstring', '', 1, 0, '', 0, 0, 0, 0);
INSERT IGNORE INTO `queryparameters_qrp` VALUES (15, 15, 1, '', 'Field', 'Select field to search for.', 'searchwhat', '1', 1, 0, '', 0, 0, 0, 0);
INSERT IGNORE INTO `queryparameters_qrp` VALUES (16, 11, 2, 'SELECT distinct don_date as Value, don_date as Display FROM donations_don ORDER BY don_date ASC', 'Beginning Date', 'Please select the beginning date to calculate total contributions for each member (i.e. YYYY-MM-DD). NOTE: You can only choose dates that conatain donations.', 'startdate', '1', 1, 0, '0', 0, 0, 0, 0);
INSERT IGNORE INTO `queryparameters_qrp` VALUES (17, 11, 2, 'SELECT distinct don_date as Value, don_date as Display FROM donations_don\r\nORDER BY don_date DESC', 'Ending Date', 'Please enter the last date to calculate total contributions for each member (i.e. YYYY-MM-DD).', 'enddate', '1', 1, 0, '', 0, 0, 0, 0);
INSERT IGNORE INTO `queryparameters_qrp` VALUES (18,18,0,'','Month','The birthday month for which you would like records returned.','birthmonth','1',1,0,'',12,1,1,2);
INSERT IGNORE INTO `queryparameters_qrp` VALUES (19,19,2,'SELECT grp_ID AS Value, grp_Name AS Display FROM group_grp ORDER BY grp_Type','Class','The sunday school class for which you would like records returned.','group','1',1,0,'',12,1,1,2);
INSERT IGNORE INTO `queryparameters_qrp` VALUES (20,20,2,'SELECT grp_ID AS Value, grp_Name AS Display FROM group_grp ORDER BY grp_Type','Class','The sunday school class for which you would like records returned.','group','1',1,0,'',12,1,1,2);
INSERT IGNORE INTO `queryparameters_qrp` VALUES (21,21,2,'SELECT grp_ID AS Value, grp_Name AS Display FROM group_grp ORDER BY grp_Type','Registered students','Group of registered students','group','1',1,0,'',12,1,1,2);
INSERT IGNORE INTO `queryparameters_qrp` VALUES (22,22,0,'','Month','The membership anniversary month for which you would like records returned.','membermonth','1',1,0,'',12,1,1,2);
INSERT IGNORE INTO `queryparameters_qrp` VALUES (25,25,2,'SELECT vol_ID AS Value, vol_Name AS Display FROM volunteeropportunity_vol ORDER BY vol_Name','Volunteer opportunities','Choose a volunteer opportunity','volopp','1',1,0,'',12,1,1,2);

INSERT IGNORE INTO `queryparameters_qrp` VALUES (26,26,0,'','Months','Number of months since becoming a friend','friendmonths','1',1,0,'',24,1,1,2);

INSERT IGNORE INTO `queryparameters_qrp` VALUES (27,28,1,'','First Fiscal Year','First fiscal year for comparison','fyid1','9',1,0,'',12,9,0,0);
INSERT IGNORE INTO `queryparameters_qrp` VALUES (28,28,1,'','Second Fiscal Year','Second fiscal year for comparison','fyid2','9',1,0,'',12,9,0,0);

INSERT IGNORE INTO `queryparameters_qrp` VALUES (30,30,1,'','First Fiscal Year','Pledged this year','fyid1','9',1,0,'',12,9,0,0);
INSERT IGNORE INTO `queryparameters_qrp` VALUES (31,30,1,'','Second Fiscal Year','but not this year','fyid2','9',1,0,'',12,9,0,0);

INSERT IGNORE INTO `query_qry` VALUES (100, 'SELECT a.per_ID as AddToCart, CONCAT(''<a href=PersonView.php?PersonID='',a.per_ID,''>'',a.per_FirstName,'' '',a.per_LastName,''</a>'') AS Name FROM person_per AS a LEFT JOIN person2volunteeropp_p2vo p2v1 ON (a.per_id = p2v1.p2vo_per_ID AND p2v1.p2vo_vol_ID = ~volopp1~) LEFT JOIN person2volunteeropp_p2vo p2v2 ON (a.per_id = p2v2.p2vo_per_ID AND p2v2.p2vo_vol_ID = ~volopp2~) WHERE p2v1.p2vo_per_ID=p2v2.p2vo_per_ID ORDER BY per_LastName', 'Volunteers', 'Find volunteers for who match two specific opportunity codes', 1);
INSERT IGNORE INTO `queryparameters_qrp` VALUES (100, 100, 2, 'SELECT vol_ID AS Value, vol_Name AS Display FROM volunteeropportunity_vol ORDER BY vol_Name', 'Volunteer opportunities', 'First volunteer opportunity choice', 'volopp1', '1', 1, 0, '', 12, 1, 1, 2);
INSERT IGNORE INTO `queryparameters_qrp` VALUES (101, 100, 2, 'SELECT vol_ID AS Value, vol_Name AS Display FROM volunteeropportunity_vol ORDER BY vol_Name', 'Volunteer opportunities', 'Second volunteer opportunity choice', 'volopp2', '1', 1, 0, '', 12, 1, 1, 2);

CREATE TABLE IF NOT EXISTS `record2property_r2p` (
  `r2p_pro_ID` mediumint(8) unsigned NOT NULL default '0',
  `r2p_record_ID` MEDIUMINT UNSIGNED NOT NULL DEFAULT '0',
  `r2p_Value` text NOT NULL
) TYPE=MyISAM;


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
) TYPE=MyISAM;

CREATE TABLE IF NOT EXISTS `user_usr` (
  `usr_per_ID` mediumint(9) unsigned NOT NULL default '0',
  `usr_Password` varchar(50) NOT NULL default '',
  `usr_NeedPasswordChange` tinyint(3) unsigned NOT NULL default '0',
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
) TYPE=MyISAM;

INSERT IGNORE INTO `user_usr` (usr_per_ID, usr_Password, usr_NeedPasswordChange,
usr_LastLogin, usr_LoginCount, usr_FailedLogins, usr_AddRecords, usr_EditRecords,
usr_DeleteRecords, usr_MenuOptions, usr_ManageGroups, usr_Finance, usr_Communication,
usr_Notes, usr_Admin, usr_Workspacewidth, usr_BaseFontSize, usr_SearchLimit, usr_Style,
usr_UserName, usr_defaultFY, usr_currentDeposit)
VALUES (1,'1a7ac1b904382aaf0ac67b4f00e7b93f',1,
'0000-00-00 00:00:00',0,0,1,1,
1,1,1,1,1,
1,1,580,9,10,'Style.css',
'Admin', 10, 0);

CREATE TABLE IF NOT EXISTS `groupprop_master` (
  `grp_ID` mediumint(9) unsigned NOT NULL default '0',
  `prop_ID` tinyint(3) unsigned NOT NULL default '0',
  `prop_Field` varchar(5) NOT NULL default '0',
  `prop_Name` varchar(40) default NULL,
  `prop_Description` varchar(60) default NULL,
  `type_ID` smallint(5) unsigned NOT NULL default '0',
  `prop_Special` mediumint(9) unsigned default NULL,
  `prop_PersonDisplay` enum('false','true') NOT NULL default 'false'
) TYPE=MyISAM COMMENT='Group-specific properties order, name, description, type';

CREATE TABLE IF NOT EXISTS `person_custom_master` (
  `custom_Order` smallint(6) NOT NULL default '0',
  `custom_Field` varchar(5) NOT NULL default '',
  `custom_Name` varchar(40) NOT NULL default '',
  `custom_Special` mediumint(8) unsigned default NULL,
  `custom_Side` enum('left','right') NOT NULL default 'left',
  `type_ID` tinyint(4) NOT NULL default '0'
) TYPE=MyISAM;

CREATE TABLE IF NOT EXISTS `person_custom` (
  `per_ID` mediumint(9) NOT NULL default '0',
  PRIMARY KEY  (`per_ID`)
) TYPE=MyISAM;

CREATE TABLE IF NOT EXISTS `list_lst` (
  `lst_ID` mediumint(8) unsigned NOT NULL default '0',
  `lst_OptionID` mediumint(8) unsigned NOT NULL default '0',
  `lst_OptionSequence` tinyint(3) unsigned NOT NULL default '0',
  `lst_OptionName` varchar(50) NOT NULL default ''
) TYPE=MyISAM;

# Sample data for member classifications
INSERT IGNORE INTO `list_lst` VALUES (1, 1, 1, 'Member');
INSERT IGNORE INTO `list_lst` VALUES (1, 2, 2, 'Regular Attender');
INSERT IGNORE INTO `list_lst` VALUES (1, 3, 3, 'Guest');
INSERT IGNORE INTO `list_lst` VALUES (1, 5, 4, 'Non-Attender');
INSERT IGNORE INTO `list_lst` VALUES (1, 4, 5, 'Non-Attender (staff)');

# Sample data for family roles
INSERT IGNORE INTO `list_lst` VALUES (2, 1, 1, 'Head of Household');
INSERT IGNORE INTO `list_lst` VALUES (2, 2, 2, 'Spouse');
INSERT IGNORE INTO `list_lst` VALUES (2, 3, 3, 'Child');
INSERT IGNORE INTO `list_lst` VALUES (2, 4, 4, 'Other Relative');
INSERT IGNORE INTO `list_lst` VALUES (2, 5, 5, 'Non Relative');

# Sample data for group types
INSERT IGNORE INTO `list_lst` VALUES (3, 1, 1, 'Ministry');
INSERT IGNORE INTO `list_lst` VALUES (3, 2, 2, 'Team');
INSERT IGNORE INTO `list_lst` VALUES (3, 3, 3, 'Bible Study');
INSERT IGNORE INTO `list_lst` VALUES (3, 4, 4, 'Sunday School Class');

# Insert the custom-field  group-property types
INSERT IGNORE INTO `list_lst` VALUES (4, 1, 1, 'True / False');
INSERT IGNORE INTO `list_lst` VALUES (4, 2, 2, 'Date');
INSERT IGNORE INTO `list_lst` VALUES (4, 3, 3, 'Text Field (50 char)');
INSERT IGNORE INTO `list_lst` VALUES (4, 4, 4, 'Text Field (100 char)');
INSERT IGNORE INTO `list_lst` VALUES (4, 5, 5, 'Text Field (Long)');
INSERT IGNORE INTO `list_lst` VALUES (4, 6, 6, 'Year');
INSERT IGNORE INTO `list_lst` VALUES (4, 7, 7, 'Season');
INSERT IGNORE INTO `list_lst` VALUES (4, 8, 8, 'Number');
INSERT IGNORE INTO `list_lst` VALUES (4, 9, 9, 'Person from Group');
INSERT IGNORE INTO `list_lst` VALUES (4, 10, 10, 'Money');
INSERT IGNORE INTO `list_lst` VALUES (4, 11, 11, 'Phone Number');
INSERT IGNORE INTO `list_lst` VALUES (4, 12, 12, 'Custom Drop-Down List');

CREATE TABLE IF NOT EXISTS `whycame_why` (
  `why_ID` mediumint(9) NOT NULL auto_increment,
  `why_per_ID` mediumint(9) NOT NULL default '0',
  `why_join` text NOT NULL,
  `why_come` text NOT NULL,
  `why_suggest` text NOT NULL,
  `why_hearOfUs` text NOT NULL,
  PRIMARY KEY  (`why_ID`)
) TYPE=MyISAM;

CREATE TABLE IF NOT EXISTS `event_attend` (
  `event_id` int(11) NOT NULL default '0',
  `person_id` int(11) NOT NULL default '0',
  `checkin_date` datetime,
  `checkin_id` int(11),
  `checkout_date` datetime,
  `checkout_id` int(11),
) TYPE=MyISAM;


-- New table to define Event Count Names 
CREATE TABLE IF NOT EXISTS `eventcountnames_evctnm` (
`evctnm_countid` int( 5 ) NOT NULL AUTO_INCREMENT ,
`evctnm_eventtypeid` smallint( 5 ) NOT NULL default '0',
`evctnm_countname` varchar( 20 ) NOT NULL default '',
`evctnm_notes` varchar( 20 ) NOT NULL default '',
UNIQUE KEY `evctnm_countid` ( `evctnm_countid` ) ,
UNIQUE KEY `evctnm_eventtypeid` ( `evctnm_eventtypeid` , `evctnm_countname` )
) TYPE=MyISAM;
INSERT IGNORE INTO `eventcountnames_evctnm` VALUES (1, 1, 'Total','');
INSERT IGNORE INTO `eventcountnames_evctnm` VALUES (2, 1, 'Members','');
INSERT IGNORE INTO `eventcountnames_evctnm` VALUES (3, 1, 'Visitors','');
INSERT IGNORE INTO `eventcountnames_evctnm` VALUES (4, 2, 'Total','');
INSERT IGNORE INTO `eventcountnames_evctnm` VALUES (5, 2, 'Members','');
INSERT IGNORE INTO `eventcountnames_evctnm` VALUES (6, 2, 'Visitors','');

-- New table to track Event Counts
CREATE TABLE IF NOT EXISTS `eventcounts_evtcnt` (
`evtcnt_eventid` int( 5 ) NOT NULL default '0',
`evtcnt_countid` int( 5 ) NOT NULL default '0',
`evtcnt_countname` varchar( 20 ) default NULL ,
`evtcnt_countcount` int( 6 ) default NULL ,
`evtcnt_notes` varchar( 20 ) default NULL ,
PRIMARY KEY ( `evtcnt_eventid` , `evtcnt_countid` )
) TYPE=MyISAM;

CREATE TABLE IF NOT EXISTS `event_types` (
  `type_id` int(11) NOT NULL auto_increment,
  `type_name` varchar(255) NOT NULL default '',
  `type_defstarttime` time NOT NULL default '00:00:00',
  `type_defrecurtype` enum( 'none', 'weekly', 'monthly', 'yearly' ) NOT NULL default 'none',
  `type_defrecurDOW` enum( 'Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday' ) NOT NULL default 'Sunday',
  `type_defrecurDOM` char( 2 ) NOT NULL default '0',
  `type_defrecurDOY` date NOT NULL default '0000-00-00',
  `type_active` int( 1 ) NOT NULL default '1',
  PRIMARY KEY  (`type_id`)
) TYPE=MyISAM;

INSERT IGNORE INTO `event_types` VALUES (1, 'Church Service', '10:30:00', 'weekly', 'Sunday','','',1);
INSERT IGNORE INTO `event_types` VALUES (2, 'Sunday School',  '09:30:00', 'weekly', 'Sunday','','',1);

CREATE TABLE IF NOT EXISTS `events_event` (
  `event_id` int(11) NOT NULL auto_increment,
  `event_type` int(11) NOT NULL default '0',
  `event_title` varchar(255) NOT NULL default '',
  `event_desc` varchar(255) default NULL,
  `event_text` text,
  `event_start` datetime NOT NULL default '0000-00-00 00:00:00',
  `event_end` datetime NOT NULL default '0000-00-00 00:00:00',
  `inactive` int(1) NOT NULL default '0',
  `event_typename` varchar( 40 ) NOT NULL default '',
  PRIMARY KEY  (`event_id`),
  FULLTEXT KEY `event_txt` (`event_text`)
) TYPE=MyISAM;

CREATE TABLE IF NOT EXISTS `config_cfg` (
  `cfg_id` int(11) NOT NULL default '0',
  `cfg_name` varchar(50) NOT NULL default '',
  `cfg_value` text,
  `cfg_type` enum('text','number','date','boolean','textarea') NOT NULL default 'text',
  `cfg_default` text NOT NULL,
  `cfg_tooltip` text NOT NULL,
  `cfg_section` varchar(50) NOT NULL default '',
  PRIMARY KEY  (`cfg_id`),
  UNIQUE KEY `cfg_name` (`cfg_name`),
  KEY `cfg_id` (`cfg_id`)
) TYPE=MyISAM;

-- New table for user settings and permissions
CREATE TABLE IF NOT EXISTS `userconfig_ucfg` (
  `ucfg_per_id` mediumint(9) unsigned NOT NULL,
  `ucfg_id` int(11) NOT NULL default '0',
  `ucfg_name` varchar(50) NOT NULL default '',
  `ucfg_value` text,
  `ucfg_type` enum('text','number','date','boolean','textarea') NOT NULL default 'text',
  `ucfg_tooltip` text NOT NULL,
  `ucfg_permission` enum('FALSE','TRUE') NOT NULL default 'FALSE',
  PRIMARY KEY  (`ucfg_per_ID`,`ucfg_id`)
) TYPE=MyISAM;

-- Add default permissions for new users
INSERT IGNORE INTO `userconfig_ucfg` (ucfg_per_id, ucfg_id, ucfg_name, ucfg_value,
ucfg_type, ucfg_tooltip, ucfg_permission)
VALUES (0,0,'bEmailMailto','1',
'boolean','User permission to send email via mailto: links','TRUE');
INSERT IGNORE INTO `userconfig_ucfg` (ucfg_per_id, ucfg_id, ucfg_name, ucfg_value,
ucfg_type, ucfg_tooltip, ucfg_permission)
VALUES (0,1,'sMailtoDelimiter',',',
'text','Delimiter to separate emails in mailto: links','TRUE');
INSERT IGNORE INTO `userconfig_ucfg` (ucfg_per_id, ucfg_id, ucfg_name, ucfg_value,
ucfg_type, ucfg_tooltip, ucfg_permission)
VALUES (0,2,'bSendPHPMail','0',
'boolean','User permission to send email using PHPMailer','FALSE');
INSERT IGNORE INTO `userconfig_ucfg` (ucfg_per_id, ucfg_id, ucfg_name, ucfg_value,
ucfg_type, ucfg_tooltip, ucfg_permission)
VALUES (0,3,'sFromEmailAddress','',
'text','Reply email address: PHPMailer','FALSE');
INSERT IGNORE INTO `userconfig_ucfg` (ucfg_per_id, ucfg_id, ucfg_name, ucfg_value,
ucfg_type, ucfg_tooltip, ucfg_permission)
VALUES (0,4,'sFromName','ChurchInfo Webmaster',
'text','Name that appears in From field: PHPMailer','FALSE');
INSERT IGNORE INTO `userconfig_ucfg` (ucfg_per_id, ucfg_id, ucfg_name, ucfg_value,
ucfg_type, ucfg_tooltip, ucfg_permission)
VALUES (0,5,'bCreateDirectory','0',
'boolean','User permission to create directories','FALSE');
INSERT IGNORE INTO `userconfig_ucfg` (ucfg_per_id, ucfg_id, ucfg_name, ucfg_value,
ucfg_type, ucfg_tooltip, ucfg_permission)
VALUES (0,6,'bExportCSV','0',
'boolean','User permission to export CSV files','FALSE');
INSERT IGNORE INTO `userconfig_ucfg` (ucfg_per_id, ucfg_id, ucfg_name, ucfg_value,
ucfg_type, ucfg_tooltip, ucfg_permission)
VALUES (0,7,'bUSAddressVerification','0',
'boolean','User permission to use IST Address Verification','FALSE');


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
VALUES (1,3,'sFromEmailAddress','',
'text','Reply email address for PHPMailer','TRUE');
INSERT IGNORE INTO `userconfig_ucfg` (ucfg_per_id, ucfg_id, ucfg_name, ucfg_value,
ucfg_type, ucfg_tooltip, ucfg_permission)
VALUES (1,4,'sFromName','ChurchInfo Webmaster',
'text','Name that appears in From field','TRUE');
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


CREATE TABLE IF NOT EXISTS `istlookup_lu` (
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
) TYPE=MyISAM COMMENT='US Address Verification Lookups From Intelligent Search Technology (IST)';

CREATE TABLE IF NOT EXISTS `email_recipient_pending_erp` (
  `erp_id` smallint(5) unsigned NOT NULL DEFAULT '0',
  `erp_usr_id` mediumint(9) unsigned NOT NULL DEFAULT '0',
  `erp_num_attempt` smallint(5) unsigned NOT NULL DEFAULT '0',
  `erp_failed_time` datetime,
  `erp_email_address` varchar(50) NOT NULL DEFAULT ''
) TYPE=MyISAM;

CREATE TABLE IF NOT EXISTS `email_message_pending_emp` (
  `emp_usr_id` mediumint(9) unsigned NOT NULL DEFAULT '0',
  `emp_to_send` smallint(5) unsigned NOT NULL DEFAULT '0',
  `emp_subject` varchar(128) NOT NULL,
  `emp_message` text NOT NULL
) TYPE=MyISAM;

INSERT IGNORE INTO `config_cfg` VALUES (1, 'sWEBCALENDARDB', '', 'text', '', 'WebCalendar database name', 'General');
INSERT IGNORE INTO `config_cfg` VALUES (2, 'debug', '1', 'boolean', '1', 'Set debug mode\r\nThis may be helpful for when you''re first setting up ChurchInfo, but you should\r\nprobably turn it off for maximum security otherwise.  If you are having trouble,\r\nplease enable this so that you''ll know what the errors are.  This is especially\r\nimportant if you need to report a problem on the help forums.', 'General');
INSERT IGNORE INTO `config_cfg` VALUES (3, 'sJPGRAPH_PATH', 'Include/jpgraph-1.13/src', 'text', 'Include/jpgraph-1.13/src', 'JPGraph library', 'General');
INSERT IGNORE INTO `config_cfg` VALUES (4, 'sFPDF_PATH', 'Include/fpdf', 'text', 'Include/fpdf', 'FPDF library', 'General');
INSERT IGNORE INTO `config_cfg` VALUES (5, 'sDirClassifications', '1,2,4,5', 'text', '1,2,4,5', 'Include only these classifications in the directory, comma seperated', 'General');
INSERT IGNORE INTO `config_cfg` VALUES (6, 'sDirRoleHead', '1,7', 'text', '1,7', 'These are the family role numbers designated as head of house', 'General');
INSERT IGNORE INTO `config_cfg` VALUES (7, 'sDirRoleSpouse', '2', 'text', '2', 'These are the family role numbers designated as spouse', 'General');
INSERT IGNORE INTO `config_cfg` VALUES (8, 'sDirRoleChild', '3', 'text', '3', 'These are the family role numbers designated as child', 'General');
INSERT IGNORE INTO `config_cfg` VALUES (9, 'sSessionTimeout', '3600', 'number', '3600', 'Session timeout length in seconds\rSet to zero to disable session timeouts.', 'General');
INSERT IGNORE INTO `config_cfg` VALUES (10, 'aFinanceQueries', '28,30,31', 'text', '28', 'Queries for which user must have finance permissions to use:', 'General');
INSERT IGNORE INTO `config_cfg` VALUES (11, 'bCSVAdminOnly', '1', 'boolean', '1', 'Should only administrators have access to the CSV export system and directory report?', 'General');
INSERT IGNORE INTO `config_cfg` VALUES (12, 'sDefault_Pass', 'password', 'text', 'password', 'Default password for new users and those with reset passwords', 'General');
INSERT IGNORE INTO `config_cfg` VALUES (13, 'sMinPasswordLength', '6', 'number', '6', 'Minimum length a user may set their password to', 'General');
INSERT IGNORE INTO `config_cfg` VALUES (14, 'sMinPasswordChange', '4', 'number', '4', 'Minimum amount that a new password must differ from the old one (# of characters changed)\rSet to zero to disable this feature', 'General');
INSERT IGNORE INTO `config_cfg` VALUES (15, 'sDisallowedPasswords', 'churchinfo,password,god,jesus,church,christian', 'text', 'churchinfo,password,god,jesus,church,christian', 'A comma-seperated list of disallowed (too obvious) passwords.', 'General');
INSERT IGNORE INTO `config_cfg` VALUES (16, 'iMaxFailedLogins', '50', 'number', '50', 'Maximum number of failed logins to allow before a user account is locked.\rOnce the maximum has been reached, an administrator must re-enable the account.\rThis feature helps to protect against automated password guessing attacks.\rSet to zero to disable this feature.', 'General');
INSERT IGNORE INTO `config_cfg` VALUES (17, 'bToolTipsOn', '', 'boolean', '', 'Turn on or off guided help (Tool Tips).\rThis feature is not complete.  Leave off for now.', 'General');
INSERT IGNORE INTO `config_cfg` VALUES (18, 'iNavMethod', '1', 'number', '1', 'Interface navigation method\r1 = Javascript MenuBar (default)\r2 = Flat Sidebar (alternative for buggy browsers)', 'General');
INSERT IGNORE INTO `config_cfg` VALUES (19, 'bFamListFirstNames', '1', 'boolean', '1', 'Show family member firstnames in Family Listing?', 'General');
INSERT IGNORE INTO `config_cfg` VALUES (20, 'iPDFOutputType', '1', 'number', '1', 'PDF handling mode.\r1 = Save File dialog\r2 = Open in current browser window', 'General');
INSERT IGNORE INTO `config_cfg` VALUES (21, 'sDefaultCity', '', 'text', '', 'Default City', 'General');
INSERT IGNORE INTO `config_cfg` VALUES (22, 'sDefaultState', '', 'text', '', 'Default State - Must be 2-letter abbreviation!', 'General');
INSERT IGNORE INTO `config_cfg` VALUES (23, 'sDefaultCountry', 'United States', 'text', 'United States', 'Default Country', 'General');
INSERT IGNORE INTO `config_cfg` VALUES (24, 'bEmailSend', '', 'boolean', '', 'If you wish to be able to send emails from within ChurchInfo. This requires\reither an SMTP server address to send from or sendmail installed in PHP.', 'General');
INSERT IGNORE INTO `config_cfg` VALUES (25, 'sSendType', 'smtp', 'text', 'smtp', 'The method for sending email. Either "smtp" or "sendmail"', 'General');
INSERT IGNORE INTO `config_cfg` VALUES (26, 'sToEmailAddress', '', 'text', '', 'Default account for receiving a copy of all emails', 'General');
INSERT IGNORE INTO `config_cfg` VALUES (27, 'sSMTPHost', '', 'text', '', 'SMTP Server Address (mail.server.com:25)', 'General');
INSERT IGNORE INTO `config_cfg` VALUES (28, 'sSMTPAuth', '1', 'boolean', '1', 'Does your SMTP server require auththentication (username/password)?', 'General');
INSERT IGNORE INTO `config_cfg` VALUES (29, 'sSMTPUser', '', 'text', '', 'SMTP Username', 'General');
INSERT IGNORE INTO `config_cfg` VALUES (30, 'sSMTPPass', '', 'text', '', 'SMTP Password', 'General');
INSERT IGNORE INTO `config_cfg` VALUES (31, 'sWordWrap', '72', 'number', '72', 'Word Wrap point. Default for most email programs is 72', 'General');
INSERT IGNORE INTO `config_cfg` VALUES (32, 'bDefectiveBrowser', '1', 'boolean', '1', 'Are you using any non-standards-compliant "broken" browsers at this installation?\rIf so, enabling this will turn off the CSS tags that make the menubar stay\rat the top of the screen instead of scrolling with the rest of the page.\rIt will also turn off the use of nice, alpha-blended PNG images, which IE still\rdoes not properly handle.\rNOTICE: MS Internet Explorer is currently not standards-compliant enough for\rthese purposes.  Please use a quality web browser such as Netscape 7, Firefox, etc.\r', 'General');
INSERT IGNORE INTO `config_cfg` VALUES (33, 'bShowFamilyData', '1', 'boolean', '1', 'Unavailable person info inherited from assigned family for display?\rThis option causes certain info from a person''s assigned family record to be\rdisplayed IF the corresponding info has NOT been entered for that person. ', 'General');
INSERT IGNORE INTO `config_cfg` VALUES (34, 'bOldVCardVersion', '', 'boolean', '', 'Use vCard 2.1 rather than vCard 3.0 standard.', 'General');
INSERT IGNORE INTO `config_cfg` VALUES (35, 'bEnableBackupUtility', '', 'boolean', '', 'This backup system only works on "UNIX-style" operating systems such as\rGNU/Linux, OSX and the BSD variants (NOT Windows).\rOf course, remember that only your web server needs to running a UNIX-style\rOS for this feature to work.', 'General');
INSERT IGNORE INTO `config_cfg` VALUES (36, 'sGZIPname', 'gzip', 'text', 'gzip', '', 'General');
INSERT IGNORE INTO `config_cfg` VALUES (37, 'sZIPname', 'zip', 'text', 'zip', '', 'General');
INSERT IGNORE INTO `config_cfg` VALUES (38, 'sPGPname', 'gpg', 'text', 'gpg', '', 'General');
INSERT IGNORE INTO `config_cfg` VALUES (39, 'sLanguage', 'en_US', 'text', 'en_US', 'Internationalization (I18n) support\rUS English (en_US), Italian (it_IT), French (fr_FR), and German (de_DE)', 'General');
INSERT IGNORE INTO `config_cfg` VALUES (40, 'iFYMonth', '1', 'number', '1', 'First month of the fiscal year', 'General');
INSERT IGNORE INTO `config_cfg` VALUES (41, 'sXML_RPC_PATH', 'XML/RPC.php', 'text', 'XML/RPC.php', 'Path to RPC.php, required for Lat/Lon address lookup', 'General');
INSERT IGNORE INTO `config_cfg` VALUES (42, 'sGeocoderID', '', 'text', '', 'User ID for rpc.geocoder.us', 'General');
INSERT IGNORE INTO `config_cfg` VALUES (43, 'sGeocoderPW', '', 'text', '', 'Password for rpc.geocoder.us', 'General');
INSERT IGNORE INTO `config_cfg` VALUES (44, 'sGoogleMapKey', '', 'text', '', 'Google map API requires a unique key from http://maps.google.com/apis/maps/signup.html', 'General');
INSERT IGNORE INTO `config_cfg` VALUES (45, 'nChurchLatitude', '', 'number', '', 'Latitude of the church, used to center the Google map', 'General');
INSERT IGNORE INTO `config_cfg` VALUES (46, 'nChurchLongitude', '', 'number', '', 'Longitude of the church, used to center the Google map', 'General');
INSERT IGNORE INTO `config_cfg` VALUES (47, 'bHidePersonAddress', '1', 'boolean', '1', 'Set true to disable entering addresses in Person Editor.  Set false to enable entering addresses in Person Editor.', 'General');
INSERT IGNORE INTO `config_cfg` VALUES (48, 'bHideFriendDate', '0', 'boolean', '0', 'Set true to disable entering Friend Date in Person Editor.  Set false to enable entering Friend Date in Person Editor.', 'General');
INSERT IGNORE INTO `config_cfg` VALUES (49, 'bHideFamilyNewsletter', '0', 'boolean', '0', 'Set true to disable management of newsletter subscriptions in the Family Editor.', 'General');
INSERT IGNORE INTO `config_cfg` VALUES (50, 'bHideWeddingDate', '0', 'boolean', '0', 'Set true to disable entering Wedding Date in Family Editor.  Set false to enable entering Wedding Date in Family Editor.', 'General');
INSERT IGNORE INTO `config_cfg` VALUES (51, 'bHideLatLon', '0', 'boolean', '0', 'Set true to disable entering Latitude and Longitude in Family Editor.  Set false to enable entering Latitude and Longitude in Family Editor.  Lookups are still performed, just not displayed.', 'General');
INSERT IGNORE INTO `config_cfg` VALUES (52, 'bUseDonationEnvelopes', '0', 'boolean', '0', 'Set true to enable use of donation envelopes', 'General');
INSERT IGNORE INTO `config_cfg` VALUES (53, 'sHeader', '', 'textarea', '', 'Enter in HTML code which will be displayed as a header at the top of each page. Be sure to close your tags! Note: You must REFRESH YOUR BROWSER A SECOND TIME to view the new header.', 'General');
INSERT IGNORE INTO `config_cfg` VALUES (54, 'sISTusername', 'username', 'text', 'username', 'Intelligent Search Technolgy, Ltd. CorrectAddress Username for https://www.name-searching.com/CaddressASP', 'General');
INSERT IGNORE INTO `config_cfg` VALUES (55, 'sISTpassword', '', 'text', '', 'Intelligent Search Technolgy, Ltd. CorrectAddress Password for https://www.name-searching.com/CaddressASP', 'General');
INSERT IGNORE INTO `config_cfg` VALUES (56, 'bUseGoogleGeocode', '1', 'boolean', '1', 'Set true to use the Google geocoder.  Set false to use rpc.geocoder.us.', 'General');
INSERT IGNORE INTO `config_cfg` VALUES (999, 'bRegistered', '0', 'boolean', '0', 'ChurchInfo has been registered.  The ChurchInfo team uses registration information to track usage.  This information is kept confidential and never released or sold.  If this field is true the registration option in the admin menu changes to update registration.', 'General');

INSERT IGNORE INTO `config_cfg` VALUES (1001, 'leftX', '20', 'number', '20', 'Left Margin (1 = 1/100th inch)', 'ChurchInfoReport');
INSERT IGNORE INTO `config_cfg` VALUES (1002, 'incrementY', '4', 'number', '4', 'Line Thickness (1 = 1/100th inch', 'ChurchInfoReport');
INSERT IGNORE INTO `config_cfg` VALUES (1003, 'sChurchName', 'Some Church', 'text', '', 'Church Name', 'ChurchInfoReport');
INSERT IGNORE INTO `config_cfg` VALUES (1004, 'sChurchAddress', '100 Main St', 'text', '', 'Church Address', 'ChurchInfoReport');
INSERT IGNORE INTO `config_cfg` VALUES (1005, 'sChurchCity', 'Wall', 'text', '', 'Church City', 'ChurchInfoReport');
INSERT IGNORE INTO `config_cfg` VALUES (1006, 'sChurchState', 'SD', 'text', '', 'Church State', 'ChurchInfoReport');
INSERT IGNORE INTO `config_cfg` VALUES (1007, 'sChurchZip', '11111', 'text', '', 'Church Zip', 'ChurchInfoReport');
INSERT IGNORE INTO `config_cfg` VALUES (1008, 'sChurchPhone', '123-456-7890', 'text', '', 'Church Phone', 'ChurchInfoReport');
INSERT IGNORE INTO `config_cfg` VALUES (1009, 'sChurchEmail', 'church@church.org', 'text', '', 'Church Email', 'ChurchInfoReport');
INSERT IGNORE INTO `config_cfg` VALUES (1010, 'sHomeAreaCode', '111', 'text', '', 'Home area code of the church', 'ChurchInfoReport');
INSERT IGNORE INTO `config_cfg` VALUES (1011, 'sTaxReport1', 'This letter shows our record of your payments for', 'text', 'This letter shows our record of your payments for', 'Verbage for top line of tax report. Dates will be appended to the end of this line.', 'ChurchInfoReport');
INSERT IGNORE INTO `config_cfg` VALUES (1012, 'sTaxReport2', 'Thank you for your help in making a difference for the cause of Christ. We greatly appreciate your gift and covet your prayers!', 'text', 'Thank you for your help in making a difference for the cause of Christ. We greatly appreciate your gift and covet your prayers!', 'Verbage for bottom line of tax report.', 'ChurchInfoReport');
INSERT IGNORE INTO `config_cfg` VALUES (1013, 'sTaxReport3', 'If you have any questions or corrections to make to this report, please contact the church at the above number during business hours, 9am to 4pm, M-F.', 'text', 'If you have any questions or corrections to make to this report, please contact the church at the above number during business hours, 9am to 4pm, M-F.', 'Verbage for bottom line of tax report.', 'ChurchInfoReport');
INSERT IGNORE INTO `config_cfg` VALUES (1014, 'sTaxSigner', 'Elder Joe Smith', 'text', '', 'Tax Report signer', 'ChurchInfoReport');
INSERT IGNORE INTO `config_cfg` VALUES (1015, 'sReminder1', 'This letter shows our record of your pledge and payments for fiscal year', 'text', 'This letter shows our record of your pledge and payments for fiscal year', 'Verbage for the pledge reminder report', 'ChurchInfoReport');
INSERT IGNORE INTO `config_cfg` VALUES (1016, 'sReminderSigner', 'Elder Joe Smith', 'text', '', 'Pledge Reminder Signer', 'ChurchInfoReport');
INSERT IGNORE INTO `config_cfg` VALUES (1017, 'sReminderNoPledge', 'Pledges: We do not have record of a pledge for from you for this fiscal year.', 'text', 'Pledges: We do not have record of a pledge for from you for this fiscal year.', 'Verbage for the pledge reminder report - No record of a pledge', 'ChurchInfoReport');
INSERT IGNORE INTO `config_cfg` VALUES (1018, 'sReminderNoPayments', 'Payments: We do not have record of a pledge for from you for this fiscal year.', 'text', 'Payments: We do not have record of a pledge for from you for this fiscal year.', 'Verbage for the pledge reminder report - No record of payments', 'ChurchInfoReport');
INSERT IGNORE INTO `config_cfg` VALUES (1019, 'sConfirm1', 'This letter shows the information we have in our database with respect to your family.  Please review, mark-up as necessary, and return this form to the church office.', 'text', 'This letter shows the information we have in our database with respect to your family.  Please review, mark-up as necessary, and return this form to the church office.', 'Verbage for the database information confirmation and correction report', 'ChurchInfoReport');
INSERT IGNORE INTO `config_cfg` VALUES (1020, 'sConfirm2', 'Thank you very much for helping us to update this information.  If you want on-line access to the church database please provide your email address and a desired password and we will send instructions.', 'text', 'Thank you very much for helping us to update this information.  If you want on-line access to the church database please provide your email address and a desired password and we will send instructions.', 'Verbage for the database information confirmation and correction report', 'ChurchInfoReport');
INSERT IGNORE INTO `config_cfg` VALUES (1021, 'sConfirm3', 'Email _____________________________________ Password ________________', 'text', 'Email _____________________________________ Password ________________', 'Verbage for the database information confirmation and correction report', 'ChurchInfoReport');
INSERT IGNORE INTO `config_cfg` VALUES (1022, 'sConfirm4', '[  ] I no longer want to be associated with the church (check here to be removed from our records).', 'text', '[  ] I no longer want to be associated with the church (check here to be removed from our records).', 'Verbage for the database information confirmation and correction report', 'ChurchInfoReport');
INSERT IGNORE INTO `config_cfg` VALUES (1023, 'sConfirm5', '', 'text', '', 'Verbage for the database information confirmation and correction report', 'ChurchInfoReport');
INSERT IGNORE INTO `config_cfg` VALUES (1024, 'sConfirm6', '', 'text', '', 'Verbage for the database information confirmation and correction report', 'ChurchInfoReport');
INSERT IGNORE INTO `config_cfg` VALUES (1025, 'sConfirmSigner', 'Elder Joe Smith', 'text', '', 'Database information confirmation and correction report signer', 'ChurchInfoReport');
INSERT IGNORE INTO `config_cfg` VALUES (1026, 'sPledgeSummary1', 'Summary of pledges and payments for the fiscal year', 'text', 'Summary of pledges and payments for the fiscal year', 'Verbage for the pledge summary report', 'ChurchInfoReport');
INSERT IGNORE INTO `config_cfg` VALUES (1027, 'sPledgeSummary2', 'as of', 'text', ' as of', 'Verbage for the pledge summary report', 'ChurchInfoReport');
INSERT IGNORE INTO `config_cfg` VALUES (1028, 'sDirectoryDisclaimer1', 'Every effort was made to insure the accuracy of this directory.  If there are any errors or omissions, please contact the church office.This directory is for the use of the people of', 'text', 'Every effort was made to insure the accuracy of this directory.  If there are any errors or omissions, please contact the church office.\n\nThis directory is for the use of the people of', 'Verbage for the directory report', 'ChurchInfoReport');
INSERT IGNORE INTO `config_cfg` VALUES (1029, 'sDirectoryDisclaimer2', ', and the information contained in it may not be used for business or commercial purposes.', 'text', ', and the information contained in it may not be used for business or commercial purposes.', 'Verbage for the directory report', 'ChurchInfoReport');
INSERT IGNORE INTO `config_cfg` VALUES (1030, 'bDirLetterHead', '../Images/church_letterhead.jpg', 'text', '../Images/church_letterhead.jpg', 'Church Letterhead path and file', 'ChurchInfoReport');

-- Table to keep track of version information
CREATE TABLE IF NOT EXISTS `version_ver` (
  `ver_ID` mediumint(9) unsigned NOT NULL auto_increment,
  `ver_version` varchar(50) NOT NULL default '',
  `ver_date` datetime default NULL,
  PRIMARY KEY  (`ver_ID`),
  UNIQUE KEY `ver_version` (`ver_version`)
) TYPE=MyISAM;

INSERT IGNORE INTO `version_ver` (`ver_version`, `ver_date`) VALUES ('1.2.9',NOW());
