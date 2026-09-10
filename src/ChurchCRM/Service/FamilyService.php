<?php

namespace ChurchCRM\Service;

use ChurchCRM\dto\SystemURLs;
use ChurchCRM\model\ChurchCRM\Family;
use ChurchCRM\model\ChurchCRM\FamilyQuery;
use ChurchCRM\Utils\GeoUtils;
use ChurchCRM\Utils\LoggerUtils;
use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\Connection\ConnectionInterface;

class FamilyService
{
    /**
     * Maximum number of families to geocode in a single call to geocodeAllMissingFamilies().
     * Keeps the request within a reasonable wall-clock time (~1 req/sec throttle).
     */
    public const MAX_GEOCODE_PER_RUN = 50;

    /**
     * Maximum number of per-family failure details to include in the
     * geocodeAllMissingFamilies() response. Prevents unbounded response payloads
     * when many families cannot be geocoded.
     */
    public const MAX_FAILURE_DETAILS = 20;

    private $logger;

    public function __construct()
    {
        $this->logger = LoggerUtils::getAppLogger();
    }

    // -------------------------------------------------------------------------
    // Coordinate helpers
    // -------------------------------------------------------------------------

    /**
     * Build a base query selecting active families that are missing coordinates
     * and have a geocodeable street address.
     *
     * "Missing coordinates" means latitude IS NULL or equals 0.
     * Families without an Address1 are excluded because they cannot be geocoded.
     *
     * @return FamilyQuery
     */
    private function buildMissingCoordinatesQuery(): FamilyQuery
    {
        $query = FamilyQuery::create()
            ->filterByDateDeactivated(null)
            ->filterByAddress1(null, Criteria::ISNOTNULL)
            ->where("TRIM(Family.Address1) != ''");

        // latitude IS NULL OR latitude = 0
        $query->condition('lat_null', 'Family.Latitude IS NULL');
        $query->condition('lat_zero', 'Family.Latitude = ?', 0.0);
        $query->combine(['lat_null', 'lat_zero'], 'or', 'lat_missing');
        $query->where(['lat_missing']);

        return $query;
    }

    /**
     * Get count of active families that are missing usable coordinate data.
     *
     * @return int
     */
    public function getMissingCoordinatesCount(): int
    {
        return $this->buildMissingCoordinatesQuery()->count();
    }

    /**
     * Geocode a single family and return a structured result with a machine-
     * readable reason on failure.
     *
     * Reason codes:
     *   null                 — success (or skipped because address is empty)
     *   'incomplete_address' — Address1 is present but city, state, and ZIP are
     *                          all absent; Nominatim cannot resolve a street alone
     *   'no_result'          — Nominatim returned no usable coordinates (address
     *                          exists but could not be matched, or API was unreachable)
     *   'error'              — an unexpected exception was thrown
     *
     * @param Family $family
     * @return array{success: bool, reason: string|null}
     */
    private function geocodeFamilyWithReason(Family $family): array
    {
        $street = trim($family->getAddress1() ?? '');

        // Empty street — skip geocoding (not a failure; address filter upstream
        // should prevent this, but guard defensively for direct callers).
        if (empty($street)) {
            $this->logger->debug('geocodeFamilyWithReason: skipping empty address for family ' . $family->getId());
            return ['success' => true, 'reason' => null];
        }

        // Incomplete address: street present but no city, state, or ZIP at all.
        // Nominatim structured queries require at least one of those to succeed.
        $city  = trim($family->getCity() ?? '');
        $state = trim($family->getState() ?? '');
        $zip   = trim($family->getZip() ?? '');
        if ($city === '' && $state === '' && $zip === '') {
            $this->logger->warning(
                'geocodeFamilyWithReason: incomplete address (no city/state/zip) for family ' . $family->getId()
            );
            return ['success' => false, 'reason' => 'incomplete_address'];
        }

        try {
            $country = $family->getCountry();

            $this->logger->debug('geocodeFamilyWithReason: geocoding family ' . $family->getId());

            $coords = GeoUtils::getLatLong($street, $city, $state, $zip, $country);
            $lat    = (float) $coords['Latitude'];
            $lng    = (float) $coords['Longitude'];

            if ($lat === 0.0 && $lng === 0.0) {
                $this->logger->warning(
                    'geocodeFamilyWithReason: no result from Nominatim for family ' . $family->getId()
                );
                return ['success' => false, 'reason' => 'no_result'];
            }

            $family->setLatitude($lat);
            $family->setLongitude($lng);
            $family->save();

            $this->logger->info(
                'geocodeFamilyWithReason: geocoded family ' . $family->getId() . ' -> ' . $lat . ', ' . $lng
            );
            return ['success' => true, 'reason' => null];
        } catch (\Throwable $e) {
            $this->logger->warning(
                'geocodeFamilyWithReason: exception for family ' . $family->getId() . ': ' . $e->getMessage()
            );
            return ['success' => false, 'reason' => 'error'];
        }
    }

