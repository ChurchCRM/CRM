-- Configuration item:
--   cfg_id: 2054
--   cfg_name: bEnabledCalendar
-- The config was removed from the codebase; delete any lingering DB row.

DELETE FROM config_cfg
WHERE cfg_name = 'bEnabledCalendar'
	OR cfg_id = 2054;

-- Note: Deleting by both name and id makes this safe whether the cfg_name was renamed
-- or the numeric id was previously used in older dumps.
