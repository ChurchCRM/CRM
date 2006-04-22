ALTER TABLE  `config_cfg` CHANGE  `cfg_value`  `cfg_value` TEXT DEFAULT NULL , CHANGE  `cfg_type`  `cfg_type` ENUM(  'text',  'number',  'date',  'boolean',  'textarea' ) DEFAULT  'text' NOT NULL , CHANGE  `cfg_default`  `cfg_default` TEXT NOT NULL;
INSERT IGNORE INTO `config_cfg` VALUES (89, 'sISTusername', 'username', 'text', 'username', 'Intelligent Search Technolgy, Ltd. CorrectAddress Username for https://www.name-searching.com/CaddressASP', 'General');
INSERT IGNORE INTO `config_cfg` VALUES (90, 'sISTpassword', '', 'text', '', 'Intelligent Search Technolgy, Ltd. CorrectAddress Password for https://www.name-searching.com/CaddressASP', 'General');
UPDATE  `config_cfg` SET  `cfg_type` =  'textarea', `cfg_tooltip` =  'Enter in HTML code which will be displayed as a header at the top of each page. Be sure to close your tags! Note: You must REFRESH YOUR BROWSER A SECOND TIME to view the new header.' WHERE  `cfg_id` = 88 LIMIT 1 ;

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
