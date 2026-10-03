<?php

namespace ChurchCRM\Utils;

use ChurchCRM\Bootstrapper;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\Service\Geocoding\GeocoderChain;

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
     *   "SW 74th Lane", "5 Avenue" -> "5th Avenue"). A leading number is taken
     *   as the house number unless the street-type word ends the line, so
     *   "6047 Avenue F" is left alone.
     * - Trailing unit designators are removed, stacked ones too ("708 SW 16th
     *   Ave Apt 215" -> "708 SW 16th Ave"); geocoders resolve the building, not
     *   the unit.
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

        // Strip trailing unit designators, with or without a separating comma/space.
        // Loop: "Apt 215 Suite 3300" stacks two, and the anchored pattern removes one per pass.
        $unitPattern = '/\s*(?:' . self::UNIT_DESIGNATORS . ')\s*[\w-]+\s*$/iu';
        do {
            $before = $street;
            $street = preg_replace($unitPattern, '', $street) ?? $street;
        } while ($street !== $before);

        $tokens = explode(' ', trim($street));
        $count = count($tokens);
        for ($i = 0; $i < $count - 1; $i++) {
            if (
                preg_match('/^\d+$/', $tokens[$i]) !== 1
                || preg_match('/^(?:' . self::STREET_TYPES . ')\.?$/i', $tokens[$i + 1]) !== 1
            ) {
                continue;
            }
            // The first token is normally the house number ("6047 Avenue F"), so it is only
            // treated as a street number when the street-type word ends the line ("5 Avenue").
            if ($i === 0 && $i + 1 !== $count - 1) {
                continue;
            }
            $tokens[$i] .= self::ordinalSuffix((int) $tokens[$i]);
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
     * Geocode an address to latitude/longitude.
     *
     * The street line is normalised first (see normalizeStreet()), then the
     * providers configured in the `sGeocoderProviders` setting (Nominatim,
     * then the US Census Bureau by default) are tried in order until one
     * answers. No API key is needed for either.
     * Supports both concatenated address strings and structured components.
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
        if (empty(trim($address))) {
            LoggerUtils::getAppLogger()->warning('Geocoding: empty address provided');
            return ['Latitude' => 0.0, 'Longitude' => 0.0];
        }

        return GeocoderChain::fromConfig()->geocode(self::normalizeStreet($address), $city, $state, $zip, $country);
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
