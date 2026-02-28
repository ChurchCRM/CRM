<?php

namespace ChurchCRM\dto;

use ChurchCRM\Plugin\PluginManager;
use ChurchCRM\Utils\GeoUtils;

class ChurchMetaData
{
    public static function getChurchName()
    {
        return SystemConfig::getValue('sChurchName');
    }

    public static function getChurchFullAddress(): string
    {
        $address = [];
        if (!empty(self::getChurchAddress())) {
            $address[] = self::getChurchAddress();
        }

        if (!empty(self::getChurchCity())) {
            $address[] = self::getChurchCity() . ',';
        }

        if (!empty(self::getChurchState())) {
            $address[] = self::getChurchState();
        }

        if (!empty(self::getChurchZip())) {
            $address[] = self::getChurchZip();
        }
        if (!empty(self::getChurchCountry())) {
            $address[] = self::getChurchCountry();
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

    public static function getChurchWebSite()
    {
        return SystemConfig::getValue('sChurchWebSite');
    }

    public static function getChurchLatitude()
    {
        $mapsPlugin = PluginManager::getPlugin('maps');
        if ($mapsPlugin !== null) {
            $lat = $mapsPlugin->getChurchLatitude();
            if ($lat !== '') {
                return $lat;
            }
            // Plugin active but no stored value — geocode and persist
            self::updateLatLng();
            return $mapsPlugin->getChurchLatitude();
        }

        // Legacy fallback: read from SystemConfig
        if (empty(SystemConfig::getValue('iChurchLatitude'))) {
            self::updateLatLng();
        }

        return SystemConfig::getValue('iChurchLatitude');
    }

    public static function getChurchLongitude()
    {
        $mapsPlugin = PluginManager::getPlugin('maps');
        if ($mapsPlugin !== null) {
            $lng = $mapsPlugin->getChurchLongitude();
            if ($lng !== '') {
                return $lng;
            }
            // Plugin active but no stored value — geocode and persist
            self::updateLatLng();
            return $mapsPlugin->getChurchLongitude();
        }

        // Legacy fallback: read from SystemConfig
        if (empty(SystemConfig::getValue('iChurchLongitude'))) {
            self::updateLatLng();
        }

        return SystemConfig::getValue('iChurchLongitude');
    }

    public static function getChurchTimeZone()
    {
        return SystemConfig::getValue('sTimeZone');
    }

    private static function updateLatLng(): void
    {
        if (!empty(self::getChurchFullAddress())) {
            $latLng = GeoUtils::getLatLong(self::getChurchFullAddress());
            if (!empty($latLng['Latitude']) && !empty($latLng['Longitude'])) {
                $mapsPlugin = PluginManager::getPlugin('maps');
                if ($mapsPlugin !== null) {
                    $mapsPlugin->setChurchLatLong($latLng['Latitude'], $latLng['Longitude']);
                } else {
                    SystemConfig::setValue('iChurchLatitude', $latLng['Latitude']);
                    SystemConfig::setValue('iChurchLongitude', $latLng['Longitude']);
                }
            }
        }
    }
}
