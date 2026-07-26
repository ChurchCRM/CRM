<?php

namespace ChurchCRM\Service;

use ChurchCRM\model\ChurchCRM\Family;
use ChurchCRM\model\ChurchCRM\FamilyQuery;
use ChurchCRM\Utils\GeoUtils;
use ChurchCRM\Utils\LoggerUtils;
use Propel\Runtime\Connection\ConnectionInterface;

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
     * Trims all string fields and assigns them to the model. WeddingDate is
     * validated with DateTimeImmutable before being set — malformed input is
     * silently ignored (fixes B9 from #9229).
     *
     * Geocoding is intentionally NOT performed here. Callers should invoke
     * autoGeocodeFamily() after committing any enclosing database transaction
     * to avoid holding the DB connection open during a network call.
     *
     * The model's postInsert hook fires FAMILY_CREATED events automatically.
     *
     * @param array                    $fields Associative array of POST field names => values
     * @param int                      $userId ID of the user performing the action
     * @param ConnectionInterface|null $con    Optional Propel connection (participate in caller transaction)
     * @return Family                          The newly persisted Family
     */
    public function createFamilyFromCartInput(array $fields, int $userId, ?ConnectionInterface $con = null): Family
    {
        $family = new Family();
        // Trim all string inputs so that whitespace-only values are treated as empty
        // and leading/trailing spaces are not persisted to the database.
        $family->setName(trim($fields['FamilyName'] ?? ''));

        $address1 = trim($fields['Address1'] ?? '');
        if ($address1 !== '') { $family->setAddress1($address1); }

        $address2 = trim($fields['Address2'] ?? '');
        if ($address2 !== '') { $family->setAddress2($address2); }

        $city = trim($fields['City'] ?? '');
        if ($city !== '') { $family->setCity($city); }

        $zip = trim($fields['Zip'] ?? '');
        if ($zip !== '') { $family->setZip($zip); }

        $country = trim($fields['Country'] ?? '');
        if ($country !== '') { $family->setCountry($country); }

        // State: prefer select value, fall back to free-text box
        $state = trim(!empty($fields['State']) ? $fields['State'] : ($fields['StateTextbox'] ?? ''));
        if ($state !== '') { $family->setState($state); }

        $homePhone = trim($fields['HomePhone'] ?? '');
        if ($homePhone !== '') { $family->setHomePhone($homePhone); }

        $email = trim($fields['Email'] ?? '');
        if ($email !== '') { $family->setEmail($email); }

        // Validate WeddingDate before setting — malformed input would otherwise throw at ORM (fixes B9)
        if (!empty($fields['WeddingDate'])) {
            $wd = \DateTimeImmutable::createFromFormat('Y-m-d', $fields['WeddingDate']);
            if ($wd !== false) {
                $family->setWeddingDate($wd->format('Y-m-d'));
            }
        }

        $family->setDateEntered(date('YmdHis'));
        $family->setEnteredBy($userId);
        $family->save($con);

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
