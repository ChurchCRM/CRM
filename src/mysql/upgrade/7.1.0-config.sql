-- Migrate demo Google Analytics tracking ID to plugin config key.
-- The sGoogleTrackingID legacy key was removed in 7.0.0; this ensures any
-- installs that skipped that migration are cleaned up.
DELETE FROM config_cfg WHERE cfg_name = 'sGoogleTrackingID';
