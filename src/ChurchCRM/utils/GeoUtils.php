<?php

namespace ChurchCRM\Utils;

use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\LocaleInfo;
use Geocoder\Provider\BingMaps\BingMaps;
use Geocoder\Provider\GoogleMaps\GoogleMaps;
use Geocoder\StatefulGeocoder;
use Http\Adapter\Guzzle6\Client;
use Geocoder\Query\GeocodeQuery;
use ChurchCRM\Bootstrapper;

class GeoUtils
{

    public static function getLatLong($address)
    {

        $logger = LoggerUtils::getAppLogger();
        $localeInfo = Bootstrapper::GetCurrentLocale();

        $provider = null;
        $adapter = new Client();

        $lat = 0;
        $long = 0;
        try {
            switch (SystemConfig::getValue("sGeoCoderProvider")) {
                case "GoogleMaps":
                    $provider = new GoogleMaps($adapter, null, SystemConfig::getValue("sGoogleMapsGeocodeKey"));
                    break;
                case "BingMaps":
                    $provider = new BingMaps($adapter, SystemConfig::getValue("sBingMapKey"));
                    break;
            }
            $logger->debug("Using: Geo Provider -  ". $provider->getName());
            $geoCoder = new StatefulGeocoder($provider, $localeInfo->getShortLocale());
            $result = $geoCoder->geocodeQuery(GeocodeQuery::create($address));
            $logger->debug("We have " . $result->count() . " results");
            if (!empty($result)) {
                $firstResult = $result->get(0);
                $coordinates = $firstResult->getCoordinates();
                $lat = $coordinates->getLatitude();
                $long = $coordinates->getLongitude();
            }
        } catch (\Exception $exception) {
            $logger->warn("issue creating geoCoder " . $exception->getMessage());
        }

        return array(
            'Latitude' => $lat,
            'Longitude' => $long
        );

    }

    public static function DrivingDistanceMatrix($address1, $address2)
    {
        $logger = LoggerUtils::getAppLogger();
        $localeInfo = new LocaleInfo(SystemConfig::getValue('sLanguage'));
        $url = "https://maps.googleapis.com/maps/api/distancematrix/json?";
        $url = $url . "language=" . $localeInfo->getShortLocale();
        $url = $url . "&origins=" . urlencode($address1);
        $url = $url . "&destinations=" . urlencode($address2);
        $logger->debug($url);
        $gMapsResponse = file_get_contents($url);
        $details = json_decode($gMapsResponse, TRUE);
        $matrixElements = $details['rows'][0]['elements'][0];
        return array(
            'distance' => $matrixElements['distance']['text'],
            'duration' => $matrixElements['duration']['text']
        );
    }

    // Function takes latitude and longitude
    // of two places as input and returns the
    // distance in miles.
    public static function LatLonDistance($lat1, $lon1, $lat2, $lon2)
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
        if (strtoupper(SystemConfig::getValue('sDistanceUnit')) == 'MILES') {
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

    public static function LatLonBearing($lat1, $lon1, $lat2, $lon2)
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

        // Covert from radians to degrees
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
