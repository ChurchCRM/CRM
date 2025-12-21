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
