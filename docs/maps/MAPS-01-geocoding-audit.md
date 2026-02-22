# MAPS-01: Geocoding Call Path Audit

**Audience:** Developer  
**Last Updated:** 2026-02-22 (revised: Bing Maps removal, drivingDistanceMatrix detail)
**Related:** [Issue #MAPS-01](https://github.com/ChurchCRM/CRM/issues), [GeoUtils](../../src/ChurchCRM/utils/GeoUtils.php)

## Overview

This document audits every place in the ChurchCRM codebase where geocoding is
triggered today. It covers the core utility, all callers, the full request-to-DB
write flow, and known edge cases.  No code changes are part of this ticket.

---

## Table of Contents

- [Core Utility: GeoUtils](#core-utility-geoutils)
- [Entry Points](#entry-points)
- [Full Request Flow](#full-request-flow)
- [DB Write Locations](#db-write-locations)
- [Configuration](#configuration)
- [Edge Cases](#edge-cases)
- [Summary Table](#summary-table)

---

## Core Utility: GeoUtils

**File:** `src/ChurchCRM/utils/GeoUtils.php`

### `GeoUtils::getLatLong(string $address): array`

The single geocoding entry function used throughout the application.

- Reads `SystemConfig::getValue('sGeoCoderProvider')` to choose the provider.
- Supported provider: `GoogleMaps` only. Bing Maps support was removed in v7.0.0 (`geocoder-php/bing-maps-provider` removed from composer, `sBingMapKey` config deleted).
- Uses the `geocoder-php` library (`StatefulGeocoder`) with a `Guzzle7` HTTP adapter.
- Locale is set from `Bootstrapper::getCurrentLocale()->getShortLocale()`.
- Returns `['Latitude' => float, 'Longitude' => float]`.
- Returns `['Latitude' => 0, 'Longitude' => 0]` on any error (logged at WARNING level).

### Other Methods in GeoUtils

| Method | API Call? | Description |
|--------|-----------|-------------|
| `drivingDistanceMatrix($addr1, $addr2)` | **Yes** — Google Distance Matrix API | Constructs a URL with both addresses and calls it via `file_get_contents()`. **Not** geocoder-php. Uses its own quota and key (currently reads `sGoogleMapsGeocodeKey` — see note below). |
| `latLonDistance($lat1, $lon1, $lat2, $lon2)` | No | Pure math (Spherical Law of Cosines). Reads distance unit from Maps plugin settings with fallback to SystemConfig. |
| `latLonBearing($lat1, $lon1, $lat2, $lon2)` | No | Pure math (bearing calculation). No API call. |

> **`drivingDistanceMatrix` note:** This method calls the [Google Distance Matrix API](https://developers.google.com/maps/documentation/distance-matrix/overview) — a **separate product** from the Geocoding API with its own billing, quota, and (ideally) its own API key. Currently it reuses the geocode key, which is a configuration gap. It is called directly via `file_get_contents()`, bypassing geocoder-php entirely, and it is not rate-limited or cached. See also: Entry Point 5 below, where this causes two outbound API calls per single family geolocation request.

---

## Entry Points

### 1. Bulk Update Tool — `UpdateAllLatLon.php`

**Trigger:** Manual — admin navigates to `/UpdateAllLatLon.php`

**Flow:**
1. Queries up to 250 `Family` records where `Latitude IS NULL OR Latitude = 0 OR Longitude IS NULL OR Longitude = 0`.
2. For each family, calls `Family::updateLanLng()` (note: method name has a typo in the codebase — `Lan` instead of `Lat`).
3. `updateLanLng()` calls `GeoUtils::getLatLong($this->getAddress())`.
4. On success, sets `$this->setLatitude()` / `$this->setLongitude()` and calls `$this->save()` → writes to `family_fam` table.

**Note:** Limited to 250 families per request. Repeat visits are required to geocode a large dataset.

---

### 2. Map Rendering — `MapUsingGoogle.php`

**Trigger:** Automatic (lazy) — any user views a map page (`/MapUsingGoogle.php?GroupID=...`)

**Family path (all families or group with family plot):**
- Reads `Family.Latitude` and `Family.Longitude` directly from DB.
- **No geocoding call is made** if coordinates already exist.
- Missing-coordinate families are silently skipped on the map.

**Person path (cart or group with person plot):**
1. For each `Person`, calls `Person::getLatLng()`.
2. `getLatLng()` first checks `Family::hasLatitudeAndLongitude()`.
3. If the family already has coordinates → returns them immediately (no API call).
4. If not → calls `GeoUtils::getLatLong($person->getAddress())`.
5. On success, additionally calls `$this->getFamily()->updateLanLng()` to cache the result to the family record in DB.

**Church marker:**
- Calls `ChurchMetaData::getChurchLatitude()` and `ChurchMetaData::getChurchLongitude()`.
- These lazily geocode the church address on first call (see next entry point).

---

### 3. Church Coordinates — `ChurchMetaData::getChurchLatitude()` / `getChurchLongitude()`

**File:** `src/ChurchCRM/dto/ChurchMetaData.php`

**Trigger:** Automatic (lazy) — any page that calls these methods (e.g., `MapUsingGoogle.php`)

**Flow:**
1. `getChurchLatitude()` checks `SystemConfig::getValue('iChurchLatitude')`.
2. If empty, calls private `updateLatLng()`.
3. `updateLatLng()` calls `GeoUtils::getLatLong(self::getChurchFullAddress())`.
4. On success, persists both values via `SystemConfig::setValue('iChurchLatitude', ...)` and `SystemConfig::setValue('iChurchLongitude', ...)`.

**Note:** Geocodes the church address only once; result is stored in `SystemConfig` and reused on subsequent calls.

---

### 4. REST API — `POST /api/geocoder/address`

**File:** `src/api/routes/geocoder.php`

**Trigger:** Manual — JavaScript front-end POSTs `{"address": "..."}` to this endpoint.

**Flow:**
1. Parses JSON body; throws `HttpBadRequestException` on empty body.
2. Calls `GeoUtils::getLatLong($input->address)`.
3. Returns the lat/lng JSON response to the caller.
4. **Does not save to DB** — the caller is responsible for any persistence.

**Registered in:** `src/api/index.php` via `require __DIR__ . '/routes/geocoder.php'`.

---

### 5. REST API — `GET /api/family/{familyId}/geolocation`

**File:** `src/api/routes/people/people-family.php`

**Trigger:** Manual — front-end requests geolocation for a specific family.

**Flow:**
1. Resolves the family via `FamilyMiddleware`.
2. Calls `GeoUtils::getLatLong($family->getAddress())` → **Google Geocoding API** call.
3. Additionally calls `GeoUtils::drivingDistanceMatrix($familyAddress, ChurchMetaData::getChurchAddress())` → **Google Distance Matrix API** call (separate product, separate quota).
4. Merges and returns both results as JSON.
5. **Does not save to DB.**

> **Two API calls per request.** This endpoint makes one Geocoding API call and one Distance Matrix API call every time it is invoked, with no caching. If the family's coordinates are already stored in the DB, the geocoding call is still made (no short-circuit). Both calls use `file_get_contents()` or geocoder-php directly with no rate limiting.

---

### 6. Geographic Utilities View — `GeoPage.php`

**Trigger:** Manual — admin navigates to the Geographic Utilities page.

**Geocoding involvement:** None.  
This page uses only `GeoUtils::latLonDistance()` and `GeoUtils::latLonBearing()` — both pure-math
functions that read pre-stored DB coordinates. No API calls are made.

---

## Full Request Flow

```
User/Admin Action
      │
      ▼
Entry Point (view/API/lazy call)
      │
      ▼
GeoUtils::getLatLong(address)
      │
      ├─ Reads sGeoCoderProvider from SystemConfig
      │
      ▼
geocoder-php StatefulGeocoder
      │
      └─ GoogleMaps provider  ──►  Google Geocoding API
           (uses sGoogleMapsGeocodeKey)
           [Only provider — Bing Maps removed in v7.0.0]
      │
      ▼
Parse first result → Coordinates (lat, lng)
      │
      ▼
Return ['Latitude' => lat, 'Longitude' => lng]
  (zeros + WARNING log on any error)
      │
      ▼
Caller decides whether to persist:
  ├─ Family::updateLanLng()     → saves to family_fam (Latitude, Longitude)
  ├─ ChurchMetaData::updateLatLng() → saves to system_config (iChurchLatitude, iChurchLongitude)
  └─ API endpoints              → does NOT save (returns to client only)
```

---

## DB Write Locations

| Record Type | Table | Columns | Written By |
|-------------|-------|---------|------------|
| Family | `family_fam` | `fam_Latitude`, `fam_Longitude` | `Family::updateLanLng()` |
| Church | `system_config` | `iChurchLatitude`, `iChurchLongitude` | `ChurchMetaData::updateLatLng()` |

**Note:** `Person` records do not store geocoded coordinates directly. `Person::getLatLng()` reads
from the parent `Family` and can trigger `Family::updateLanLng()` as a side-effect.

---

## Configuration

All geocoding-related settings are managed in `src/ChurchCRM/dto/SystemConfig.php` under
the **Map Settings** group:

| Config Key | Type | Default | Description | Status |
|------------|------|---------|-------------|--------|
| `sGeoCoderProvider` | choice | `GoogleMaps` | Active geocoding backend | Active — only value is `GoogleMaps` (Bing removed v7.0.0) |
| `sGoogleMapsGeocodeKey` | text | _(empty)_ | Google Maps API key for server-side geocoding via geocoder-php | Active |
| `plugin.maps.googleMapsGeocodeKey` | text | _(empty)_ | Same key stored in Maps plugin settings; takes precedence over `sGoogleMapsGeocodeKey` when set | Active |
| `plugin.maps.googleMapsRenderKey` | text | _(empty)_ | Google Maps JS API key for browser map rendering | **To be removed** in MAPS-08 (Leaflet replaces Google Maps JS widget) |
| `sBingMapKey` | text | _(empty)_ | Bing Maps API key | **Removed** in v7.0.0 |
| `sGoogleMapsRenderKey` | text | _(empty)_ | Legacy render key (system config level) | **Removed** in v7.0.0 |

> **Key precedence for geocoding:** `GeoUtils` now reads `plugin.maps.googleMapsGeocodeKey` first (via `PluginManager::getPlugin('maps')->getGoogleMapsGeocodeKey()`), falling back to `sGoogleMapsGeocodeKey` from SystemConfig. Both point to the same Google Geocoding API product.

---

## Edge Cases

### Bulk Update Limit

`UpdateAllLatLon.php` limits processing to 250 families per page load. Churches with
large membership must reload the page multiple times to geocode all records.

### Lazy Church Geocoding

`ChurchMetaData::getChurchLatitude()` and `getChurchLongitude()` geocode the church
address on the first call where the SystemConfig values are empty. This can cause an
unexpected outbound API call the first time any map is rendered after a settings change.

### Person Geocoding Side-Effect

When `MapUsingGoogle.php` plots individual persons (cart or group maps), it calls
`Person::getLatLng()`. If the person's family has no stored coordinates, this triggers a
geocoding API call **and** writes the result back to the family record. Map rendering
therefore has a hidden DB write side-effect for un-geocoded families.

### CSV Import — No Geocoding

`CSVImport.php` does **not** trigger geocoding. Families imported via CSV will have
`Latitude = 0` and `Longitude = 0` until `UpdateAllLatLon.php` is run or the map is
rendered with the person-plot mode.

### Kiosk — No Geocoding

The kiosk subsystem (`src/kiosk/`) does not reference `GeoUtils` or trigger any geocoding.

### Family Editor — No Geocoding on Save

`FamilyEditor.php` does not call `updateLanLng()` or `GeoUtils::getLatLong()` directly.
A newly created or edited family will not have coordinates until the bulk update tool is
run or the family is plotted on a map.

### Distance Matrix — Separate API, Shared Key, No Caching

`GeoUtils::drivingDistanceMatrix()` calls the Google Distance Matrix API, which is a **distinct
billing product** from the Geocoding API. It currently reuses the geocode API key. The call is
made via `file_get_contents()` with no rate limiting, no caching, and no error surfacing. The
`GET /api/family/{id}/geolocation` endpoint therefore triggers two separate outbound API calls
per request regardless of whether the family's coordinates are already cached in the DB.

This is out of scope for the current MAPS modernization tickets but should be tracked as a
follow-on: either add caching for distance results or remove the Distance Matrix call from the
geolocation endpoint and replace with the pure-math `latLonDistance()` (which requires only
stored coordinates, no API).

### Error Handling

`GeoUtils::getLatLong()` wraps the entire geocoding call in a `try/catch`. Any provider
error (missing API key, rate limit, network failure) logs a WARNING and silently returns
`['Latitude' => 0, 'Longitude' => 0]`. Callers do not currently surface this failure to
the user.

---

## Summary Table

| Entry Point | File | Trigger | Calls `getLatLong`? | Calls Distance Matrix? | Saves to DB? |
|-------------|------|---------|---------------------|------------------------|--------------|
| Bulk update | `UpdateAllLatLon.php` | Manual (admin) | Yes — for each un-geocoded family | No | Yes → `family_fam` |
| Map rendering (persons) | `MapUsingGoogle.php` → `Person::getLatLng()` | Auto (lazy on page load) | Yes — if family has no coords | No | Yes → `family_fam` (side-effect) |
| Church lat/lng lookup | `ChurchMetaData::getChurchLatitude/Longitude()` | Auto (lazy, first call) | Yes — if SystemConfig empty | No | Yes → `system_config` |
| Geocoder API | `POST /api/geocoder/address` | Manual (JS call) | Yes | No | No |
| Family geolocation API | `GET /api/family/{id}/geolocation` | Manual (JS call) | Yes (always, no short-circuit) | **Yes** (always) | No |
| Geo utilities view | `GeoPage.php` | Manual (admin) | No (math only) | No | No |
| CSV import | `CSVImport.php` | Manual (admin) | No | No | No |
| Kiosk | `src/kiosk/` | N/A | No | No | No |
| Family editor save | `FamilyEditor.php` | Manual (user/admin) | No | No | No |
