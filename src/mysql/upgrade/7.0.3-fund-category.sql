-- ChurchCRM 7.0.3 Donation Fund Category
-- Add fun_Category column to donationfund_fun table for better fund organization.

ALTER TABLE `donationfund_fun`
    ADD COLUMN `fun_Category` varchar(50) DEFAULT NULL;