    /**
     * Geocode up to MAX_GEOCODE_PER_RUN families that are missing coordinates,
     * throttled to approximately one Nominatim API request per second to comply
     * with the fair-use policy.
     *
     * The response includes a `failures` array (capped at MAX_FAILURE_DETAILS)
     * with per-family detail for families that could not be geocoded, and a
     * `failuresTruncated` flag when `failed > count(failures)`.
     *
     * @return array{
     *   total: int,
     *   geocoded: int,
     *   failed: int,
     *   remaining: int,
     *   failures: list<array{id:int,name:string,address:string,editUrl:string,reason:string}>,
     *   failuresTruncated: bool
     * }
     */
    public function geocodeAllMissingFamilies(): array
    {
        // Count the total BEFORE fetching the batch so `remaining` stays accurate
        // even when the batch is limited by MAX_GEOCODE_PER_RUN.
        $total = $this->getMissingCoordinatesCount();

        // Fetch only the rows we will process, not all missing rows into PHP memory.
        $batch = $this->buildMissingCoordinatesQuery()
            ->limit(self::MAX_GEOCODE_PER_RUN)
            ->find();

        $geocoded = 0;
        $failed   = 0;
        $failures = [];
        $count    = count($batch);

        $this->logger->info('geocodeAllMissingFamilies: starting batch', [
            'total'     => $total,
            'batchSize' => $count,
        ]);

        foreach ($batch as $index => $family) {
            $result = $this->geocodeFamilyWithReason($family);

            if ($result['success']) {
                $geocoded++;
            } else {
                $failed++;
                // Collect failure detail, capped at MAX_FAILURE_DETAILS
                if (count($failures) < self::MAX_FAILURE_DETAILS) {
                    $failures[] = [
                        'id'      => (int) $family->getId(),
                        'name'    => (string) $family->getName(),
                        'address' => (string) $family->getAddress(),
                        'editUrl' => SystemURLs::getRootPath() . '/FamilyEditor.php?FamilyID=' . (int) $family->getId(),
                        'reason'  => (string) $result['reason'],
                    ];
                }
            }

            // Throttle: sleep 1 second between iterations that actually called
            // Nominatim. Skip the delay for 'incomplete_address' which exits
            // before any network request is made.
            if ($index < $count - 1 && $result['reason'] !== 'incomplete_address') {
                sleep(1);
            }
        }

        // Remaining = families that still need geocoding after this run:
        // those not included in this batch (overflow) PLUS those that failed
        // during the batch (Nominatim returned no result for them).
        $remaining = max(0, $total - $geocoded);

        $this->logger->info('geocodeAllMissingFamilies: batch complete', [
            'geocoded'  => $geocoded,
            'failed'    => $failed,
            'remaining' => $remaining,
        ]);

        return [
            'total'             => $total,
            'geocoded'          => $geocoded,
            'failed'            => $failed,
            'remaining'         => $remaining,
            'failures'          => $failures,
            'failuresTruncated' => $failed > count($failures),
        ];
    }

    // -------------------------------------------------------------------------
    // Family CRUD helpers
    // -------------------------------------------------------------------------

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
        return $this->geocodeFamilyWithReason($family)['success'];
    }
}
