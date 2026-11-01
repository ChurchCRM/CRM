-- ChurchCRM 7.8.1 upgrade script

-- #9773: email_count and email_list were created by rebuild_views.sql on every
-- upgrade and restore, and nothing reads them.
DROP VIEW IF EXISTS `email_count`;
DROP VIEW IF EXISTS `email_list`;

-- Migration: queue composer email in email_log_eml (issue #10356)
--
-- POST /api/email/send no longer sends inside the request. It writes one row per
-- recipient with eml_Status 'queued', and EmailQueueService sends them in bounded
-- batches (the composer while it is open, then the timer jobs).
--
--   eml_Batch        the composer send the row belongs to; NULL for a direct send
--   eml_Attempts     delivery attempts so far (a queued row is retried up to 3 times)
--   eml_NextAttempt  queued: when the row is due; sending: when a drain that died
--                    holding the row may be taken over
--
-- Existing rows were each one attempt, except the skipped ones, which never tried.

SET @upgrade_sql = IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS
   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'email_log_eml' AND COLUMN_NAME = 'eml_Batch') = 0,
  'ALTER TABLE `email_log_eml` ADD COLUMN `eml_Batch` varchar(32) DEFAULT NULL AFTER `eml_DateSent`',
  'DO 0'
);
PREPARE upgrade_stmt FROM @upgrade_sql;
EXECUTE upgrade_stmt;
DEALLOCATE PREPARE upgrade_stmt;

SET @upgrade_sql = IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS
   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'email_log_eml' AND COLUMN_NAME = 'eml_Attempts') = 0,
  'ALTER TABLE `email_log_eml` ADD COLUMN `eml_Attempts` tinyint(3) unsigned NOT NULL DEFAULT 0 AFTER `eml_Batch`',
  'DO 0'
);
PREPARE upgrade_stmt FROM @upgrade_sql;
EXECUTE upgrade_stmt;
DEALLOCATE PREPARE upgrade_stmt;

SET @upgrade_sql = IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS
   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'email_log_eml' AND COLUMN_NAME = 'eml_NextAttempt') = 0,
  'ALTER TABLE `email_log_eml` ADD COLUMN `eml_NextAttempt` datetime DEFAULT NULL AFTER `eml_Attempts`',
  'DO 0'
);
PREPARE upgrade_stmt FROM @upgrade_sql;
EXECUTE upgrade_stmt;
DEALLOCATE PREPARE upgrade_stmt;

SET @upgrade_sql = IF(
  (SELECT COUNT(*) FROM information_schema.STATISTICS
   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'email_log_eml' AND INDEX_NAME = 'idx_eml_Batch') = 0,
  'ALTER TABLE `email_log_eml` ADD KEY `idx_eml_Batch` (`eml_Batch`)',
  'DO 0'
);
PREPARE upgrade_stmt FROM @upgrade_sql;
EXECUTE upgrade_stmt;
DEALLOCATE PREPARE upgrade_stmt;

UPDATE `email_log_eml` SET `eml_Attempts` = 1 WHERE `eml_Status` IN ('sent', 'failed');


-- #10561: a new installation starts on Volunteer Management V2. An upgraded
-- installation that never chose a version was on V1, the old default, and stays
-- there. cfg_name is the primary key, so INSERT IGNORE keeps a choice already made.
INSERT IGNORE INTO `config_cfg` (`cfg_name`, `cfg_value`) VALUES ('sVolunteerVersion', 'v1');

-- Churches that changed the reminder lead keep their alert lead; others get the 48-hour default.
INSERT IGNORE INTO `config_cfg` (`cfg_name`, `cfg_value`)
SELECT 'iVolunteerGapAlertLeadHours', `cfg_value` FROM `config_cfg` WHERE `cfg_name` = 'iVolunteerReminderLeadHours';

ALTER TABLE `volunteer_notification_vntf`
  MODIFY COLUMN `vntf_Type` enum('assignment','reminder','decline_alert','gap_alert','signup_confirm','swap_proposed','swap_resolved','help_offer','pool_add') NOT NULL;

