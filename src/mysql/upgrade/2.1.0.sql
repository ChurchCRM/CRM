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

-- ------ Notes #608 - start

ALTER TABLE note_nte
  ADD COLUMN nte_Type VARCHAR(45) NOT NULL DEFAULT 'note' AFTER nte_EditedBy;

INSERT INTO note_nte
	(nte_per_ID, nte_fam_ID, nte_Private, nte_Text, nte_EnteredBy, nte_DateEntered, nte_Type)
select per_id, 0, 0, "", per_EnteredBy, per_DateEntered, "create"
from person_per;

INSERT INTO note_nte
	(nte_per_ID, nte_fam_ID, nte_Private, nte_Text, nte_EnteredBy, nte_DateEntered, nte_Type)
select per_id, 0, 0, "", per_EditedBy, per_DateLastEdited, "edit"
from person_per
where per_DateLastEdited is not null;

-- ------ Notes #608 - end

update version_ver set ver_update_end = now();
