CREATE TABLE canvas05_c05 (
  c05_famID smallint(9) NOT NULL default '0',
  c05_churchColor text,
  c05_doingRight text,
  c05_canImprove text,
  c05_pledgeByMar31 text,
  c05_comments text
) TYPE=MyISAM;

CREATE TABLE deposit_dep (
  dep_ID mediumint(9) unsigned NOT NULL auto_increment,
  dep_Date date default NULL,
  dep_Comment text,
  dep_EnteredBy mediumint(9) unsigned default NULL,
  dep_Closed tinyint(1) NOT NULL default '0',
  PRIMARY KEY  (dep_ID)
) TYPE=MyISAM PACK_KEYS=0;

CREATE TABLE donationfund_fun (
  fun_ID tinyint(3) NOT NULL auto_increment,
  fun_Active enum('true','false') NOT NULL default 'true',
  fun_Name varchar(30) default NULL,
  fun_Description varchar(100) default NULL,
  PRIMARY KEY  (fun_ID),
  UNIQUE KEY fun_ID (fun_ID)
) TYPE=MyISAM;

CREATE TABLE family_fam (
  fam_ID mediumint(9) unsigned NOT NULL auto_increment,
  fam_Name varchar(50) default NULL,
  fam_Address1 varchar(255) default NULL,
  fam_Address2 varchar(255) default NULL,
  fam_City varchar(50) default NULL,
  fam_State varchar(50) default NULL,
  fam_Zip varchar(50) default NULL,
  fam_Country varchar(50) default NULL,
  fam_HomePhone varchar(30) default NULL,
  fam_WorkPhone varchar(30) default NULL,
  fam_CellPhone varchar(30) default NULL,
  fam_Email varchar(100) default NULL,
  fam_WeddingDate date default NULL,
  fam_DateEntered datetime NOT NULL default '0000-00-00 00:00:00',
  fam_DateLastEdited datetime default NULL,
  fam_EnteredBy smallint(5) unsigned NOT NULL default '0',
  fam_EditedBy smallint(5) unsigned default '0',
  fam_scanCheck text,
  fam_scanCredit text,
  fam_SendNewsLetter enum('FALSE','TRUE') NOT NULL default 'FALSE',
  fam_DateDeactivated date default NULL,
  PRIMARY KEY  (fam_ID),
  KEY fam_ID (fam_ID)
) TYPE=MyISAM;

CREATE TABLE group_grp (
  grp_ID mediumint(8) unsigned NOT NULL auto_increment,
  grp_Type tinyint(4) NOT NULL default '0',
  grp_RoleListID mediumint(8) unsigned NOT NULL,
  grp_DefaultRole mediumint(9) NOT NULL default '0',
  grp_Name varchar(50) NOT NULL default '',
  grp_Description text,
  grp_hasSpecialProps enum('true','false') NOT NULL default 'false',
  PRIMARY KEY  (grp_ID),
  UNIQUE KEY grp_ID (grp_ID),
  KEY grp_ID_2 (grp_ID)
) TYPE=MyISAM;


CREATE TABLE note_nte (
  nte_ID mediumint(8) unsigned NOT NULL auto_increment,
  nte_per_ID mediumint(8) unsigned NOT NULL default '0',
  nte_fam_ID mediumint(8) unsigned NOT NULL default '0',
  nte_Private mediumint(8) unsigned NOT NULL default '0',
  nte_Text text,
  nte_DateEntered datetime NOT NULL default '0000-00-00 00:00:00',
  nte_DateLastEdited datetime default NULL,
  nte_EnteredBy mediumint(8) unsigned NOT NULL default '0',
  nte_EditedBy mediumint(8) unsigned NOT NULL default '0',
  PRIMARY KEY  (nte_ID)
) TYPE=MyISAM;


