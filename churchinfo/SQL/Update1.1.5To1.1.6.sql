ALTER TABLE autopayment_aut ADD COLUMN (aut_FYID mediumint(9) NOT NULL default '9');

CREATE TABLE volunteeropportunity_vol (
  vol_ID tinyint(3) NOT NULL auto_increment,
  vol_Active enum('true','false') NOT NULL default 'true',
  vol_Name varchar(30) default NULL,
  vol_Description varchar(100) default NULL,
  PRIMARY KEY  (vol_ID),
  UNIQUE KEY vol_ID (vol_ID)
) TYPE=MyISAM;

CREATE TABLE person2volunteeropp_p2vo (
  p2vo_ID mediumint(9) NOT NULL auto_increment,
  p2vo_per_ID mediumint(9),
  p2vo_vol_ID mediumint(9),
  PRIMARY KEY (p2vo_ID),
  UNIQUE KEY (p2vo_ID)
) TYPE=MyISAM;

INSERT INTO query_qry VALUES (18,'SELECT per_ID as AddToCart, per_BirthDay as Day, CONCAT(per_FirstName,\' \',per_LastName) AS Name FROM person_per WHERE per_cls_ID=1 AND per_BirthMonth=~birthmonth~ ORDER BY per_BirthDay','Birthdays','Members with birthdays in a particular month',0);
INSERT INTO query_qry VALUES (19, 'SELECT per_ID as AddToCart, CONCAT(\'<a href=PersonView.php?PersonID=\',per_ID,\'>\',per_FirstName,\' \',per_LastName,\'</a>\') AS Name FROM person_per LEFT JOIN person2group2role_p2g2r ON per_id = p2g2r_per_ID LEFT JOIN group_grp a ON grp_ID = p2g2r_grp_ID LEFT JOIN list_lst b ON lst_ID = grp_RoleListID AND p2g2r_rle_ID = lst_OptionID WHERE lst_OptionName = \'Student\' AND grp_ID = ~group~ ORDER BY per_LastName', 'Class Students', 'Find students for a particular class', 1);
INSERT INTO query_qry VALUES (20, 'SELECT per_ID as AddToCart, CONCAT(\'<a href=PersonView.php?PersonID=\',per_ID,\'>\',per_FirstName,\' \',per_LastName,\'</a>\') AS Name FROM person_per LEFT JOIN person2group2role_p2g2r ON per_id = p2g2r_per_ID LEFT JOIN group_grp a ON grp_ID = p2g2r_grp_ID LEFT JOIN list_lst b ON lst_ID = grp_RoleListID AND p2g2r_rle_ID = lst_OptionID WHERE lst_OptionName = \'Teacher\' AND grp_ID = ~group~ ORDER BY per_LastName', 'Class Teachers', 'Find teachers for a particular class', 1);
INSERT INTO query_qry VALUES (21, 'SELECT per_ID as AddToCart, CONCAT(\'<a href=PersonView.php?PersonID=\',per_ID,\'>\',per_FirstName,\' \',per_LastName,\'</a>\') AS Name FROM person_per LEFT JOIN person2group2role_p2g2r ON per_id = p2g2r_per_ID WHERE p2g2r_grp_ID=~group~ ORDER BY per_LastName', 'Registered students', 'Find Registered students', 1);
INSERT INTO query_qry VALUES (22,'SELECT per_ID as AddToCart, DAYOFMONTH(per_MembershipDate) as Day, per_MembershipDate AS DATE, CONCAT(per_FirstName,\' \',per_LastName) AS Name FROM person_per WHERE per_cls_ID=1 AND MONTH(per_MembershipDate)=~membermonth~ ORDER BY per_MembershipDate','Membership anniversaries','Members who joined in a particular month',0);
INSERT INTO query_qry VALUES (23,'SELECT usr_per_ID as AddToCart, CONCAT(a.per_FirstName,\' \',a.per_LastName) AS Name FROM user_usr LEFT JOIN person_per a ON per_ID=usr_per_ID ORDER BY per_LastName','Select database users','People who are registered as database users',0);
INSERT INTO query_qry VALUES (24,'SELECT per_ID as AddToCart, CONCAT(\'<a href=PersonView.php?PersonID=\',per_ID,\'>\',per_FirstName,\' \',per_LastName,\'</a>\') AS Name FROM person_per WHERE per_cls_id =1','Select all members','People who are members',0);
INSERT INTO query_qry VALUES (25, 'SELECT per_ID as AddToCart, CONCAT(\'<a href=PersonView.php?PersonID=\',per_ID,\'>\',per_FirstName,\' \',per_LastName,\'</a>\') AS Name FROM person_per LEFT JOIN person2volunteeropp_p2vo ON per_id = p2vo_per_ID WHERE p2vo_vol_ID = ~volopp~ ORDER BY per_LastName', 'Volunteers', 'Find volunteers for a particular opportunity', 1);

INSERT INTO queryparameters_qrp VALUES (19,19,2,'SELECT grp_ID AS Value, grp_Name AS Display FROM group_grp ORDER BY grp_Type','Class','The sunday school class for which you would like records returned.','group','1',1,0,'',12,1,1,2);
INSERT INTO queryparameters_qrp VALUES (20,20,2,'SELECT grp_ID AS Value, grp_Name AS Display FROM group_grp ORDER BY grp_Type','Class','The sunday school class for which you would like records returned.','group','1',1,0,'',12,1,1,2);
INSERT INTO queryparameters_qrp VALUES (21,21,2,'SELECT grp_ID AS Value, grp_Name AS Display FROM group_grp ORDER BY grp_Type','Registered students','Group of registered students','group','1',1,0,'',12,1,1,2);
INSERT INTO queryparameters_qrp VALUES (22,22,0,'','Month','The membership anniversary month for which you would like records returned.','membermonth','1',1,0,'',12,1,1,2);
INSERT INTO queryparameters_qrp VALUES (25,25,2,'SELECT vol_ID AS Value, vol_Name AS Display FROM volunteeropportunity_vol ORDER BY vol_Name','Volunteer opportunities','Choose a volunteer opportunity','volopp','1',1,0,'',12,1,1,2);
