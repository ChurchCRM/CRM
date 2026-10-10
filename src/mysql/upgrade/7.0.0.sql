-- ChurchCRM 7.0.0 consolidated upgrade script
-- Merged from individual scripts on 2026-10-10
-- Order preserved from original upgrade.json block


-- ============================================================================
-- BEGIN: 6.8.0.sql
-- ============================================================================

-- Remove deprecated bCSVAdminOnly config setting
-- This setting provided no real security value as users with CSV export permission
-- could already export data through DataTables buttons on any page.
-- Finance permission alone is now sufficient to access CSV exports in financial reports.
DELETE FROM config_cfg WHERE cfg_name = 'bCSVAdminOnly';

-- Remove deprecated bExportCSV user permission setting
-- CSV export is now available to all authenticated users.
-- The permission provided no real security as data visible on screen could always be copied.
DELETE FROM userconfig_ucfg WHERE ucfg_name = 'bExportCSV';

-- END: 6.8.0.sql


-- ============================================================================
-- BEGIN: 7.0.0-remove-bing-maps.sql
-- ============================================================================

-- ChurchCRM 7.0.0 — Remove Bing Maps configuration
-- Bing Maps Basic accounts were retired June 30, 2025.
-- Google Maps is now the only supported geocoding provider.
-- These rows are no longer registered in SystemConfig and serve no purpose.

DELETE FROM config_cfg WHERE cfg_name IN ('sBingMapKey', 'sGeoCoderProvider');

-- END: 7.0.0-remove-bing-maps.sql


-- ============================================================================
-- BEGIN: 7.0.0-map.sql
-- ============================================================================

-- ChurchCRM 7.0.0 — Maps modernisation (Leaflet + OpenStreetMap)
-- Removes config keys that are no longer used now that the congregation map
-- has been migrated from Google Maps (MapUsingGoogle.php) to the new Leaflet-
-- based /v2/map page with circle-marker classification colours.

-- sGMapIcons stored a comma-separated list of Google Maps image-based marker
-- names (e.g. "green-dot,purple,yellow-dot,...") used to differentiate
-- classifications.  The new map uses a built-in colour palette; this key is
-- no longer read anywhere in the codebase.
DELETE FROM config_cfg WHERE cfg_name = 'sGMapIcons';

-- sGoogleMapsRenderKey stored the Google Maps JavaScript API key used to render
-- inline maps in the family profile and family verification pages.  Both pages
-- have been migrated to Leaflet + OpenStreetMap; no API key is required.
DELETE FROM config_cfg WHERE cfg_name = 'sGoogleMapsRenderKey';

-- END: 7.0.0-map.sql
