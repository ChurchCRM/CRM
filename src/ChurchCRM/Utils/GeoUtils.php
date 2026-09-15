<?php

namespace ChurchCRM\Utils;

use ChurchCRM\Bootstrapper;
use ChurchCRM\dto\SystemConfig;

class GeoUtils
{
    /**
     * Street-type words that may follow a bare street number ("5 Avenue" -> "5th Avenue").
     * English only; addresses in other languages are left untouched.
     */
    private const STREET_TYPES = 'st|str|street|ave|avenue|pl|place|ter|terr|terrace|rd|road|ln|lane|way|ct|court|'
        . 'dr|drive|blvd|boulevard|cir|circle|pkwy|parkway|trl|trail|hwy|highway|loop|run|path|walk|row|sq|square';

    /**
     * Trailing unit designators that geocoders cannot use ("Apt 215", "#231", "Unit B", "Suite 4").
     */
    private const UNIT_DESIGNATORS = '#|apt\.?|apartment|unit|suite|ste\.?|lot|bldg\.?|building|fl\.?|floor|rm\.?|room';

    /**
     * Normalise a street line before it is sent to a geocoder.
     *
     * - A bare number directly followed by an English street-type word gets its
     *   ordinal suffix ("NW 10 Street" -> "NW 10th Street", "SW 74 Lane" ->
     *   "SW 74th Lane"). The first token is never touched, so a house number
     *   in front of a lettered street ("6047 Avenue F") is left alone.
     * - A trailing unit designator is removed ("708 SW 16th Ave Apt 215" ->
     *   "708 SW 16th Ave"); geocoders resolve the building, not the unit.
     * - Whitespace is collapsed.
     *
     * Pure function, no I/O. Only the part before the first comma is
     * normalised so a full "street, city, state zip" string is safe to pass;
     * a comma-separated tail that is nothing but a unit (", Apt 212", ", A-7",
     * ", #218B") is dropped, a tail with a city name is kept.
     */
    public static function normalizeStreet(string $street): string
    {
        $street = trim(preg_replace('/\s+/', ' ', $street) ?? $street);
        if ($street === '') {
            return '';
        }

        $rest = '';
        $commaPos = strpos($street, ',');
        if ($commaPos !== false) {
            $rest = substr($street, $commaPos);
            $street = substr($street, 0, $commaPos);
            // ", Apt 212" / ", A-7" / ", #218B": a tail that is only a unit. A
            // city name never contains a digit, so it is never mistaken for one.
            if (preg_match('/^,\s*(?:(?:' . self::UNIT_DESIGNATORS . ')\s*[\w-]+|[\w-]*\d[\w-]*)\s*$/iu', $rest) === 1) {
                $rest = '';
            }
        }

        // Strip a trailing unit designator, with or without a separating comma/space.
        $street = preg_replace(
            '/\s*(?:' . self::UNIT_DESIGNATORS . ')\s*[\w-]+\s*$/iu',
            '',
            $street
        ) ?? $street;

        $tokens = explode(' ', trim($street));
        $count = count($tokens);
        for ($i = 1; $i < $count - 1; $i++) {
            if (
                preg_match('/^\d+$/', $tokens[$i]) === 1
                && preg_match('/^(?:' . self::STREET_TYPES . ')\.?$/i', $tokens[$i + 1]) === 1
            ) {
                $tokens[$i] .= self::ordinalSuffix((int) $tokens[$i]);
            }
        }

        return trim(implode(' ', $tokens)) . $rest;
    }

    private static function ordinalSuffix(int $n): string
    {
        $mod100 = $n % 100;
        if ($mod100 >= 11 && $mod100 <= 13) {
            return 'th';
        }
        return match ($n % 10) {
            1 => 'st',
            2 => 'nd',
            3 => 'rd',
            default => 'th',
        };
    }