-- #10189: school grade on each person, from a church-defined list.
--
-- per_grd_ID is the lst_OptionID of the person's grade in the grades list.
-- NULL means no grade, so every existing person starts without one.
-- A grade's lst_OptionID is its level, the typical age at the start of the
-- school year (Pre-K 4 ... 12th 17), so switching to another country's
-- grade names renames options without touching anyone's grade.

SET @upgrade_sql = IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS
   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'person_per' AND COLUMN_NAME = 'per_grd_ID') = 0,
  'ALTER TABLE `person_per` ADD COLUMN `per_grd_ID` mediumint(8) unsigned DEFAULT NULL AFTER `per_NeedsReview`',
  'DO 0'
);
PREPARE upgrade_stmt FROM @upgrade_sql;
EXECUTE upgrade_stmt;
DEALLOCATE PREPARE upgrade_stmt;

SET @upgrade_sql = IF(
  (SELECT COUNT(*) FROM information_schema.STATISTICS
   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'person_per' AND INDEX_NAME = 'idx_per_grd_ID') = 0,
  'ALTER TABLE `person_per` ADD INDEX `idx_per_grd_ID` (`per_grd_ID`)',
  'DO 0'
);
PREPARE upgrade_stmt FROM @upgrade_sql;
EXECUTE upgrade_stmt;
DEALLOCATE PREPARE upgrade_stmt;

-- List IDs below 10 are reserved for permanent lists, but group role lists
-- used to take MAX(lst_ID)+1, so a database that started on ChurchInfo can
-- already use 6. The grades list takes 6 when nothing refers to it and
-- otherwise the next ID no list or reference uses, recorded in iGradeListId.
-- A retry may start after the rows were inserted but before iGradeListId was saved.
-- Recover that list by its complete seed rows before allocating another ID.
SET @existing_grade_list_id = COALESCE(
  (SELECT CAST(`cfg_value` AS UNSIGNED) FROM `config_cfg` WHERE `cfg_name` = 'iGradeListId'),
  (SELECT MIN(grades.`lst_ID`) FROM (
    SELECT `lst_ID` FROM `list_lst`
    WHERE `lst_ID` = 6 OR `lst_ID` >= 10
    GROUP BY `lst_ID`
    HAVING COUNT(*) = 14 AND SUM(
      `lst_OptionSequence` + 3 = `lst_OptionID`
      AND `lst_OptionName` = CASE `lst_OptionID`
        WHEN 4 THEN 'Pre-K' WHEN 5 THEN 'Kindergarten'
        WHEN 6 THEN '1st' WHEN 7 THEN '2nd' WHEN 8 THEN '3rd'
        WHEN 9 THEN '4th' WHEN 10 THEN '5th' WHEN 11 THEN '6th'
        WHEN 12 THEN '7th' WHEN 13 THEN '8th' WHEN 14 THEN '9th'
        WHEN 15 THEN '10th' WHEN 16 THEN '11th' WHEN 17 THEN '12th'
      END
    ) = 14
  ) AS grades)
);

SET @grade_list_in_use = (SELECT COUNT(*) FROM `list_lst` WHERE `lst_ID` = 6)
  + (SELECT COUNT(*) FROM `group_grp` WHERE `grp_RoleListID` = 6)
  + (SELECT COUNT(*) FROM `person_custom_master` WHERE `type_ID` = 12 AND `custom_Special` = 6)
  + (SELECT COUNT(*) FROM `family_custom_master` WHERE `type_ID` = 12 AND `fam_custom_Special` = 6)
  + (SELECT COUNT(*) FROM `groupprop_master` WHERE `type_ID` = 12 AND `prop_Special` = 6);

