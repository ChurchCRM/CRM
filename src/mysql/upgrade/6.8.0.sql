-- Remove deprecated bCSVAdminOnly config setting
-- This setting provided no real security value as users with CSV export permission
-- could already export data through DataTables buttons on any page.
-- Finance permission alone is now sufficient to access CSV exports in financial reports.
DELETE FROM config_cfg WHERE cfg_name = 'bCSVAdminOnly';

-- Remove deprecated bExportCSV user permission setting
-- CSV export is now available to all authenticated users.
-- The permission provided no real security as data visible on screen could always be copied.
DELETE FROM userconfig_ucfg WHERE ucfg_name = 'bExportCSV';