CREATE TABLE person2group2role_p2g2r (
  p2g2r_per_ID mediumint(8) unsigned NOT NULL default '0',
  p2g2r_grp_ID mediumint(8) unsigned NOT NULL default '0',
  p2g2r_rle_ID mediumint(8) unsigned NOT NULL default '0',
  PRIMARY KEY  (p2g2r_per_ID,p2g2r_grp_ID),
  KEY p2g2r_per_ID (p2g2r_per_ID,p2g2r_grp_ID,p2g2r_rle_ID)
) TYPE=MyISAM;


CREATE TABLE person_per (
  per_ID mediumint(9) unsigned NOT NULL auto_increment,
  per_Title varchar(50) default NULL,
  per_FirstName varchar(50) default NULL,
  per_MiddleName varchar(50) default NULL,
  per_LastName varchar(50) default NULL,
  per_Suffix varchar(50) default NULL,
  per_Address1 varchar(50) default NULL,
  per_Address2 varchar(50) default NULL,
  per_City varchar(50) default NULL,
  per_State varchar(50) default NULL,
  per_Zip varchar(50) default NULL,
  per_Country varchar(50) default NULL,
  per_HomePhone varchar(30) default NULL,
  per_WorkPhone varchar(30) default NULL,
  per_CellPhone varchar(30) default NULL,
  per_Email varchar(50) default NULL,
  per_WorkEmail varchar(50) default NULL,
  per_BirthMonth tinyint(3) unsigned NOT NULL default '0',
  per_BirthDay tinyint(3) unsigned NOT NULL default '0',
  per_BirthYear year(4) default NULL,
  per_MembershipDate date default NULL,
  per_Gender tinyint(1) unsigned NOT NULL default '0',
  per_fmr_ID tinyint(3) unsigned NOT NULL default '0',
  per_cls_ID tinyint(3) unsigned NOT NULL default '0',
  per_fam_ID smallint(5) unsigned NOT NULL default '0',
  per_Envelope smallint(5) unsigned default NULL,
  per_DateLastEdited datetime default NULL,
  per_DateEntered datetime NOT NULL default '0000-00-00 00:00:00',
  per_EnteredBy smallint(5) unsigned NOT NULL default '0',
  per_EditedBy smallint(5) unsigned default '0',
  PRIMARY KEY  (per_ID),
  KEY per_ID (per_ID)
) TYPE=MyISAM;


INSERT INTO person_per VALUES (1,NULL,'ChurchInfo',NULL,'Admin',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,0,0,0000,NULL,0,0,0,0,NULL,NULL,'2004-08-25 18:00:00',0,0);

CREATE TABLE pledge_plg (
  plg_plgID mediumint(9) NOT NULL auto_increment,
  plg_FamID mediumint(9) default NULL,
  plg_FYID mediumint(9) default NULL,
  plg_date date default NULL,
  plg_amount decimal(8,2) default NULL,
  plg_schedule enum('Monthly','Quarterly','Once','Other') default NULL,
  plg_method enum('CREDITCARD','CHECK','CASH','OTHER') default NULL,
  plg_comment text,
  plg_DateLastEdited date NOT NULL default '0000-00-00',
  plg_EditedBy mediumint(9) NOT NULL default '0',
  plg_PledgeOrPayment enum('Pledge','Payment') NOT NULL default 'Pledge',
  plg_fundID tinyint(3) unsigned default NULL,
  plg_depID mediumint(9) unsigned default NULL,
  plg_CheckNo bigint(16) unsigned default NULL,
  plg_Problem tinyint(1) default NULL,
  plg_scanString text,
  PRIMARY KEY  (plg_plgID)
) TYPE=MyISAM;

CREATE TABLE property_pro (
  pro_ID mediumint(8) unsigned NOT NULL auto_increment,
  pro_Class varchar(10) NOT NULL default '',
  pro_prt_ID mediumint(8) unsigned NOT NULL default '0',
  pro_Name varchar(200) NOT NULL default '0',
  pro_Description text NOT NULL,
  pro_Prompt varchar(255) default NULL,
  PRIMARY KEY  (pro_ID),
  UNIQUE KEY pro_ID (pro_ID),
  KEY pro_ID_2 (pro_ID)
) TYPE=MyISAM;


