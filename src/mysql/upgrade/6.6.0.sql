-- Configuration items removed:
--   cfg_id: 112
--   cfg_name: sFont
--   Description: Font for rendering initials (no longer used in codebase)
--
--   cfg_id: Unknown (previously removed)
--   cfg_name: iRemotePhotoCacheDuration
--   Description: Remote photo cache duration (no longer used in codebase)
--
-- Associated font files removed:
--   src/fonts/Roboto-Regular.ttf
--
-- The configs were removed from the codebase; delete any lingering DB rows.

DELETE FROM config_cfg
WHERE cfg_name = 'sFont'
	OR cfg_id = 112
	OR cfg_name = 'iRemotePhotoCacheDuration';

-- Note: Deleting by both name and id makes this safe whether the cfg_name was renamed
-- or the numeric id was previously used in older dumps.

-- Also remove CSV export charset setting (standardize on UTF-8 for all CSV exports)
--   cfg_id: 108
--   cfg_name: sCSVExportCharset
--   Description: Charset conversion no longer needed; all CSV exports use UTF-8
DELETE FROM config_cfg
WHERE cfg_name = 'sCSVExportCharset'
	OR cfg_id = 108;

-- Also remove CSV export delimiter setting (not used; CsvExporter hardcodes RFC 4180 comma)
--   cfg_id: 107
--   cfg_name: sCSVExportDelimiter
--   Description: Unused setting; RFC 4180 standard requires comma delimiter
DELETE FROM config_cfg
WHERE cfg_name = 'sCSVExportDelimiter'
	OR cfg_id = 107;
