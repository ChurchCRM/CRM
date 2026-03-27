-- Remove bHSTSEnable (cfg_id 20142) — removed from SystemConfig, HSTS should be configured at the web server level.
DELETE FROM config_cfg WHERE cfg_name = 'bHSTSEnable';

-- Remove iDashboardServiceIntervalTime (cfg_id 2047) — removed from SystemConfig, no longer configurable.
DELETE FROM config_cfg WHERE cfg_name = 'iDashboardServiceIntervalTime';

-- Clean up legacy sGoogleTrackingID (already removed in 7.0.0, belt-and-suspenders).
DELETE FROM config_cfg WHERE cfg_name = 'sGoogleTrackingID';
