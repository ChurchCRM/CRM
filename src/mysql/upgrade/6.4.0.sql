-- Remove eGive feature (deprecated, never tested)
-- 
-- Changes:
-- 1. Remove 'eGive' enum from deposit type field
-- 2. Remove 'EGIVE' enum from pledge method field
-- 3. Drop egive_egv lookup table

-- Update deposit_dep table: remove 'eGive' from dep_Type enum
ALTER TABLE `deposit_dep` CHANGE COLUMN `dep_Type` `dep_Type` enum('Bank','CreditCard','BankDraft') NOT NULL default 'Bank';

-- Update pledge_plg table: remove 'EGIVE' from plg_method enum
ALTER TABLE `pledge_plg` CHANGE COLUMN `plg_method` `plg_method` enum('CREDITCARD','CHECK','CASH','BANKDRAFT') DEFAULT NULL;

-- Drop egive_egv table (no longer used)
DROP TABLE IF EXISTS `egive_egv`;