INSERT INTO property_pro VALUES (1,'p',1,'Disabled','has a disability.','What is the nature of the disability?');
INSERT INTO property_pro VALUES (2,'f',2,'Single Parent','is a single-parent household.','');
INSERT INTO property_pro VALUES (3,'g',3,'Youth','is youth-oriented.','');


CREATE TABLE propertytype_prt (
  prt_ID mediumint(9) NOT NULL auto_increment,
  prt_Class varchar(10) NOT NULL default '',
  prt_Name varchar(50) NOT NULL default '',
  prt_Description text NOT NULL,
  PRIMARY KEY  (prt_ID),
  UNIQUE KEY prt_ID (prt_ID),
  KEY prt_ID_2 (prt_ID)
) TYPE=MyISAM;


INSERT INTO propertytype_prt VALUES (1,'p','General','General Person Properties');
INSERT INTO propertytype_prt VALUES (2,'f','General','General Family Properties');
INSERT INTO propertytype_prt VALUES (3,'g','General','General Group Properties');


CREATE TABLE query_qry (
  qry_ID mediumint(8) unsigned NOT NULL auto_increment,
  qry_SQL text NOT NULL,
  qry_Name varchar(255) NOT NULL default '',
  qry_Description text NOT NULL,
  qry_Count tinyint(1) unsigned NOT NULL default '0',
  PRIMARY KEY  (qry_ID),
  UNIQUE KEY qry_ID (qry_ID),
  KEY qry_ID_2 (qry_ID)
) TYPE=MyISAM;


INSERT INTO query_qry VALUES (2,'SELECT COUNT(per_ID)\nAS \'Count\'\nFROM person_per','Person Count','Returns the total number of people in the database.',0);
INSERT INTO query_qry VALUES (3,'SELECT CONCAT(\'<a href=FamilyView.php?FamilyID=\',fam_ID,\'>\',fam_Name,\'</a>\') AS \'Family Name\', COUNT(*) AS \'No.\'\nFROM person_per\nINNER JOIN family_fam\nON fam_ID = per_fam_ID\nGROUP BY per_fam_ID\nORDER BY \'No.\' DESC','Family Member Count','Returns each family and the total number of people assigned to them.',0);
INSERT INTO query_qry VALUES (4,'SELECT per_ID as AddToCart,CONCAT(\'<a href=PersonView.php?PersonID=\',per_ID,\'>\',per_FirstName,\' \',per_LastName,\'</a>\') AS Name, CONCAT(per_BirthMonth,\'/\',per_BirthDay,\'/\',per_BirthYear) AS \'Birth Date\', \nYEAR(CURRENT_DATE) - per_BirthYear AS \'Age\'\nFROM person_per\nWHERE\nDATE_ADD(CONCAT(per_BirthYear,\'-\',per_BirthMonth,\'-\',per_BirthDay),INTERVAL ~min~ YEAR) <= CURDATE()\nAND\nDATE_ADD(CONCAT(per_BirthYear,\'-\',per_BirthMonth,\'-\',per_BirthDay),INTERVAL ~max~ YEAR) >= CURDATE()','Person by Age','Returns any person records with ages between two given ages.',1);
INSERT INTO query_qry VALUES (6,'SELECT COUNT(per_ID) AS Total FROM person_per WHERE per_Gender = ~gender~','Total By Gender','Total of records matching a given gender.',0);
INSERT INTO query_qry VALUES (7,'SELECT per_ID as AddToCart, CONCAT(per_FirstName,\' \',per_LastName) AS Name FROM person_per WHERE per_fmr_ID = ~role~ AND per_Gender = ~gender~','Person by Role and Gender','Selects person records with the family role and gender specified.',1);
INSERT INTO query_qry VALUES (9,'SELECT \r\nper_ID as AddToCart, \r\nCONCAT(per_FirstName,\' \',per_LastName) AS Name, \r\nCONCAT(r2p_Value,\' \') AS Value\r\nFROM person_per,record2property_r2p\r\nWHERE per_ID = r2p_record_ID\r\nAND r2p_pro_ID = ~PropertyID~\r\nORDER BY per_LastName','Person by Property','Returns person records which are assigned the given property.',1);
INSERT INTO query_qry VALUES (10, 'SELECT CONCAT(\'<a href=PersonView.php?PersonID=\',per_ID,\' target=view>\', per_FirstName,\' \', per_MiddleName,\' \', per_LastName,\'</a>\') AS Name, CONCAT(\'<a href=DonationView.php?PersonID=\',per_ID,\' target=view>\', \'$\',sum(round(dna_amount,2)),\'</a>\') as Amount\r\nFROM donations_don, person_per\r\nLEFT JOIN donationamounts_dna ON don_ID = dna_don_ID\r\nWHERE don_DonorID = per_ID AND don_date >= \'~startdate~\'\r\nAND don_date <= \'~enddate~\'\r\nGROUP BY don_DonorID\r\nORDER BY per_LastName ASC', 'Total Donations by Member', 'Sum of donations by member for a specific period of time between two dates.', 1);
INSERT INTO query_qry VALUES (11, 'SELECT fun_name as Fund, CONCAT(\'$\',sum(round(dna_amount,2))) as Total\r\nFROM donations_don\r\nLEFT JOIN donationamounts_dna ON donations_don.don_ID = donationamounts_dna.dna_don_ID LEFT JOIN donationfund_fun ON donationamounts_dna.dna_fun_ID = donationfund_fun.fun_ID\r\nWHERE don_date >= \'~startdate~\'\r\nAND don_date <= \'~enddate~\'\r\nGROUP BY fun_id\r\nORDER BY fun_name', 'Total Donations by Fund', 'Sum of donations by FUND for a specific period of time between two dates.', 1);
INSERT INTO query_qry VALUES (15, 'SELECT per_ID as AddToCart, CONCAT(\'<a href=PersonView.php?PersonID=\',per_ID,\'>\',per_FirstName,\' \',per_MiddleName,\' \',per_LastName,\'</a>\') AS Name, \r\nper_City as City, per_State as State,\r\nper_Zip as ZIP, per_HomePhone as HomePhone\r\nFROM person_per \r\nWHERE ~searchwhat~ LIKE \'%~searchstring~%\'', 'Advanced Search', 'Search by any part of Name, City, State, Zip, or Home Phone.', 1);

