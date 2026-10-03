-- ChurchCRM 7.8.0 — Remove people queries that now live at /people/reports.
-- qry_ID 9, 18, 22, 25, 26, 100, 201, 300, 301.
-- Pledge comparison (28) and missing pledges (30) stay on Query List.
-- Idempotent: a second run deletes nothing.

DELETE FROM queryparameteroptions_qpo
WHERE qpo_qrp_ID IN (
    SELECT qrp_ID FROM queryparameters_qrp
    WHERE qrp_qry_ID IN (9, 18, 22, 25, 26, 100, 201, 300, 301)
);

DELETE FROM queryparameters_qrp
WHERE qrp_qry_ID IN (9, 18, 22, 25, 26, 100, 201, 300, 301);

DELETE FROM query_qry
WHERE qry_ID IN (9, 18, 22, 25, 26, 100, 201, 300, 301);
