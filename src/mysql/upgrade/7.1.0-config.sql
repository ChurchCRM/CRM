-- Remove bHSTSEnable (cfg_id 20142) — HSTS is a web server concern, configure at nginx/Apache level.
DELETE FROM config_cfg WHERE cfg_name = 'bHSTSEnable';

-- Remove iDashboardServiceIntervalTime (cfg_id 2047) — no longer read anywhere in the application.
DELETE FROM config_cfg WHERE cfg_name = 'iDashboardServiceIntervalTime';
