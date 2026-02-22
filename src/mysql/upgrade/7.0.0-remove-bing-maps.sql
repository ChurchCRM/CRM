-- ChurchCRM 7.0.0 — Remove Bing Maps configuration
-- Bing Maps Basic accounts were retired June 30, 2025.
-- Google Maps is now the only supported geocoding provider.
-- These rows are no longer registered in SystemConfig and serve no purpose.

DELETE FROM config_cfg WHERE cfg_name IN ('sBingMapKey', 'sGeoCoderProvider');