SET @grade_list_id = COALESCE(@existing_grade_list_id, IF(@grade_list_in_use = 0, 6, GREATEST(
  10,
  (SELECT COALESCE(MAX(`lst_ID`), 0) + 1 FROM `list_lst`),
  (SELECT COALESCE(MAX(`grp_RoleListID`), 0) + 1 FROM `group_grp`),
  (SELECT COALESCE(MAX(`custom_Special`), 0) + 1 FROM `person_custom_master` WHERE `type_ID` = 12),
  (SELECT COALESCE(MAX(`fam_custom_Special`), 0) + 1 FROM `family_custom_master` WHERE `type_ID` = 12),
  (SELECT COALESCE(MAX(`prop_Special`), 0) + 1 FROM `groupprop_master` WHERE `type_ID` = 12)
)));

SET @upgrade_sql = IF(@existing_grade_list_id IS NULL,
  'INSERT INTO `list_lst` (`lst_ID`, `lst_OptionID`, `lst_OptionSequence`, `lst_OptionName`) VALUES
  (@grade_list_id, 4, 1, ''Pre-K''),
  (@grade_list_id, 5, 2, ''Kindergarten''),
  (@grade_list_id, 6, 3, ''1st''),
  (@grade_list_id, 7, 4, ''2nd''),
  (@grade_list_id, 8, 5, ''3rd''),
  (@grade_list_id, 9, 6, ''4th''),
  (@grade_list_id, 10, 7, ''5th''),
  (@grade_list_id, 11, 8, ''6th''),
  (@grade_list_id, 12, 9, ''7th''),
  (@grade_list_id, 13, 10, ''8th''),
  (@grade_list_id, 14, 11, ''9th''),
  (@grade_list_id, 15, 12, ''10th''),
  (@grade_list_id, 16, 13, ''11th''),
  (@grade_list_id, 17, 14, ''12th'')',
  'DO 0'
);
PREPARE upgrade_stmt FROM @upgrade_sql;
EXECUTE upgrade_stmt;
DEALLOCATE PREPARE upgrade_stmt;

INSERT INTO `config_cfg` (`cfg_name`, `cfg_value`)
SELECT 'iGradeListId', @grade_list_id FROM DUAL WHERE @grade_list_id <> 6
  AND NOT EXISTS (SELECT 1 FROM `config_cfg` WHERE `cfg_name` = 'iGradeListId');

-- #10725: a payment could get the GroupKey of a pledge for the same family,
-- fund and date, and delete and edit act on every row with that key. In each
-- group that holds both types, the payment rows move to the old key plus the
-- lowest plg_plgID among them. Generated keys have five parts, so the new key
-- matches no other group. The payment's cash counts move with it. Groups of
-- one type stay as they are, and so does a key too long to extend (no
-- generated key is). The derived table is materialized by its GROUP BY, which
-- lets MySQL read pledge_plg while updating it.

UPDATE `pledge_denominations_pdem` AS pdem
JOIN (
  SELECT `plg_GroupKey` AS old_key,
         CONCAT(`plg_GroupKey`, '|', MIN(IF(`plg_PledgeOrPayment` = 'Payment', `plg_plgID`, NULL))) AS new_key
  FROM `pledge_plg`
  GROUP BY `plg_GroupKey`
  HAVING SUM(`plg_PledgeOrPayment` = 'Pledge') > 0 AND SUM(`plg_PledgeOrPayment` = 'Payment') > 0
     AND CHAR_LENGTH(new_key) <= 64
) AS rekey ON rekey.old_key = pdem.`pdem_plg_GroupKey`
SET pdem.`pdem_plg_GroupKey` = rekey.new_key;

UPDATE `pledge_plg` AS plg
JOIN (
  SELECT `plg_GroupKey` AS old_key,
         CONCAT(`plg_GroupKey`, '|', MIN(IF(`plg_PledgeOrPayment` = 'Payment', `plg_plgID`, NULL))) AS new_key
  FROM `pledge_plg`
  GROUP BY `plg_GroupKey`
  HAVING SUM(`plg_PledgeOrPayment` = 'Pledge') > 0 AND SUM(`plg_PledgeOrPayment` = 'Payment') > 0
     AND CHAR_LENGTH(new_key) <= 64
) AS rekey ON rekey.old_key = plg.`plg_GroupKey`
SET plg.`plg_GroupKey` = rekey.new_key
WHERE plg.`plg_PledgeOrPayment` = 'Payment';
