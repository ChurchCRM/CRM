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

-- Remove family/person data inheritance configs
-- IMPORTANT: Migrate family data to person records first (before configs are removed)
-- This ensures no data loss when family inheritance feature is disabled

-- Populate person address fields from family when person data is empty
UPDATE person_per p 
INNER JOIN family_fam f ON p.per_fam_ID = f.fam_ID
SET 
  p.per_Address1 = IF(p.per_Address1 = '' OR p.per_Address1 IS NULL, f.fam_Address1, p.per_Address1),
  p.per_Address2 = IF(p.per_Address2 = '' OR p.per_Address2 IS NULL, f.fam_Address2, p.per_Address2),
  p.per_City = IF(p.per_City = '' OR p.per_City IS NULL, f.fam_City, p.per_City),
  p.per_State = IF(p.per_State = '' OR p.per_State IS NULL, f.fam_State, p.per_State),
  p.per_Zip = IF(p.per_Zip = '' OR p.per_Zip IS NULL, f.fam_Zip, p.per_Zip),
  p.per_Country = IF(p.per_Country = '' OR p.per_Country IS NULL, f.fam_Country, p.per_Country)
WHERE p.per_fam_ID > 0;

-- Populate person phone fields from family when person data is empty
UPDATE person_per p 
INNER JOIN family_fam f ON p.per_fam_ID = f.fam_ID
SET 
  p.per_HomePhone = IF(p.per_HomePhone = '' OR p.per_HomePhone IS NULL, f.fam_HomePhone, p.per_HomePhone),
  p.per_WorkPhone = IF(p.per_WorkPhone = '' OR p.per_WorkPhone IS NULL, f.fam_WorkPhone, p.per_WorkPhone),
  p.per_CellPhone = IF(p.per_CellPhone = '' OR p.per_CellPhone IS NULL, f.fam_CellPhone, p.per_CellPhone)
WHERE p.per_fam_ID > 0;

-- Populate person email from family when person data is empty
UPDATE person_per p 
INNER JOIN family_fam f ON p.per_fam_ID = f.fam_ID
SET 
  p.per_Email = IF(p.per_Email = '' OR p.per_Email IS NULL, f.fam_Email, p.per_Email)
WHERE p.per_fam_ID > 0;

-- Populate person last name from family when person last name is empty (inherit family name)
UPDATE person_per p 
INNER JOIN family_fam f ON p.per_fam_ID = f.fam_ID
SET 
  p.per_LastName = f.fam_Name
WHERE p.per_fam_ID > 0 AND (p.per_LastName = '' OR p.per_LastName IS NULL);

-- Delete family/person data inheritance configs
-- Each person must now enter their own data - no automatic inheritance from family records
DELETE FROM config_cfg WHERE cfg_id = 33;   -- bShowFamilyData (removed: family data inheritance for display)
DELETE FROM config_cfg WHERE cfg_id = 2010; -- bAllowEmptyLastName (removed: last name now always required)

-- Drop egive_egv table (no longer used)
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
