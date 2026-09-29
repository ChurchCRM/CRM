-- Add order column to donation funds table for custom sorting.
-- Plain ALTER TABLE (no IF NOT EXISTS): that clause is MariaDB-only; the version-gated
-- upgrade runner guarantees this script never runs on a DB that already has this column.
ALTER TABLE donationfund_fun ADD COLUMN fun_Order INT NOT NULL DEFAULT 0 AFTER fun_Description;

-- Initialize order values based on current fund IDs.
-- Use ROW_NUMBER() window function (MySQL 8.0+, MariaDB 10.2+) instead of the
-- deprecated @var:=expr DML syntax (removed in MySQL 9.0, deprecated since 8.0.22).
UPDATE donationfund_fun f
JOIN (
    SELECT fun_ID, ROW_NUMBER() OVER (ORDER BY fun_ID) AS rn
    FROM donationfund_fun
) ranked ON f.fun_ID = ranked.fun_ID
SET f.fun_Order = ranked.rn;

-- Remove unused evctnm_notes column from eventcountnames_evctnm table.
-- NOTE: This column is present only in databases migrated from ChurchInfo 1.x;
-- fresh CRM installs (Install.sql) do not have it. Use dynamic SQL to guard
-- the drop conditionally (DROP COLUMN IF EXISTS is MariaDB-only, not MySQL).
SET @_sql = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='eventcountnames_evctnm' AND COLUMN_NAME='evctnm_notes')>0,'ALTER TABLE `eventcountnames_evctnm` DROP COLUMN `evctnm_notes`','DO 0');
PREPARE _s FROM @_sql; EXECUTE _s; DEALLOCATE PREPARE _s;

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
