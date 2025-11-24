-- Configuration item:
--   cfg_id: 2016
--   cfg_name: bEnableGooglePhotos
-- The config was removed from the codebase; delete any lingering DB row.

DELETE FROM config_cfg
WHERE cfg_name = 'bEnableGooglePhotos'
	OR cfg_id = 2016;

-- Note: Deleting by both name and id makes this safe whether the cfg_name was renamed
-- or the numeric id was previously used in older dumps.
