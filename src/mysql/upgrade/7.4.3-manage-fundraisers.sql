-- 7.4.1: Add usr_ManageFundraisers permission column to user_usr
-- Grants users the ability to access and manage fundraiser pages.
-- Admins always retain access regardless of this flag.
--
-- Note: plain ALTER TABLE (no IF NOT EXISTS) is safe here because the upgrade
-- runner is version-gated: fresh installs set the DB version to the current
-- release via installChurchCRMSchema() and never execute historical migration
-- scripts. IF NOT EXISTS is a MariaDB-only extension unsupported by MySQL.
ALTER TABLE `user_usr` ADD COLUMN `usr_ManageFundraisers` tinyint(1) unsigned NOT NULL DEFAULT 0 AFTER `usr_Finance`;
