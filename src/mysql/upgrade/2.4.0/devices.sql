CREATE TABLE 'kioskdevice_kdev' ( 
  `kdev_ID` mediumint(9) unsigned NOT NULL AUTO_INCREMENT,
  `kdev_GUID` char(36) DEFAULT NULL,
  `kdev_Name` varchar(50) DEFAULT NULL,
  `kdev_deviceType` enum('Sunday School Classroom Kisok','Self Registration Kiosk', 'Child Check-In Kiosk') COLLATE utf8_unicode_ci NOT NULL DEFAULT 'text',
  `kdev_deviceConfiguration` text COLLATE utf8_unicode_ci
)