CREATE TABLE queryparameteroptions_qpo (
  qpo_ID smallint(5) unsigned NOT NULL auto_increment,
  qpo_qrp_ID mediumint(8) unsigned NOT NULL default '0',
  qpo_Display varchar(50) NOT NULL default '',
  qpo_Value varchar(50) NOT NULL default '',
  PRIMARY KEY  (qpo_ID),
  UNIQUE KEY qpo_ID (qpo_ID)
) TYPE=MyISAM;


INSERT INTO queryparameteroptions_qpo VALUES (1,4,'Male','1');
INSERT INTO queryparameteroptions_qpo VALUES (2,4,'Female','2');
INSERT INTO queryparameteroptions_qpo VALUES (3,6,'Male','1');
INSERT INTO queryparameteroptions_qpo VALUES (4,6,'Female','2');
INSERT INTO queryparameteroptions_qpo VALUES (5, 15, 'Name', 'CONCAT(per_FirstName,per_MiddleName,per_LastName)');
INSERT INTO queryparameteroptions_qpo VALUES (6, 15, 'Zip Code', 'per_Zip');
INSERT INTO queryparameteroptions_qpo VALUES (7, 15, 'State', 'per_State');
INSERT INTO queryparameteroptions_qpo VALUES (8, 15, 'City', 'per_City');
INSERT INTO queryparameteroptions_qpo VALUES (9, 15, 'Home Phone', 'per_HomePhone');

