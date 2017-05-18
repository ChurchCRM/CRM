DROP TABLE IF EXISTS `kioskdevice_kdev`;
CREATE TABLE `kioskdevice_kdev` ( 
  `kdev_ID` mediumint(9) unsigned NOT NULL AUTO_INCREMENT,
  `kdev_GUIDHash` char(64) DEFAULT NULL,
  `kdev_Name` varchar(50) DEFAULT NULL,
  `kdev_deviceType` mediumint(9) NOT NULL DEFAULT 0,
  `kdev_deviceConfiguration` text,
  `kdev_lastHeartbeat` TIMESTAMP,
  `kdev_Accepted` BOOLEAN,
  `kdev_PendingCommands` varchar(50),

  PRIMARY KEY  (`kdev_ID`),
  UNIQUE KEY `kdev_ID` (`kdev_ID`)
) ENGINE=MyISAM CHARACTER SET utf8 COLLATE utf8_unicode_ci;

DROP TABLE IF EXISTS `kioskassginment_kasm`;
CREATE TABLE `kioskassginment_kasm` ( 
  `kasm_ID` mediumint(9) unsigned NOT NULL AUTO_INCREMENT,
  `kasm_kdevId` mediumint(9) DEFAULT NULL,
  `kasm_AssignmentType` mediumint(9) DEFAULT NULL,
`kasm_EventId` mediumint(9) DEFAULT 0,

  PRIMARY KEY  (`kasm_ID`),
  UNIQUE KEY `kasm_ID` (`kasm_ID`)
) ENGINE=MyISAM CHARACTER SET utf8 COLLATE utf8_unicode_ci;