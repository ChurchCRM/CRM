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
