<?php
namespace ChurchCRM\dto;

use ChurchCRM\Utils\GeoUtils;

class ChurchMetaData
{
    public static function getChurchName()
    {
        return SystemConfig::getValue('sChurchName');
    }

    public static function getChurchFullAddress()
    {
        $address = [];
        if (!empty(self::getChurchAddress())) {
            array_push($address, self::getChurchAddress());
        }

        if (!empty(self::getChurchCity())) {
            array_push($address, self::getChurchCity() . ',');
        }

        if (!empty(self::getChurchState())) {
            array_push($address, self::getChurchState());
        }

        if (!empty(self::getChurchZip())) {
            array_push($address, self::getChurchZip());
        }
        if (!empty(self::getChurchCountry())) {
            array_push($address, self::getChurchCountry());
        }

        return implode(' ', $address);
    }

    public static function getChurchAddress()
    {
        return SystemConfig::getValue('sChurchAddress');
    }

    public static function getChurchCity()
    {
        return SystemConfig::getValue('sChurchCity');
    }

    public static function getChurchState()
    {
        return SystemConfig::getValue('sChurchState');
    }

    public static function getChurchZip()
    {
        return SystemConfig::getValue('sChurchZip');
    }

    public static function getChurchCountry()
    {
        return SystemConfig::getValue('sChurchCountry');
    }

    public static function getChurchEmail()
    {
        return SystemConfig::getValue('sChurchEmail');
    }

    public static function getChurchPhone()
    {
        return SystemConfig::getValue('sChurchPhone');
    }

    public static function getChurchLatitude()
    {
        if (empty(SystemConfig::getValue('nChurchLatitude'))) {
            self::updateLatLng();
        }
        return SystemConfig::getValue('nChurchLatitude');
    }

    public static function getChurchLongitude()
    {
        if (empty(SystemConfig::getValue('nChurchLongitude'))) {
            self::updateLatLng();
        }
        return SystemConfig::getValue('nChurchLongitude');
    }

    private static function updateLatLng()
    {
        if (!empty(self::getChurchFullAddress())) {
            $latLng = GeoUtils::getLatLong(self::getChurchFullAddress());
            if (!empty($latLng['Latitude']) && !empty($latLng['Longitude'])) {
                SystemConfig::setValue('nChurchLatitude', $latLng['Latitude']);
                SystemConfig::setValue('nChurchLongitude', $latLng['Longitude']);
            }
        }
    }
}
