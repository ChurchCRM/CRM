-- 7.4.1: Add usr_ManageFundraisers permission column to user_usr
-- Grants users the ability to access and manage fundraiser pages.
-- Admins always retain access regardless of this flag.
ALTER TABLE `user_usr` ADD COLUMN IF NOT EXISTS `usr_ManageFundraisers` tinyint(1) unsigned NOT NULL DEFAULT 0 AFTER `usr_Finance`;
