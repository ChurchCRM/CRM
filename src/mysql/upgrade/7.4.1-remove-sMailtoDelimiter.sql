-- Remove legacy sMailtoDelimiter setting (comma is the RFC 6068 standard)
-- The per-user value was migrated to user_settings as 'ui.email.delimiter' in 4.3.0
-- but was never read back by application code, so both rows are safe to drop.
DELETE FROM userconfig_ucfg WHERE ucfg_name = 'sMailtoDelimiter';
DELETE FROM user_settings WHERE setting_name = 'ui.email.delimiter';
