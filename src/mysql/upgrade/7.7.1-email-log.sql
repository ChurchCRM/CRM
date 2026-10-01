-- Migration: add email_log_eml (issue #9877)
--
-- One row per recipient for every email ChurchCRM sends through BaseEmail::send():
-- composer messages, birthday greetings, family verification links, account emails,
-- notifications and the SMTP test. eml_per_ID / eml_fam_ID link the row to the record the
-- address belonged to at send time (NULL when no record matched); eml_usr_ID is the user who
-- sent a composer message (NULL for automated sends). eml_Body holds the rendered HTML only
-- for email classes that allow it (composer); account emails never store a body because they
-- carry passwords and one-time tokens. Rows are kept indefinitely.

CREATE TABLE IF NOT EXISTS `email_log_eml` (
  `eml_ID`        int(10) unsigned      NOT NULL AUTO_INCREMENT,
  `eml_per_ID`    mediumint(8) unsigned DEFAULT NULL,
  `eml_fam_ID`    mediumint(8) unsigned DEFAULT NULL,
  `eml_usr_ID`    mediumint(9) unsigned DEFAULT NULL,
  `eml_Address`   varchar(255)          NOT NULL,
  `eml_Kind`      varchar(50)           NOT NULL,
  `eml_Subject`   varchar(255)          NOT NULL DEFAULT '',
  `eml_Body`      longtext              DEFAULT NULL,
  `eml_Status`    varchar(20)           NOT NULL,
  `eml_Error`     text                  DEFAULT NULL,
  `eml_MessageID` varchar(255)          DEFAULT NULL,
  `eml_DateSent`  datetime              NOT NULL,
  PRIMARY KEY (`eml_ID`),
  KEY `idx_eml_per_ID`   (`eml_per_ID`),
  KEY `idx_eml_fam_ID`   (`eml_fam_ID`),
  KEY `idx_eml_DateSent` (`eml_DateSent`),
  KEY `idx_eml_Status`   (`eml_Status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
