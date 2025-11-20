-- Migration to make deposit types configurable
-- Change dep_Type from ENUM to VARCHAR to allow dynamic types

ALTER TABLE `deposit_dep` 
  MODIFY COLUMN `dep_Type` VARCHAR(50) NOT NULL DEFAULT 'Bank';
