-- ChurchCRM 7.0.0 — Maps modernisation (Leaflet + OpenStreetMap)
-- Removes config keys that are no longer used now that the congregation map
-- has been migrated from Google Maps (MapUsingGoogle.php) to the new Leaflet-
-- based /v2/map page with circle-marker classification colours.

-- sGMapIcons stored a comma-separated list of Google Maps image-based marker
-- names (e.g. "green-dot,purple,yellow-dot,...") used to differentiate
-- classifications.  The new map uses a built-in colour palette; this key is
-- no longer read anywhere in the codebase.
DELETE FROM config_cfg WHERE cfg_name = 'sGMapIcons';