    /**
     * Geocode an address to latitude/longitude using OpenStreetMap's Nominatim service.
     *
     * Nominatim is free and requires no API key. No admin configuration needed.
     * Supports both concatenated address strings and structured components.
     * The street line is normalised first (see normalizeStreet()); when a
     * structured query finds nothing, one free-form retry is made with the
     * normalised address before giving up.
     *
     * @param string $address The address to geocode (can be full address or just street)
     * @param string|null $city City name (improves accuracy when provided)
     * @param string|null $state State/province (improves accuracy when provided)
     * @param string|null $zip Postal code (improves accuracy when provided)
     * @param string|null $country Country name (improves accuracy when provided)
     * @return array{Latitude: float, Longitude: float} Latitude and longitude, or [0, 0] if not found
     */
    public static function getLatLong(
        string $address,
        ?string $city = null,
        ?string $state = null,
        ?string $zip = null,
        ?string $country = null
    ): array {
        $logger = LoggerUtils::getAppLogger();
        $localeInfo = Bootstrapper::getCurrentLocale();

        $notFound = ['Latitude' => 0, 'Longitude' => 0];

        if (empty(trim($address))) {
            $logger->warning('Geocoding: empty address provided');
            return $notFound;
        }

        try {
            $logger->debug('Using: Geo Provider - Nominatim (OpenStreetMap)');

            $street = self::normalizeStreet($address);
            $baseParams = [
                'format' => 'json',
                'limit' => 1,
                'accept-language' => $localeInfo->getShortLocale(),
            ];

            $freeFormParts = [$street];
            foreach ([$city, $state, $zip] as $part) {
                if (!empty($part)) {
                    $freeFormParts[] = trim($part);
                }
            }
            // Don't add country to the free-form query - it often causes matching to fail
            $freeForm = $baseParams + ['q' => implode(', ', $freeFormParts)];

            // Use a structured query first when components are provided (better accuracy)
            if (!empty($city) || !empty($state) || !empty($zip)) {
                $structured = $baseParams + ['street' => $street];
                if (!empty($city)) {
                    $structured['city'] = trim($city);
                }
                if (!empty($state)) {
                    $structured['state'] = trim($state);
                }
                if (!empty($zip)) {
                    $structured['postalcode'] = trim($zip);
                }
                // Only add country if it's actually provided (not empty/null)
                if (!empty($country)) {
                    $structured['country'] = trim($country);
                }

                $result = self::queryNominatim($structured);
                if ($result !== null) {
                    $logger->debug('Geocoding successful: lat=' . $result['Latitude'] . ', lng=' . $result['Longitude']);
                    return $result;
                }

                // Structured queries need OSM's exact street spelling; retry once
                // free-form, after the 1 req/sec pause Nominatim's policy requires.
                $logger->debug('Geocoding: structured query found nothing, retrying free-form');
                sleep(1);
            }

            $result = self::queryNominatim($freeForm, true);
            if ($result === null) {
                $logger->warning('Geocoding: No results found for address (see service log for familyId)');
                return $notFound;
            }

            $logger->debug('Geocoding successful: lat=' . $result['Latitude'] . ', lng=' . $result['Longitude']);
            return $result;
        } catch (\Throwable $exception) {
            $logger->warning('Geocoding error: ' . $exception->getMessage());
        }

        return $notFound;
    }

    /**
     * Nominatim "addresstype" values that mean the match is only a locality
     * (the street was not found and Nominatim fell back to the city, county,
     * postcode...). A free-form query happily returns these, and a pin on the
     * wrong town is worse than no pin, so they are rejected.
     */
    private const LOCALITY_ADDRESS_TYPES = [
        'city', 'town', 'village', 'hamlet', 'suburb', 'neighbourhood', 'quarter', 'borough',
        'municipality', 'county', 'state', 'region', 'province', 'country', 'postcode', 'administrative',
    ];

