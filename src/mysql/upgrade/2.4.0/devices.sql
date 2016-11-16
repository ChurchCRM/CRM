CREATE TABLE `kioskdevice_kdev` ( 
  `kdev_ID` mediumint(9) unsigned NOT NULL AUTO_INCREMENT,
  `kdev_GUID` char(36) DEFAULT NULL,
  `kdev_Name` varchar(50) DEFAULT NULL,
  `kdev_deviceType` enum('Sunday School Classroom Kisok','Self Registration Kiosk', 'Child Check-In Kiosk') NOT NULL DEFAULT 'Sunday School Classroom Kisok',
  `kdev_deviceConfiguration` text,
  PRIMARY KEY  (`kdev_ID`),
  UNIQUE KEY `kdev_ID` (`kdev_ID`)
) ENGINE=MyISAM CHARACTER SET utf8 COLLATE utf8_unicode_ci;