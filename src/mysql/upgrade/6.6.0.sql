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

-- Also remove the configuration item for dashboard event timeout (no longer used)
--   cfg_id: 2043
--   cfg_name: iEventsOnDashboardPresenceTimeOut
DELETE FROM config_cfg
WHERE cfg_name = 'iEventsOnDashboardPresenceTimeOut'
	OR cfg_id = 2043;

-- Also remove the feature flag for showing events on dashboard (no longer used)
--   cfg_id: 2042
--   cfg_name: bEventsOnDashboardPresence
DELETE FROM config_cfg
WHERE cfg_name = 'bEventsOnDashboardPresence'
	OR cfg_id = 2042;

-- Also remove social link config items no longer used
--   cfg_id: 2014
--   cfg_name: sChurchFB
--   cfg_id: 2015
--   cfg_name: sChurchTwitter
DELETE FROM config_cfg
WHERE cfg_name = 'sChurchFB'
	OR cfg_id = 2014
	OR cfg_name = 'sChurchTwitter'
	OR cfg_id = 2015;

-- Also remove home area code setting (no longer used; phones displayed as-is)
--   cfg_id: 1010
--   cfg_name: sHomeAreaCode
DELETE FROM config_cfg
WHERE cfg_name = 'sHomeAreaCode'
	OR cfg_id = 1010;

-- Also remove unused short date format setting (not actively used in codebase)
--   cfg_id: 104
--   cfg_name: sDateFormatShort
DELETE FROM config_cfg
WHERE cfg_name = 'sDateFormatShort'
	OR cfg_id = 104;
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
