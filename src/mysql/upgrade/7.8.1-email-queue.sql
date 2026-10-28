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
