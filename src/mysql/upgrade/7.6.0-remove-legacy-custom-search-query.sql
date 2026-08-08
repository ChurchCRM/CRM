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
