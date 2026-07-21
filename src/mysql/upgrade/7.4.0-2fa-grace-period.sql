-- ChurchCRM 7.4.0 — Mandatory 2FA grace period
-- Adds a per-user timestamp tracking when the 2FA mandate first applied to them.
--
-- Existing installs: column is NULL for all users. On first request after
-- upgrade under an active mandate, the auth layer populates it with NOW().
-- Installs without bRequire2FA are unaffected — nothing stamps the column.

ALTER TABLE `user_usr`
    ADD COLUMN IF NOT EXISTS `usr_TwoFactorAuthGracePeriodStart` TIMESTAMP NULL DEFAULT NULL
        AFTER `usr_TwoFactorAuthRecoveryCodes`;
