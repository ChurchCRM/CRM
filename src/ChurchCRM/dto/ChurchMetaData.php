<?php

namespace ChurchCRM\dto;

use ChurchCRM\Utils\GeoUtils;

/**
 * Strongly-typed accessors for the church-identity portion of SystemConfig
 * (`sChurchName`, `sChurchAddress`, ...). Each string getter always
 * returns a trimmed string — never null — so callers don't need to cast,
 * trim, or null-check at every use site. An unset/whitespace-only config
 * value surfaces as `""` and callers can apply `?:` fallbacks cleanly.
 */
class ChurchMetaData
{
    /** Read a SystemConfig key as a trimmed string, coalescing null. */
    private static function readString(string $key): string
    {
        return trim((string) SystemConfig::getValue($key));
    }

    public static function getChurchName(): string
    {
        return self::readString('sChurchName');
    }

    public static function getChurchFullAddress(): string
    {
        $address = [];
        if (self::getChurchAddress() !== '') {
            $address[] = self::getChurchAddress();
        }

        if (self::getChurchCity() !== '') {
            $address[] = self::getChurchCity() . ',';
        }

        if (self::getChurchState() !== '') {
            $address[] = self::getChurchState();
        }

        if (self::getChurchZip() !== '') {
            $address[] = self::getChurchZip();
        }
        if (self::getChurchCountry() !== '') {
            $address[] = self::getChurchCountry();
        }

        return implode(' ', $address);
    }

    public static function getChurchAddress(): string
    {
        return self::readString('sChurchAddress');
    }

    public static function getChurchCity(): string
    {
        return self::readString('sChurchCity');
    }

    public static function getChurchState(): string
    {
        return self::readString('sChurchState');
    }

    public static function getChurchZip(): string
    {
        return self::readString('sChurchZip');
    }

    public static function getChurchCountry(): string
    {
        return self::readString('sChurchCountry');
    }

    public static function getChurchEmail(): string
    {
        return self::readString('sChurchEmail');
    }

    public static function getChurchPhone(): string
    {
        return self::readString('sChurchPhone');
    }

    public static function getChurchWebSite(): string
    {
        return self::readString('sChurchWebSite');
    }

    /**
     * Absolute URL of the church logo for use in email templates (and
     * eventually other external-facing surfaces like letters or reports).
     * Falls back to the bundled ChurchCRM logo if the admin-configured
     * value is empty or not a valid http(s) URL — this way external
     * email clients always see a working image.
     */
    public static function getChurchLogoURL(): string
    {
        $configured = self::readString('sChurchLogoURL');
        if ($configured !== '' && filter_var($configured, FILTER_VALIDATE_URL) !== false) {
            return $configured;
        }

        return SystemURLs::getURL() . '/Images/logo-churchcrm-350.jpg';
    }

    /**
     * Church latitude as a float; `0.0` when unset. Triggers a geocode
     * against the configured full address on first read if missing.
     */
    public static function getChurchLatitude(): float
    {
        if (self::readString('iChurchLatitude') === '') {
            self::updateLatLng();
        }

        return (float) SystemConfig::getValue('iChurchLatitude');
    }

    public static function getChurchLongitude(): float
    {
        if (self::readString('iChurchLongitude') === '') {
            self::updateLatLng();
        }

        return (float) SystemConfig::getValue('iChurchLongitude');
    }

    /** True when a geocoded latitude is stored; use in place of the old `!== ''` check. */
    public static function hasChurchLocation(): bool
    {
        return self::readString('iChurchLatitude') !== '' && self::readString('iChurchLongitude') !== '';
    }

    public static function getChurchTimeZone(): string
    {
        return self::readString('sTimeZone');
    }

    private static function updateLatLng(): void
    {
        if (self::getChurchFullAddress() !== '') {
            $latLng = GeoUtils::getLatLong(self::getChurchFullAddress());
            if (!empty($latLng['Latitude']) && !empty($latLng['Longitude'])) {
                SystemConfig::setValue('iChurchLatitude', $latLng['Latitude']);
                SystemConfig::setValue('iChurchLongitude', $latLng['Longitude']);
            }
        }
    }
}
