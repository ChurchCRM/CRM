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

-- Remove query #21 ("Registered students") and all related child rows
-- Delete any cached results for the query
DELETE r FROM result_res r
	WHERE r.res_qry_ID = 21;

-- Delete any parameter option rows that belong to parameters for query 21
DELETE qpo FROM queryparameteroptions_qpo qpo
	JOIN queryparameters_qrp qrp ON qpo.qpo_qrp_ID = qrp.qrp_ID
	WHERE qrp.qrp_qry_ID = 21;

-- Delete query parameters for query 21
DELETE FROM queryparameters_qrp WHERE qrp_qry_ID = 21;

-- Finally delete the query definition itself
DELETE FROM query_qry WHERE qry_ID = 21;
