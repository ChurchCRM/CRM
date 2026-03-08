<?php

namespace ChurchCRM\Service;

use ChurchCRM\model\ChurchCRM\Family;
use ChurchCRM\model\ChurchCRM\FamilyQuery;
use ChurchCRM\Utils\GeoUtils;
use ChurchCRM\Utils\LoggerUtils;

class FamilyService
{
    private $logger;

    public function __construct()
    {
        $this->logger = LoggerUtils::getAppLogger();
    }

    /**
     * Get count of families missing coordinate data.
     *
     * @return int Count of families with empty Latitude field
     */
    public function getMissingCoordinatesCount(): int
    {
        return FamilyQuery::create()
            ->filterByLatitude(null)
            ->count();
    }

    /**
     * Auto-geocode a family's address if it has changed.
     *
     * Called after family address is saved. Attempts to geocode via Nominatim API
     * using structured address components for better accuracy.
     * Failures are logged but do not break the transaction.
     *
     * @param Family $family The family to geocode
     * @return bool True if geocoding succeeded or wasn't needed, false if API failed
     */
    public function autoGeocodeFamily(Family $family): bool
    {
        // Don't geocode if street address is empty
        $street = trim($family->getAddress1() ?? '');
        if (empty($street)) {
            $this->logger->debug('autoGeocodeFamily: skipping empty address for family ' . $family->getId());
            return true;
        }

        // Try to geocode using structured address components for better Nominatim accuracy
        try {
            $city = $family->getCity();
            $state = $family->getState();
            $zip = $family->getZip();
            $country = $family->getCountry();

            $this->logger->debug('autoGeocodeFamily: geocoding family ' . $family->getId());

            $coords = GeoUtils::getLatLong(
                $street,
                $city,
                $state,
                $zip,
                $country
            );
            $lat = (float) $coords['Latitude'];
            $lng = (float) $coords['Longitude'];

            // If geocoding failed (returns 0,0), log but don't break
            if ($lat === 0.0 && $lng === 0.0) {
                $this->logger->warning('autoGeocodeFamily: Could not geocode address for family ' . $family->getId());
                return false;
            }

            // Update family coordinates and save
            $family->setLatitude($lat);
            $family->setLongitude($lng);
            $family->save();

            $this->logger->info('autoGeocodeFamily: Geocoded family ' . $family->getId() . ' -> ' . $lat . ', ' . $lng);
            return true;
        } catch (\Throwable $e) {
            $this->logger->warning('autoGeocodeFamily error for family ' . $family->getId() . ': ' . $e->getMessage());
            return false;
        }
    }
}
