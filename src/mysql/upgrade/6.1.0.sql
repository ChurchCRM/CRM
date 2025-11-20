-- Migration to make deposit types and payment methods configurable
-- Change dep_Type from ENUM to VARCHAR to allow dynamic deposit types
-- Change plg_method from ENUM to VARCHAR to allow dynamic payment methods

ALTER TABLE `deposit_dep` 
  MODIFY COLUMN `dep_Type` VARCHAR(50) NOT NULL DEFAULT 'Bank';

ALTER TABLE `pledge_plg`
  MODIFY COLUMN `plg_method` VARCHAR(50) DEFAULT NULL;
