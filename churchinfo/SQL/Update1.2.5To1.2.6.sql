INSERT IGNORE INTO `config_cfg` VALUES (89, 'sISTusername', 'username', 'text', 'username', 'Intelligent Search Technolgy, Ltd. CorrectAddress Username for https://www.name-searching.com/CaddressASP', 'General');
INSERT IGNORE INTO `config_cfg` VALUES (90, 'sISTpassword', '', 'text', '', 'Intelligent Search Technolgy, Ltd. CorrectAddress Password for https://www.name-searching.com/CaddressASP', 'General');

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
  PRIMARY KEY  (`lu_fam_ID`)
) TYPE=MyISAM COMMENT='US Address Verification Lookups From Intelligent Search Technology (IST)';
