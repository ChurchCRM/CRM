-- ChurchCRM 6.5.0 Database Upgrade
-- 
-- Changes:
-- 1. Remove eGive feature (deprecated, never tested)
-- 2. Remove Work Phone and Cell Phone from family table (belong to individuals, not families)

-- Migrate existing eGive deposits to Bank type
UPDATE `deposit_dep` SET `dep_Type`='Bank' WHERE `dep_Type`='eGive';

-- Update deposit_dep table: remove 'eGive' from dep_Type enum
ALTER TABLE `deposit_dep` CHANGE COLUMN `dep_Type` `dep_Type` enum('Bank','CreditCard','BankDraft') NOT NULL default 'Bank';

-- Migrate existing EGIVE pledges to CHECK method
UPDATE `pledge_plg` SET `plg_method`='CHECK' WHERE `plg_method`='EGIVE';

-- Update pledge_plg table: remove 'EGIVE' from plg_method enum
ALTER TABLE `pledge_plg` CHANGE COLUMN `plg_method` `plg_method` enum('CREDITCARD','CHECK','CASH','BANKDRAFT') DEFAULT NULL;

-- Migrate family work phone and cell phone to person records before dropping columns
-- ONLY migrate WorkPhone and CellPhone since these are the only two fields being removed from family_fam
-- Migrate work phone and cell phone from family to person records
-- Copy values only when person fields are empty to preserve existing person-specific data
UPDATE person_per p 
INNER JOIN family_fam f ON p.per_fam_ID = f.fam_ID
SET 
  p.per_WorkPhone = IF(p.per_WorkPhone = '' OR p.per_WorkPhone IS NULL, f.fam_WorkPhone, p.per_WorkPhone),
  p.per_CellPhone = IF(p.per_CellPhone = '' OR p.per_CellPhone IS NULL, f.fam_CellPhone, p.per_CellPhone)
WHERE p.per_fam_ID > 0;

-- Drop egive table (no longer used)
DROP TABLE IF EXISTS `egive_egv`;

-- Remove unused iLogFileThreshold config (never implemented)
DELETE FROM `config_cfg` WHERE `cfg_id` = 2077;

-- Remove integrity check background job configs (now runs only from admin pages)
DELETE FROM `config_cfg` WHERE `cfg_id` IN (1044, 1045, 1046);

-- Remove software update check timer configs (runs on admin login instead)
DELETE FROM `config_cfg` WHERE `cfg_id` IN (2063, 2064);

-- Remove church registration config (registration now via self-service Google Form)
DELETE FROM `config_cfg` WHERE `cfg_id` = 999;

-- Remove orphaned database tables (created but never fully implemented)
DROP TABLE IF EXISTS `church_location_person`;
DROP TABLE IF EXISTS `church_location_role`;
DROP TABLE IF EXISTS `person_permission`;
DROP TABLE IF EXISTS `person_roles`;
DROP TABLE IF EXISTS `permissions`;
DROP TABLE IF EXISTS `roles`;

-- Remove Work Phone and Cell Phone from family table
-- Drop work phone and cell phone columns from family table
ALTER TABLE `family_fam` DROP COLUMN IF EXISTS `fam_WorkPhone`;
ALTER TABLE `family_fam` DROP COLUMN IF EXISTS `fam_CellPhone`;