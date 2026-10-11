-- Protected households and confidential notes (security audit F-01, F-02).
-- Every column defaults to 0 and the new table starts empty, so existing
-- records and notes keep their current visibility.
ALTER TABLE `family_fam`
  ADD COLUMN `fam_Confidential` tinyint(1) unsigned NOT NULL DEFAULT 0;

ALTER TABLE `note_nte`
  ADD COLUMN `nte_Confidential` tinyint(1) unsigned NOT NULL DEFAULT 0;

CREATE TABLE IF NOT EXISTS `note_recipient_nrc` (
  `nrc_nte_ID` mediumint(8) unsigned NOT NULL,
  `nrc_usr_ID` mediumint(8) unsigned NOT NULL,
  PRIMARY KEY (`nrc_nte_ID`,`nrc_usr_ID`),
  CONSTRAINT `fk_nrc_nte` FOREIGN KEY (`nrc_nte_ID`) REFERENCES `note_nte` (`nte_ID`) ON DELETE CASCADE
) ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