CREATE TABLE queryparameters_qrp (
  qrp_ID mediumint(8) unsigned NOT NULL auto_increment,
  qrp_qry_ID mediumint(8) unsigned NOT NULL default '0',
  qrp_Type tinyint(3) unsigned NOT NULL default '0',
  qrp_OptionSQL text,
  qrp_Name varchar(25) default NULL,
  qrp_Description text,
  qrp_Alias varchar(25) default NULL,
  qrp_Default varchar(25) default NULL,
  qrp_Required tinyint(3) unsigned NOT NULL default '0',
  qrp_InputBoxSize tinyint(3) unsigned NOT NULL default '0',
  qrp_Validation varchar(5) NOT NULL default '',
  qrp_NumericMax int(11) default NULL,
  qrp_NumericMin int(11) default NULL,
  qrp_AlphaMinLength int(11) default NULL,
  qrp_AlphaMaxLength int(11) default NULL,
  PRIMARY KEY  (qrp_ID),
  UNIQUE KEY qrp_ID (qrp_ID),
  KEY qrp_ID_2 (qrp_ID),
  KEY qrp_qry_ID (qrp_qry_ID)
) TYPE=MyISAM;


INSERT INTO queryparameters_qrp VALUES (1,4,0,NULL,'Minimum Age','The minimum age for which you want records returned.','min','0',0,5,'n',120,0,NULL,NULL);
INSERT INTO queryparameters_qrp VALUES (2,4,0,NULL,'Maximum Age','The maximum age for which you want records returned.','max','120',1,5,'n',120,0,NULL,NULL);
INSERT INTO queryparameters_qrp VALUES (4,6,1,'','Gender','The desired gender to search the database for.','gender','1',1,0,'',0,0,0,0);
INSERT INTO queryparameters_qrp VALUES (5,7,2,'SELECT lst_OptionID as Value, lst_OptionName as Display FROM list_lst WHERE lst_ID=2 ORDER BY lst_OptionSequence','Family Role','Select the desired family role.','role','1',0,0,'',0,0,0,0);
INSERT INTO queryparameters_qrp VALUES (6,7,1,'','Gender','The gender for which you would like records returned.','gender','1',1,0,'',0,0,0,0);
INSERT INTO queryparameters_qrp VALUES (8,9,2,'SELECT pro_ID AS Value, pro_Name as Display \r\nFROM property_pro\r\nWHERE pro_Class= \'p\' \r\nORDER BY pro_Name ','Property','The property for which you would like person records returned.','PropertyID','0',1,0,'',0,0,0,0);
INSERT INTO queryparameters_qrp VALUES (9, 10, 2, 'SELECT distinct don_date as Value, don_date as Display FROM donations_don ORDER BY don_date ASC', 'Beginning Date', 'Please select the beginning date to calculate total contributions for each member (i.e. YYYY-MM-DD). NOTE: You can only choose dates that conatain donations.', 'startdate', '1', 1, 0, '0', 0, 0, 0, 0);
INSERT INTO queryparameters_qrp VALUES (10, 10, 2, 'SELECT distinct don_date as Value, don_date as Display FROM donations_don\r\nORDER BY don_date DESC', 'Ending Date', 'Please enter the last date to calculate total contributions for each member (i.e. YYYY-MM-DD).', 'enddate', '1', 1, 0, '', 0, 0, 0, 0);
INSERT INTO queryparameters_qrp VALUES (14, 15, 0, '', 'Search', 'Enter any part of the following: Name, City, State, Zip, or Home Phone.', 'searchstring', '', 1, 0, '', 0, 0, 0, 0);
INSERT INTO queryparameters_qrp VALUES (15, 15, 1, '', 'Field', 'Select field to search for.', 'searchwhat', '1', 1, 0, '', 0, 0, 0, 0);
INSERT INTO queryparameters_qrp VALUES (16, 11, 2, 'SELECT distinct don_date as Value, don_date as Display FROM donations_don ORDER BY don_date ASC', 'Beginning Date', 'Please select the beginning date to calculate total contributions for each member (i.e. YYYY-MM-DD). NOTE: You can only choose dates that conatain donations.', 'startdate', '1', 1, 0, '0', 0, 0, 0, 0);
INSERT INTO queryparameters_qrp VALUES (17, 11, 2, 'SELECT distinct don_date as Value, don_date as Display FROM donations_don\r\nORDER BY don_date DESC', 'Ending Date', 'Please enter the last date to calculate total contributions for each member (i.e. YYYY-MM-DD).', 'enddate', '1', 1, 0, '', 0, 0, 0, 0);


