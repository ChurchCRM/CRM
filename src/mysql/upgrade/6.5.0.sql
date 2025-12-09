-- Remove eGive feature (deprecated, never tested)
-- 
-- Changes:
-- 1. Migrate existing eGive/EGIVE data to standard types
-- 2. Remove 'eGive' enum from deposit type field
-- 3. Remove 'EGIVE' enum from pledge method field
-- 4. Drop egive_egv lookup table

-- Migrate existing eGive deposits to Bank type
UPDATE `deposit_dep` SET `dep_Type`='Bank' WHERE `dep_Type`='eGive';

-- Update deposit_dep table: remove 'eGive' from dep_Type enum
ALTER TABLE `deposit_dep` CHANGE COLUMN `dep_Type` `dep_Type` enum('Bank','CreditCard','BankDraft') NOT NULL default 'Bank';

-- Migrate existing EGIVE pledges to CHECK method
UPDATE `pledge_plg` SET `plg_method`='CHECK' WHERE `plg_method`='EGIVE';

-- Update pledge_plg table: remove 'EGIVE' from plg_method enum
ALTER TABLE `pledge_plg` CHANGE COLUMN `plg_method` `plg_method` enum('CREDITCARD','CHECK','CASH','BANKDRAFT') DEFAULT NULL;

-- Drop egive_egv table (no longer used)
DROP TABLE IF EXISTS `egive_egv`;

-- Remove unused iLogFileThreshold config (never implemented)
DELETE FROM `config_cfg` WHERE `cfg_id` = 2077;

-- Remove orphaned database tables (created but never fully implemented)
DROP TABLE IF EXISTS `church_location_person`;
DROP TABLE IF EXISTS `church_location_role`;
DROP TABLE IF EXISTS `person_permission`;
DROP TABLE IF EXISTS `person_roles`;
DROP TABLE IF EXISTS `permissions`;
DROP TABLE IF EXISTS `roles`;
