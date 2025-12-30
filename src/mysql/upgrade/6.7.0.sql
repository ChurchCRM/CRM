-- Add order column to donation funds table for custom sorting
ALTER TABLE donationfund_fun ADD COLUMN fun_Order INT NOT NULL DEFAULT 0 AFTER fun_Description;

-- Initialize order values based on current fund IDs
SET @row_number = 0;
UPDATE donationfund_fun
SET fun_Order = (@row_number:=@row_number + 1)
ORDER BY fun_ID;

-- Remove unused evctnm_notes column from eventcountnames_evctnm table
ALTER TABLE eventcountnames_evctnm DROP COLUMN evctnm_notes;

-- Remove obsolete pending email tables that were never fully implemented
DROP TABLE IF EXISTS `email_recipient_pending_erp`;
DROP TABLE IF EXISTS `email_message_pending_emp`;

-- Migrate Query ID 32 'Family Pledge by Fiscal Year' to Finance module MVC page
-- Remove query and related data (now available at /finance/pledge/dashboard)
DELETE FROM queryparameteroptions_qpo WHERE qpo_qrp_ID IN (SELECT qrp_ID FROM queryparameters_qrp WHERE qrp_qry_ID = 32);
DELETE FROM queryparameters_qrp WHERE qrp_qry_ID = 32;
DELETE FROM query_qry WHERE qry_ID = 32;

-- Update aFinanceQueries config to remove Query ID 32
UPDATE config_cfg SET cfg_value = '28,30,31' WHERE cfg_name = 'aFinanceQueries' AND cfg_value LIKE '%32%';
