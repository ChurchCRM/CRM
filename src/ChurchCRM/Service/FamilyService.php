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
     * Create a new Family from cart-form input fields.
     *
     * Validates and normalises each field before setting it on the model.
     * Calls autoGeocodeFamily() when a street address is present (behaviour
     * improvement over the legacy cart-to-family page which never geocoded).
     * The model's postInsert hook fires timeline/email/FAMILY_CREATED automatically.
     *
     * Fixes B9 (raw WeddingDate → ORM throw) from #9229.
     *
     * @param array $fields  Associative array of POST field names → values
     * @param int   $userId  ID of the user performing the action
     * @return Family        The newly persisted Family
     */
    public function createFamilyFromCartInput(array $fields, int $userId): Family
    {
        $family = new Family();
        $family->setName($fields['FamilyName'] ?? '');
        if (!empty($fields['Address1']))   { $family->setAddress1($fields['Address1']); }
        if (!empty($fields['Address2']))   { $family->setAddress2($fields['Address2']); }
        if (!empty($fields['City']))       { $family->setCity($fields['City']); }
        if (!empty($fields['Zip']))        { $family->setZip($fields['Zip']); }
        if (!empty($fields['Country']))    { $family->setCountry($fields['Country']); }
        // State: prefer select value, fall back to free-text box
        $state = !empty($fields['State']) ? $fields['State'] : ($fields['StateTextbox'] ?? '');
        if (!empty($state))                { $family->setState($state); }
        if (!empty($fields['HomePhone']))  { $family->setHomePhone($fields['HomePhone']); }
        if (!empty($fields['Email']))      { $family->setEmail($fields['Email']); }
        // Validate WeddingDate before setting — malformed input would otherwise throw at ORM (fixes B9)
        if (!empty($fields['WeddingDate'])) {
            $wd = \DateTimeImmutable::createFromFormat('Y-m-d', $fields['WeddingDate']);
            if ($wd !== false) {
                $family->setWeddingDate($wd->format('Y-m-d'));
            }
        }
        $family->setDateEntered(date('YmdHis'));
        $family->setEnteredBy($userId);
        $family->save();
        // Geocode if an address was supplied (legacy cart-to-family page never geocoded)
        if (!empty($fields['Address1']) || !empty($fields['City'])) {
            $this->autoGeocodeFamily($family);
        }
        return $family;
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
