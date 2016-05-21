--
-- remove old table `version_ver`
--

DROP TABLE version_ver;

--
-- Table structure for table `version_ver`
--

CREATE TABLE `version_ver` (
  `ver_ID` mediumint(9) unsigned NOT NULL auto_increment,
  `ver_version` varchar(50) NOT NULL default '',
  `ver_update_start` datetime default NULL,
  `ver_update_end` datetime default NULL,
  PRIMARY KEY  (`ver_ID`),
  UNIQUE KEY `ver_version` (`ver_version`)
) ENGINE=MyISAM CHARACTER SET utf8 COLLATE utf8_unicode_ci  AUTO_INCREMENT=1 ;

--
-- Dumping data for table `version_ver`
--

INSERT INTO version_ver (ver_version, ver_update_start)
VALUES ('2.1.0', now());



update version_ver set ver_update_end = now();
