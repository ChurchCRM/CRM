-- Add order column to donation funds table for custom sorting (IF NOT EXISTS for idempotency)
ALTER TABLE donationfund_fun ADD COLUMN IF NOT EXISTS fun_Order INT NOT NULL DEFAULT 0 AFTER fun_Description;

-- Initialize order values based on current fund IDs
SET @row_number = 0;
UPDATE donationfund_fun
SET fun_Order = (@row_number:=@row_number + 1)
ORDER BY fun_ID;

-- Remove unused evctnm_notes column from eventcountnames_evctnm table (IF EXISTS for idempotency)
ALTER TABLE eventcountnames_evctnm DROP COLUMN IF EXISTS evctnm_notes;

-- Remove obsolete pending email tables that were never fully implemented
DROP TABLE IF EXISTS `email_recipient_pending_erp`;
DROP TABLE IF EXISTS `email_message_pending_emp`;

-- Migrate Query ID 32 'Family Pledge by Fiscal Year' to Finance module MVC page
-- Remove query and related data (now available at /finance/pledge/dashboard)
DELETE FROM queryparameteroptions_qpo WHERE qpo_qrp_ID IN (SELECT qrp_ID FROM queryparameters_qrp WHERE qrp_qry_ID = 32);
DELETE FROM queryparameters_qrp WHERE qrp_qry_ID = 32;
DELETE FROM query_qry WHERE qry_ID = 32;

-- Update aFinanceQueries config to remove Query ID 32
UPDATE config_cfg SET cfg_value = '28,30' WHERE cfg_name = 'aFinanceQueries' AND cfg_value LIKE '%32%';
-- Remove query #21 ("Registered students") and all related child rows
-- Delete any parameter option rows that belong to parameters for query 21
DELETE qpo FROM queryparameteroptions_qpo qpo
	JOIN queryparameters_qrp qrp ON qpo.qpo_qrp_ID = qrp.qrp_ID
	WHERE qrp.qrp_qry_ID = 21;

-- Delete query parameters for query 21
DELETE FROM queryparameters_qrp WHERE qrp_qry_ID = 21;

-- Finally delete the query definition itself
DELETE FROM query_qry WHERE qry_ID = 21;

-- Remove deprecated `sHeader` system config (was an XSS vector)
DELETE FROM config_cfg WHERE cfg_name = 'sHeader';