CREATE TABLE record2property_r2p (
  r2p_pro_ID mediumint(8) unsigned NOT NULL default '0',
  r2p_record_ID MEDIUMINT UNSIGNED NOT NULL DEFAULT '0',
  r2p_Value text NOT NULL
) TYPE=MyISAM;


CREATE TABLE user_usr (
  usr_per_ID mediumint(9) unsigned NOT NULL default '0',
  usr_Password varchar(50) NOT NULL default '',
  usr_NeedPasswordChange tinyint(3) unsigned NOT NULL default '0',
  usr_LastLogin datetime NOT NULL default '0000-00-00 00:00:00',
  usr_LoginCount smallint(5) unsigned NOT NULL default '0',
  usr_FailedLogins tinyint(3) unsigned NOT NULL default '0',
  usr_AddRecords tinyint(3) unsigned NOT NULL default '0',
  usr_EditRecords tinyint(3) unsigned NOT NULL default '0',
  usr_DeleteRecords tinyint(3) unsigned NOT NULL default '0',
  usr_MenuOptions tinyint(3) unsigned NOT NULL default '0',
  usr_ManageGroups tinyint(3) unsigned NOT NULL default '0',
  usr_Finance tinyint(3) unsigned NOT NULL default '0',
  usr_Communication tinyint(3) unsigned NOT NULL default '0',
  usr_Notes tinyint(3) unsigned NOT NULL default '0',
  usr_Admin tinyint(3) unsigned NOT NULL default '0',
  usr_Workspacewidth smallint(6) default NULL,
  usr_BaseFontSize tinyint(4) default NULL,
  usr_SearchLimit tinyint(4) default '10',
  usr_Style varchar(50) default 'Style.css',
  usr_showPledges tinyint(1) NOT NULL default '0',
  usr_showPayments tinyint(1) NOT NULL default '0',
  usr_showSince date NOT NULL default '0000-00-00',
  usr_defaultFY mediumint(9) default NULL,
  usr_currentDeposit mediumint(9) default NULL,
  usr_UserName varchar(32) default NULL,
  usr_EditSelf tinyint(3) unsigned NOT NULL default '0',
  usr_CalStart date default NULL,
  usr_CalEnd date default NULL,
  usr_CalNoSchool1 date default NULL,
  usr_CalNoSchool2 date default NULL,
  usr_CalNoSchool3 date default NULL,
  usr_CalNoSchool4 date default NULL,
  usr_CalNoSchool5 date default NULL,
  usr_CalNoSchool6 date default NULL,
  usr_CalNoSchool7 date default NULL,
  usr_CalNoSchool8 date default NULL,
  usr_SearchFamily tinyint(3) default NULL,
  PRIMARY KEY  (usr_per_ID),
  KEY usr_per_ID (usr_per_ID)
) TYPE=MyISAM;

INSERT INTO user_usr (usr_per_ID,
                      usr_Password,
					  usr_NeedPasswordChange,
					  usr_LastLogin,
					  usr_LoginCount,
					  usr_FailedLogins,
					  usr_AddRecords,
					  usr_EditRecords,
					  usr_DeleteRecords,
					  usr_MenuOptions,
					  usr_ManageGroups,
					  usr_Finance,
					  usr_Communication,
					  usr_Notes,
					  usr_Admin,
					  usr_Workspacewidth,
					  usr_BaseFontSize,
					  usr_SearchLimit,
					  usr_Style) 
			VALUES (1,
			        '1a7ac1b904382aaf0ac67b4f00e7b93f',
					1,
					'0000-00-00 00:00:00',
					0,
					0,
					1,
					1,
					1,
					1,
					1,
					1,
					1,
					1,
					1,
					580,
					9,
					10,
					'Style.css');

