-- ChurchCRM 7.4.3 consolidated upgrade script
-- Merged from individual scripts on 2026-10-10
-- Order preserved from original upgrade.json block


-- ============================================================================
-- BEGIN: 7.4.3-manage-fundraisers.sql
-- ============================================================================

-- 7.4.1: Add usr_ManageFundraisers permission column to user_usr
-- Grants users the ability to access and manage fundraiser pages.
-- Admins always retain access regardless of this flag.
--
-- Note: plain ALTER TABLE (no IF NOT EXISTS) is safe here because the upgrade
-- runner is version-gated: fresh installs set the DB version to the current
-- release via installChurchCRMSchema() and never execute historical migration
-- scripts. IF NOT EXISTS is a MariaDB-only extension unsupported by MySQL.
ALTER TABLE `user_usr` ADD COLUMN `usr_ManageFundraisers` tinyint(1) unsigned NOT NULL DEFAULT 0 AFTER `usr_Finance`;

-- END: 7.4.3-manage-fundraisers.sql


-- ============================================================================
-- BEGIN: 7.4.3-remove-directory-csv-permissions.sql
-- ============================================================================

-- ChurchCRM 7.4.3 Data Migration
-- Remove low-value per-user security permission flags from userconfig_ucfg:
--   bCreateDirectory (ucfg_id = 5) and bExportCSV (ucfg_id = 6)
--
-- Background (issue #9183):
--   bExportCSV was already gated by isAdmin() in CSVExport.php, making the
--   per-user flag redundant. bCreateDirectory gated the directory-report pages;
--   those are now open to any authenticated user under the read-default policy.
--   Both flags are removed from the PHP permission model in this release.
--
--   bExportCSV rows were already deleted for users of migration 6.8.0 (via
--   "DELETE FROM userconfig_ucfg WHERE ucfg_name = 'bExportCSV'"). This script
--   is idempotent: deleting rows that no longer exist is a no-op.
--
-- Idempotent: safe to re-run; rows already absent are silently skipped.
DELETE FROM `userconfig_ucfg` WHERE `ucfg_name` IN ('bCreateDirectory', 'bExportCSV');

-- END: 7.4.3-remove-directory-csv-permissions.sql
