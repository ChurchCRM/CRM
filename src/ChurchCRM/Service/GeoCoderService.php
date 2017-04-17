<?php

namespace ChurchCRM\Service;

use ChurchCRM\dto\SystemConfig;
use Geocoder\Exception\NoResult;
use Geocoder\Geocoder;
use Geocoder\Provider\BingMaps;
use Geocoder\Provider\GoogleMaps;
use Ivory\HttpAdapter\CurlHttpAdapter;

class GeoCoderService
{
    /**
     * @var Geocoder
     */
    private $geocoder;

    public function __construct()
    {
        $curl = new CurlHttpAdapter();
        $provider = SystemConfig::getValue("sGeoCoderProvider");
        switch ($provider) {
            case "GoogleMaps":
                $this->geocoder = new GoogleMaps($curl, null, null, false, SystemConfig::getValue("sGoogleMapKey"));
                break;
            case "BingMaps":
                $this->geocoder = new BingMaps($curl);
                break;
        }
    }

    public function getLatLong($address)
    {
        $lat = 0;
        $long = 0;

        try {
            $addressCollection = $this->geocoder->geocode($address);
            $geoAddress = $addressCollection->first();
            if (!empty($geoAddress)) {
                $lat = $geoAddress->getLatitude();
                $long = $geoAddress->getLongitude();
            }
        } catch (NoResult $exception) {
            // no result for the address
        }

        return array(
            'Latitude' => $lat,
            'Longitude' => $long
        );

    }

}
