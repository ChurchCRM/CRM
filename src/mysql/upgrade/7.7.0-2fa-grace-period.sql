-- ChurchCRM 7.7.0 — Mandatory 2FA grace period
-- Adds a per-user timestamp tracking when the 2FA mandate first applied to them.
--
-- Existing installs: column is NULL for all users. On first request after
-- upgrade under an active mandate, the auth layer populates it with NOW().
-- Installs without bRequire2FA are unaffected — nothing stamps the column.
--
-- Plain ADD COLUMN (no IF NOT EXISTS) — MySQL does not support
-- ADD COLUMN IF NOT EXISTS; the version-gated upgrade runner
-- (UpgradeService::upgradeDatabaseVersion, gated by mysql/upgrade.json)
-- guarantees this column does not yet exist when this script runs. See
-- src/mysql/upgrade/6.5.0.sql for the same rationale applied to DROP COLUMN.

ALTER TABLE `user_usr`
    ADD COLUMN `usr_TwoFactorAuthGracePeriodStart` TIMESTAMP NULL DEFAULT NULL
        AFTER `usr_TwoFactorAuthRecoveryCodes`;
