-- ChurchCRM 7.0.1 — Move Google Maps geocoding API key to Maps plugin
--
-- The Maps & Geocoding plugin now owns the Google Maps API key.
-- The old sGoogleMapsGeocodeKey system setting is migrated to the
-- plugin-scoped key (plugin.maps.googleMapsGeocodeKey) and deleted.
-- The plugin is automatically enabled when a key is present.

-- Migrate any existing API key value to the plugin-scoped config key.
-- INSERT IGNORE skips the row if the target key already exists.
INSERT IGNORE INTO config_cfg (cfg_name, cfg_value)
SELECT 'plugin.maps.googleMapsGeocodeKey', cfg_value
FROM config_cfg
WHERE cfg_name = 'sGoogleMapsGeocodeKey'
  AND cfg_value <> '';

-- Enable the Maps plugin automatically when an API key was configured.
-- The literal '1' avoids the deprecated VALUES() function (removed in MySQL 9.0).
INSERT INTO config_cfg (cfg_name, cfg_value)
SELECT 'plugin.maps.enabled', '1'
FROM config_cfg
WHERE cfg_name = 'plugin.maps.googleMapsGeocodeKey'
  AND cfg_value <> ''
ON DUPLICATE KEY UPDATE cfg_value = '1';

-- Remove the legacy system-level API key setting
DELETE FROM config_cfg WHERE cfg_name = 'sGoogleMapsGeocodeKey';
