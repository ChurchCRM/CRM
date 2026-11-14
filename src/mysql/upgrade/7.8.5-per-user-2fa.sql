-- Per-user "Require 2FA" (#9690): an administrator can require two-factor
-- authentication for one login, even when bRequire2FA is off for everyone.
-- Guarded so the file can run again after a partial run.

SET @_sql = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='user_usr' AND COLUMN_NAME='usr_TwoFactorAuthRequired')=0,
    'ALTER TABLE `user_usr` ADD COLUMN `usr_TwoFactorAuthRequired` tinyint(1) unsigned NOT NULL DEFAULT ''0'' AFTER `usr_TwoFactorAuthGracePeriodStart`',
    'DO 0'
);
PREPARE _s FROM @_sql;
EXECUTE _s;
DEALLOCATE PREPARE _s;
