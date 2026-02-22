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
