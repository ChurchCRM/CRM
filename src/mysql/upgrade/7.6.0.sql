-- ChurchCRM 7.6.0 consolidated upgrade script
-- Merged from individual scripts on 2026-10-10
-- Order preserved from original upgrade.json block


-- ============================================================================
-- BEGIN: 7.6.0-remove-orphaned-query-parameters.sql
-- ============================================================================

-- ChurchCRM 7.6.0 Security Migration — GHSA-qc2c-qmw4-52fp (CWE-89)
-- Remove orphaned predefined-query parameters and parameter options.
--
-- Background:
--   The 6.6.0 and 6.7.0 upgrades retired a number of predefined queries by
--   deleting rows from query_qry, but never deleted the child rows in
--   queryparameters_qrp / queryparameteroptions_qpo. Those orphans survive on
--   every upgraded install.
--
--   The orphans left behind by the retired "Advanced Search" query (qry_ID 15)
--   are the subject of GHSA-qc2c-qmw4-52fp: its 'searchwhat' parameter is
--   substituted into the WHERE clause as a bare column name, and its
--   queryparameteroptions_qpo rows store raw SQL column expressions as option
--   values. Deleting the rows removes the injection surface outright rather
--   than escaping around it.
--
--   Matching rows were also removed from Install.sql, so new installations
--   never carry them.
--
-- Order matters: options are identified through their parent parameter row, so
-- they must be deleted before the parameters themselves.
--
-- Idempotent: safe to re-run; a second run matches nothing.

DELETE qpo
FROM queryparameteroptions_qpo qpo
	LEFT JOIN queryparameters_qrp qrp ON qpo.qpo_qrp_ID = qrp.qrp_ID
	LEFT JOIN query_qry qry ON qrp.qrp_qry_ID = qry.qry_ID
WHERE qrp.qrp_ID IS NULL
	OR qry.qry_ID IS NULL;

DELETE qrp
FROM queryparameters_qrp qrp
	LEFT JOIN query_qry qry ON qrp.qrp_qry_ID = qry.qry_ID
WHERE qry.qry_ID IS NULL;

-- END: 7.6.0-remove-orphaned-query-parameters.sql


-- ============================================================================
-- BEGIN: 7.6.0-remove-legacy-custom-search-query.sql
-- ============================================================================

-- ChurchCRM 7.6.0 Cleanup — Remove legacy CustomSearch query
-- Remove QueryID=200 (CustomSearch) which is superseded by /people/list custom field filtering.
--
-- Background:
--   QueryID=200 ('CustomSearch') provided a predefined query interface to search people
--   by custom field values. This functionality is now built directly into the /people/list
--   endpoint with native custom field filtering dropdowns, making the legacy query redundant.
--
--   Matching rows were removed from Install.sql, so new installations never carry this query.
--
-- Idempotent: safe to re-run; a second run matches nothing.

DELETE FROM queryparameteroptions_qpo
WHERE qpo_qrp_ID IN (
	SELECT qrp_ID FROM queryparameters_qrp WHERE qrp_qry_ID = 200
);

DELETE FROM queryparameters_qrp
WHERE qrp_qry_ID = 200;

DELETE FROM query_qry
WHERE qry_ID = 200;

-- END: 7.6.0-remove-legacy-custom-search-query.sql
