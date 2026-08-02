-- ChurchCRM 7.6.0 Security Migration — GHSA-qc2c-qmw4-52fp
-- Mark the Advanced Search "Field" parameter (qrp_ID=15) as an identifier type.
--
-- Background:
--   QueryView.php substitutes the ~searchwhat~ placeholder directly into SQL as
--   a column name. The new 'i' validation type causes ProcessSQL() to wrap the
--   value in backticks via escapeQueryParameter(), preventing SQL injection via
--   column-name parameters (GHSA-qc2c-qmw4-52fp / CWE-89).
--
-- Idempotent: safe to re-run; updating an already-correct value is a no-op.
UPDATE `queryparameters_qrp`
SET `qrp_Validation` = 'i'
WHERE `qrp_ID` = 15
  AND `qrp_Alias` = 'searchwhat';