CREATE TABLE groupprop_master (
  grp_ID mediumint(9) unsigned NOT NULL default '0',
  prop_ID tinyint(3) unsigned NOT NULL default '0',
  prop_Field varchar(5) NOT NULL default '0',
  prop_Name varchar(40) default NULL,
  prop_Description varchar(60) default NULL,
  type_ID smallint(5) unsigned NOT NULL default '0',
  prop_Special mediumint(9) unsigned default NULL,
  prop_PersonDisplay enum('false','true') NOT NULL default 'false'
) TYPE=MyISAM COMMENT='Group-specific properties order, name, description, type';

CREATE TABLE person_custom_master (
  custom_Order smallint(6) NOT NULL default '0',
  custom_Field varchar(5) NOT NULL default '',
  custom_Name varchar(40) NOT NULL default '',
  custom_Special mediumint(8) unsigned default NULL,
  custom_Side enum('left','right') NOT NULL default 'left',
  type_ID tinyint(4) NOT NULL default '0'
) TYPE=MyISAM;

CREATE TABLE person_custom (
  per_ID mediumint(9) NOT NULL default '0',
  PRIMARY KEY  (per_ID)
) TYPE=MyISAM;

CREATE TABLE list_lst (
  lst_ID mediumint(8) unsigned NOT NULL default '0',
  lst_OptionID mediumint(8) unsigned NOT NULL default '0',
  lst_OptionSequence tinyint(3) unsigned NOT NULL default '0',
  lst_OptionName varchar(50) NOT NULL default ''
) TYPE=MyISAM;

# Sample data for member classifications
INSERT INTO list_lst VALUES (1, 1, 1, 'Member');
INSERT INTO list_lst VALUES (1, 2, 2, 'Regular Attender');
INSERT INTO list_lst VALUES (1, 3, 3, 'Guest');
INSERT INTO list_lst VALUES (1, 5, 4, 'Non-Attender');
INSERT INTO list_lst VALUES (1, 4, 5, 'Non-Attender (staff)');

# Sample data for family roles
INSERT INTO list_lst VALUES (2, 1, 1, 'Head of Household');
INSERT INTO list_lst VALUES (2, 2, 2, 'Spouse');
INSERT INTO list_lst VALUES (2, 3, 3, 'Child');
INSERT INTO list_lst VALUES (2, 4, 4, 'Other Relative');
INSERT INTO list_lst VALUES (2, 5, 5, 'Non Relative');

# Sample data for group types
INSERT INTO list_lst VALUES (3, 1, 1, 'Ministry');
INSERT INTO list_lst VALUES (3, 2, 2, 'Team');
INSERT INTO list_lst VALUES (3, 3, 3, 'Bible Study');
INSERT INTO list_lst VALUES (3, 4, 4, 'Sunday School Class');

# Insert the custom-field / group-property types
INSERT INTO list_lst VALUES (4, 1, 1, 'True / False');
INSERT INTO list_lst VALUES (4, 2, 2, 'Date');
INSERT INTO list_lst VALUES (4, 3, 3, 'Text Field (50 char)');
INSERT INTO list_lst VALUES (4, 4, 4, 'Text Field (100 char)');
INSERT INTO list_lst VALUES (4, 5, 5, 'Text Field (Long)');
INSERT INTO list_lst VALUES (4, 6, 6, 'Year');
INSERT INTO list_lst VALUES (4, 7, 7, 'Season');
INSERT INTO list_lst VALUES (4, 8, 8, 'Number');
INSERT INTO list_lst VALUES (4, 9, 9, 'Person from Group');
INSERT INTO list_lst VALUES (4, 10, 10, 'Money');
INSERT INTO list_lst VALUES (4, 11, 11, 'Phone Number');
INSERT INTO list_lst VALUES (4, 12, 12, 'Custom Drop-Down List');