    /**
     * One Nominatim search request.
     *
     * @param array<string, mixed> $params query parameters (format, limit, q or structured fields)
     * @param bool $requireStreetLevel reject a result that is only a locality (see LOCALITY_ADDRESS_TYPES)
     * @return array{Latitude: float, Longitude: float}|null null when the request failed or returned nothing usable
     */
    private static function queryNominatim(array $params, bool $requireStreetLevel = false): ?array
    {
        $logger = LoggerUtils::getAppLogger();
        $url = 'https://nominatim.openstreetmap.org/search?' . http_build_query($params);

        // Nominatim ToS requires a User-Agent header; add timeout to avoid hanging PHP workers
        $context = stream_context_create([
            'http' => [
                'method'  => 'GET',
                'header'  => "User-Agent: ChurchCRM/7.0 (+https://churchcrm.io)\r\n",
                'timeout' => 10,
            ],
        ]);

        $response = file_get_contents($url, false, $context);
        if ($response === false) {
            $logger->warning('Geocoding failed: Nominatim API request failed');
            return null;
        }

        $results = json_decode($response, true, 512);
        if (empty($results) || !\is_array($results) || !isset($results[0]['lat'], $results[0]['lon'])) {
            return null;
        }

        $addressType = strtolower((string) ($results[0]['addresstype'] ?? $results[0]['type'] ?? ''));
        if ($requireStreetLevel && \in_array($addressType, self::LOCALITY_ADDRESS_TYPES, true)) {
            $logger->debug('Geocoding: free-form result is only a locality (' . $addressType . '), ignoring');
            return null;
        }

        return [
            'Latitude'  => (float) $results[0]['lat'],
            'Longitude' => (float) $results[0]['lon'],
        ];
    }

    /**
     * Returns a Google Maps directions deep-link URL.
     *
     * Prefer lat/lng when available — avoids client-side geocoding and gives a
     * precise pin. Falls back to an address string otherwise.
     * Returns an empty string when no destination can be determined.
     */
    public static function buildDirectionsUrl(string $address = '', float $lat = 0.0, float $lng = 0.0): string
    {
        $base = 'https://www.google.com/maps/dir/?api=1&destination=';
        if ($lat !== 0.0 && $lng !== 0.0) {
            return $base . $lat . ',' . $lng;
        }
        if (!empty($address)) {
            return $base . urlencode($address);
        }
        return '';
    }

    /**
     * Returns an Apple Maps directions deep-link URL.
     *
     * Uses the `maps.apple.com` scheme which opens natively in the Maps app
     * on iOS/macOS and falls back to an Apple Maps web view on other
     * platforms. Prefers lat/lng when available for an accurate pin; falls
     * back to the address string. Returns an empty string when no
     * destination can be determined.
     *
     * @see https://developer.apple.com/library/archive/featuredarticles/iPhoneURLScheme_Reference/MapLinks/MapLinks.html
     */
    public static function buildAppleMapsDirectionsUrl(string $address = '', float $lat = 0.0, float $lng = 0.0): string
    {
        $base = 'https://maps.apple.com/?daddr=';
        if ($lat !== 0.0 && $lng !== 0.0) {
            return $base . $lat . ',' . $lng;
        }
        if (!empty($address)) {
            return $base . urlencode($address);
        }
        return '';
    }

    public static function drivingDistanceMatrix($address1, $address2): array
    {
        $logger = LoggerUtils::getAppLogger();
        $localeInfo = Bootstrapper::getCurrentLocale();
        $url = 'https://maps.googleapis.com/maps/api/distancematrix/json?';
        $url = $url . 'language=' . $localeInfo->getShortLocale();
        $url = $url . '&origins=' . urlencode($address1);
        $url = $url . '&destinations=' . urlencode($address2);
        $logger->debug($url);
        $gMapsResponse = file_get_contents($url);
        $details = json_decode($gMapsResponse, true, 512);
        $matrixElements = $details['rows'][0]['elements'][0];

        return [
            'distance' => $matrixElements['distance']['text'],
            'duration' => $matrixElements['duration']['text'],
        ];
    }

