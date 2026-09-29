-- ChurchCRM 7.6.1 — Add Wedding Anniversary and combined Birthdays & Anniversaries predefined queries
-- Closes #8901: Anniversary (Wedding) query + combined Birthdays & Anniversaries report
--
-- Adds two new predefined query rows (qry_ID 300, 301) and their parameters (qrp_ID 300, 301)
-- to support filtering people by wedding anniversary month and a combined birthday+anniversary view.
--
-- Idempotent: INSERT IGNORE respects the PRIMARY KEY — safe to re-run.
-- UPDATE statements below correct any previously-inserted rows (e.g. from an earlier draft run).

INSERT IGNORE INTO `query_qry` (`qry_ID`, `qry_SQL`, `qry_Name`, `qry_Description`, `qry_Count`) VALUES
  (300,
   'SELECT per_ID as AddToCart, DAYOFMONTH(fam_WeddingDate) as Day, DATE_FORMAT(fam_WeddingDate, \'%Y-%m-%d\') as Date, CONCAT(per_FirstName,\' \',per_LastName) AS Name FROM person_per JOIN family_fam ON per_fam_ID=fam_ID WHERE per_fmr_ID IN (1,2) AND fam_WeddingDate IS NOT NULL AND MONTH(fam_WeddingDate)=~weddingmonth~ ORDER BY DAYOFMONTH(fam_WeddingDate), per_LastName',
   'Wedding Anniversaries',
   'People with wedding anniversaries in a particular month',
   0),
  (301,
   'SELECT per_ID as AddToCart, \'Birthday\' as Type, per_BirthDay as Day, CONCAT(per_FirstName,\' \',per_LastName) AS Name FROM person_per WHERE per_BirthMonth=~month~ AND per_BirthDay>0 UNION ALL SELECT per_ID as AddToCart, \'Anniversary\' as Type, DAYOFMONTH(fam_WeddingDate) as Day, CONCAT(per_FirstName,\' \',per_LastName) AS Name FROM person_per JOIN family_fam ON per_fam_ID=fam_ID WHERE per_fmr_ID IN (1,2) AND fam_WeddingDate IS NOT NULL AND MONTH(fam_WeddingDate)=~month~ ORDER BY Day',
   'Birthdays & Anniversaries',
   'People with birthdays or wedding anniversaries in a particular month',
   0);

INSERT IGNORE INTO `queryparameters_qrp`
  (`qrp_ID`, `qrp_qry_ID`, `qrp_Type`, `qrp_OptionSQL`, `qrp_Name`, `qrp_Description`,
   `qrp_Alias`, `qrp_Default`, `qrp_Required`, `qrp_InputBoxSize`, `qrp_Validation`,
   `qrp_NumericMax`, `qrp_NumericMin`, `qrp_AlphaMinLength`, `qrp_AlphaMaxLength`) VALUES
  (300, 300, 0, '', 'Month', 'The wedding anniversary month for which you would like records returned.',
   'weddingmonth', '1', 1, 0, 'n', 12, 1, 1, 2),
  (301, 301, 0, '', 'Month', 'The month for which you would like birthdays and anniversaries returned.',
   'month', '1', 1, 0, 'n', 12, 1, 1, 2);

-- Correct qrp_Validation on params 300/301 if an earlier draft run left them as '' (empty).
UPDATE `queryparameters_qrp`
SET `qrp_Validation` = 'n'
WHERE `qrp_ID` IN (300, 301)
  AND `qrp_Validation` = '';
