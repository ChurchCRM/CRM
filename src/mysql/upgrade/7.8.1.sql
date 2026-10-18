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
