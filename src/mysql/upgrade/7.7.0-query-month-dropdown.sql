-- ChurchCRM 7.7.0 — Month parameters on Data & Reports queries become a dropdown
--
-- The month filter on Birthdays (qrp 18), Membership anniversaries (qrp 22),
-- Wedding Anniversaries (qrp 300) and Birthdays & Anniversaries (qrp 301) was a
-- free-text box defaulting to "1". qrp_Type 4 renders a select of localized
-- month names that defaults to next month (QueryView.php computes the default
-- in the configured timezone). The posted value is still validated as a
-- number between 1 and 12.
--
-- Idempotent: repeatable UPDATE, matched on id + alias so a customized row is
-- left alone.

UPDATE `queryparameters_qrp`
SET `qrp_Type` = 4,
    `qrp_Validation` = 'n',
    `qrp_NumericMin` = 1,
    `qrp_NumericMax` = 12
WHERE (`qrp_ID` = 18 AND `qrp_Alias` = 'birthmonth')
   OR (`qrp_ID` = 22 AND `qrp_Alias` = 'membermonth')
   OR (`qrp_ID` = 300 AND `qrp_Alias` = 'weddingmonth')
   OR (`qrp_ID` = 301 AND `qrp_Alias` = 'month');
