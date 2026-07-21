-- ChurchCRM 7.5.1 Data Migration
-- Remove legacy per-user email delimiter settings.
--
-- Background:
--   sMailtoDelimiter (userconfig_ucfg) stored a per-user choice of delimiter
--   character for mailto: links. ui.email.delimiter (user_settings) was its
--   successor key introduced in the pre-6.0.0 consolidated migration. Both are
--   now obsolete: email delimiters always use the RFC 6068 comma format, and
--   the PHP code that read these settings has been updated to use ',' directly.
--
-- Idempotent: safe to re-run; rows already absent are silently skipped.
DELETE FROM `userconfig_ucfg` WHERE `ucfg_name` = 'sMailtoDelimiter';
DELETE FROM `user_settings` WHERE `setting_name` = 'ui.email.delimiter';
