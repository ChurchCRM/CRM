DROP TABLE IF EXISTS `kioskdevice_kdev`;
CREATE TABLE `kioskdevice_kdev` ( 
  `kdev_ID` mediumint(9) unsigned NOT NULL AUTO_INCREMENT,
  `kdev_GUIDHash` char(64) DEFAULT NULL,
  `kdev_Name` varchar(50) DEFAULT NULL,
  `kdev_deviceType` mediumint(9) NOT NULL DEFAULT 0,
  `kdev_deviceConfiguration` text,
  `kdev_lastHeartbeat` TIMESTAMP,
  PRIMARY KEY  (`kdev_ID`),
  UNIQUE KEY `kdev_ID` (`kdev_ID`)
) ENGINE=MyISAM CHARACTER SET utf8 COLLATE utf8_unicode_ci;


  
INSERT INTO `menuconfig_mcf` (`mid`, `name`, `parent`, `ismenu`, `content_english`, `content`, `uri`, `statustext`, `security_grp`, `session_var`, `session_var_in_text`, `session_var_in_uri`, `url_parm_name`, `active`, `sortorder`, `icon`) VALUES
(83, 'kioskmanager', 'root', 0, 'Kiosk Manager', 'Kiosk Manager', 'KioskManager.php', '', 'bAll', NULL, 0, 0, NULL, 1, 5, 'fa-laptop');

