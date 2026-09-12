-- Add per_DateDeactivated column to person_per table for activate/deactivate support (parity with family_fam)
ALTER TABLE `person_per` ADD COLUMN `per_DateDeactivated` date DEFAULT NULL;