    // Function takes latitude and longitude
    // of two places as input and returns the
    // distance in miles.
    public static function latLonDistance($lat1, $lon1, $lat2, $lon2): string
    {
        // Formula for calculating radians between
        // latitude and longitude pairs.

        // Uses the Spherical Law of Cosines to find great circle distance.
        // Length of arc on surface of sphere

        // convert to radians to work with trig functions

        $lat1 = deg2rad($lat1);
        $lon1 = deg2rad($lon1);
        $lat2 = deg2rad($lat2);
        $lon2 = deg2rad($lon2);

        // determine angle between between points in radians
        $radians = acos(sin($lat1) * sin($lat2) + cos($lat1) * cos($lat2) * cos($lon1 - $lon2));

        // mean radius of Earth in kilometers
        $radius = 6371.0;

        // distance in kilometers is $radians times $radius
        $distance = $radians * $radius;

        // convert to miles
        if (strtoupper(SystemConfig::getValue('sDistanceUnit')) === 'MILES') {
            $distance = 0.6213712 * $distance;
        }

        // Return distance to three figures
        if ($distance < 10.0) {
            $distance_f = sprintf('%0.2f', $distance);
        } elseif ($distance < 100.0) {
            $distance_f = sprintf('%0.1f', $distance);
        } else {
            $distance_f = sprintf('%0.0f', $distance);
        }

        return $distance_f;
    }

    public static function latLonBearing($lat1, $lon1, $lat2, $lon2): string
    {
        // Formula for determining the bearing from ($lat1,$lon1) to ($lat2,$lon2)

        // This is the initial bearing which if followed in a straight line will take
        // you from the start point to the end point; in general, the bearing you are
        // following will have varied by the time you get to the end point (if you were
        // to go from say 35°N,45°E (Baghdad) to 35°N,135°E (Osaka), you would start on
        // a bearing of 60° and end up on a bearing of 120°!).

        // If you are standing at ($lat1,$lon1) and pointing the shortest distance to
        // ($lat2,$lon2) this function tells you which direction you are pointing.
        // Returns one of the following 16 directions.
        // N, NNE, NE, ENE, E, ESE, SE, SSE, S, SSW, SW, WSW, W, WNW, NW, NNW

        // convert to radians to work with trig functions
        $lat1 = deg2rad($lat1);
        $lon1 = deg2rad($lon1);
        $lat2 = deg2rad($lat2);
        $lon2 = deg2rad($lon2);

        $y = sin($lon2 - $lon1) * cos($lat2);
        $x = cos($lat1) * sin($lat2) - sin($lat1) * cos($lat2) * cos($lon2 - $lon1);
        $bearing = atan2($y, $x);

        // Convert from radians to degrees
        $bearing = sprintf('%5.1f', rad2deg($bearing));

        // Convert to directions
        // -180=S   -135=SW   -90=W   -45=NW   0=N   45=NE   90=E   135=SE   180=S
        if ($bearing < -191.25) {
            $direction = '---';
        } elseif ($bearing < -168.75) {
            $direction = gettext('S');
        } elseif ($bearing < -146.25) {
            $direction = gettext('SSW');
        } elseif ($bearing < -123.75) {
            $direction = gettext('SW');
        } elseif ($bearing < -101.25) {
            $direction = gettext('WSW');
        } elseif ($bearing < -78.75) {
            $direction = gettext('W');
        } elseif ($bearing < -56.25) {
            $direction = gettext('WNW');
        } elseif ($bearing < -33.75) {
            $direction = gettext('NW');
        } elseif ($bearing < -11.25) {
            $direction = gettext('NNW');
        } elseif ($bearing < 11.25) {
            $direction = gettext('N');
        } elseif ($bearing < 33.75) {
            $direction = gettext('NNE');
        } elseif ($bearing < 56.25) {
            $direction = gettext('NE');
        } elseif ($bearing < 78.75) {
            $direction = gettext('ENE');
        } elseif ($bearing < 101.25) {
            $direction = gettext('E');
        } elseif ($bearing < 123.75) {
            $direction = gettext('ESE');
        } elseif ($bearing < 146.25) {
            $direction = gettext('SE');
        } elseif ($bearing < 168.75) {
            $direction = gettext('SSE');
        } elseif ($bearing < 191.25) {
            $direction = gettext('S');
        } else {
            $direction = '+++';
        }

//    $direction  = $bearing . " " . $direction;

        return $direction;
    }
}
