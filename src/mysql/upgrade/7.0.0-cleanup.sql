-- ChurchCRM 7.0.0 Database Cleanup
-- Remove unused configuration settings

-- Remove CSV export delimiter setting (not used; CsvExporter hardcodes RFC 4180 comma)
--   cfg_id: 107
--   cfg_name: sCSVExportDelimiter
--   Description: Unused setting; RFC 4180 standard requires comma delimiter.
--                The configuration was previously intended to allow semicolon for European locales,
--                but CsvExporter.php hardcodes comma and doesn't respect this setting.
--                All CSV exports now standardize on RFC 4180 comma delimiter.
DELETE FROM config_cfg
WHERE cfg_name = 'sCSVExportDelimiter'
	OR cfg_id = 107;
