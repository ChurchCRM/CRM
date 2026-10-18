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

ALTER TABLE `email_log_eml`
  ADD COLUMN `eml_Batch`       varchar(32)         DEFAULT NULL AFTER `eml_DateSent`,
  ADD COLUMN `eml_Attempts`    tinyint(3) unsigned NOT NULL DEFAULT 0 AFTER `eml_Batch`,
  ADD COLUMN `eml_NextAttempt` datetime            DEFAULT NULL AFTER `eml_Attempts`,
  ADD KEY `idx_eml_Batch` (`eml_Batch`);

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

ALTER TABLE `person_per`
  ADD COLUMN `per_grd_ID` mediumint(8) unsigned DEFAULT NULL AFTER `per_NeedsReview`,
  ADD INDEX `idx_per_grd_ID` (`per_grd_ID`);

-- List IDs below 10 are reserved for permanent lists, but group role lists
-- used to take MAX(lst_ID)+1, so a database that started on ChurchInfo can
-- already use 6. The grades list takes 6 when nothing refers to it and
-- otherwise the next ID no list or reference uses, recorded in iGradeListId.
SET @grade_list_in_use = (SELECT COUNT(*) FROM `list_lst` WHERE `lst_ID` = 6)
  + (SELECT COUNT(*) FROM `group_grp` WHERE `grp_RoleListID` = 6)
  + (SELECT COUNT(*) FROM `person_custom_master` WHERE `type_ID` = 12 AND `custom_Special` = 6)
  + (SELECT COUNT(*) FROM `family_custom_master` WHERE `type_ID` = 12 AND `fam_custom_Special` = 6)
  + (SELECT COUNT(*) FROM `groupprop_master` WHERE `type_ID` = 12 AND `prop_Special` = 6);

SET @grade_list_id = IF(@grade_list_in_use = 0, 6, GREATEST(
  10,
  (SELECT COALESCE(MAX(`lst_ID`), 0) + 1 FROM `list_lst`),
  (SELECT COALESCE(MAX(`grp_RoleListID`), 0) + 1 FROM `group_grp`),
  (SELECT COALESCE(MAX(`custom_Special`), 0) + 1 FROM `person_custom_master` WHERE `type_ID` = 12),
  (SELECT COALESCE(MAX(`fam_custom_Special`), 0) + 1 FROM `family_custom_master` WHERE `type_ID` = 12),
  (SELECT COALESCE(MAX(`prop_Special`), 0) + 1 FROM `groupprop_master` WHERE `type_ID` = 12)
));

INSERT INTO `list_lst` (`lst_ID`, `lst_OptionID`, `lst_OptionSequence`, `lst_OptionName`) VALUES
  (@grade_list_id, 4, 1, 'Pre-K'),
  (@grade_list_id, 5, 2, 'Kindergarten'),
  (@grade_list_id, 6, 3, '1st'),
  (@grade_list_id, 7, 4, '2nd'),
  (@grade_list_id, 8, 5, '3rd'),
  (@grade_list_id, 9, 6, '4th'),
  (@grade_list_id, 10, 7, '5th'),
  (@grade_list_id, 11, 8, '6th'),
  (@grade_list_id, 12, 9, '7th'),
  (@grade_list_id, 13, 10, '8th'),
  (@grade_list_id, 14, 11, '9th'),
  (@grade_list_id, 15, 12, '10th'),
  (@grade_list_id, 16, 13, '11th'),
  (@grade_list_id, 17, 14, '12th');

INSERT INTO `config_cfg` (`cfg_name`, `cfg_value`)
SELECT 'iGradeListId', @grade_list_id FROM DUAL WHERE @grade_list_id <> 6;

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
