<?php

namespace ChurchCRM\Service;

use ChurchCRM\dto\SystemURLs;
use ChurchCRM\model\ChurchCRM\Family;
use ChurchCRM\model\ChurchCRM\FamilyQuery;
use ChurchCRM\model\ChurchCRM\Person;
use ChurchCRM\model\ChurchCRM\PersonQuery;
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
        return $this->buildMissingCoordinatesQuery()->count() + $this->buildMissingPersonCoordinatesQuery()->count();
    }

    /**
     * Active people with their own street address and no usable coordinates.
     */
    private function buildMissingPersonCoordinatesQuery(): PersonQuery
    {
        return PersonQuery::create()
            ->filterByDateDeactivated(null)
            ->filterByAddress1(null, Criteria::ISNOTNULL)
            ->where("TRIM(Person.Address1) != ''")
            ->condition('lat_null', 'Person.Latitude IS NULL')
            ->condition('lat_zero', 'Person.Latitude = ?', 0.0)
            ->where(['lat_null', 'lat_zero'], Criteria::LOGICAL_OR);
    }

    /**
     * Addresses still without coordinates, split by whether geocoding was ever tried:
     * `not_found` answered with no match, `untried` was never asked.
     *
     * @return array{not_found: int, untried: int}
     */
    public function getGeocodeStatusCounts(): array
    {
        $notFound = $this->buildMissingCoordinatesQuery()->filterByGeocodeTriedAt(null, Criteria::ISNOTNULL)->count()
            + $this->buildMissingPersonCoordinatesQuery()->filterByGeocodeTriedAt(null, Criteria::ISNOTNULL)->count();

        return ['not_found' => $notFound, 'untried' => $this->getMissingCoordinatesCount() - $notFound];
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
     *   'unavailable'        — no geocoding provider could answer (timeout, rate limit,
     *                          5xx); the family stays retryable
     *   'error'              — an unexpected exception was thrown
     *
     * A definitive answer (coordinates, no match, incomplete address) is stored on the record, so a
     * miss is remembered; 'unavailable' and 'error' store nothing and stay retryable.
     *
     * @return array{success: bool, reason: string|null}
     */
    private function geocodeWithReason(Family|Person $family): array
    {
        $street = trim($family->getAddress1() ?? '');

        // Empty street — skip geocoding (not a failure; address filter upstream
        // should prevent this, but guard defensively for direct callers).
        if (empty($street)) {
            $this->logger->debug('geocodeWithReason: skipping empty address for family ' . $family->getId());
            return ['success' => true, 'reason' => null];
        }

        // Incomplete address: street present but no city, state, or ZIP at all.
        // Nominatim structured queries require at least one of those to succeed.
        $city  = trim($family->getCity() ?? '');
        $state = trim($family->getState() ?? '');
        $zip   = trim($family->getZip() ?? '');
        if ($city === '' && $state === '' && $zip === '') {
            $this->logger->warning(
                'geocodeWithReason: incomplete address (no city/state/zip) for family ' . $family->getId()
            );
            $family->recordGeocode(null);
            $family->saveWithoutUpdateNote();
            return ['success' => false, 'reason' => 'incomplete_address'];
        }

        try {
            $country = $family->getCountry();

            $this->logger->debug('geocodeWithReason: geocoding family ' . $family->getId());

            $outcome = GeoUtils::lookup($street, $city, $state, $zip, $country);
            if ($outcome->isUnavailable()) {
                $this->logger->warning(
                    'geocodeWithReason: geocoding unavailable for family ' . $family->getId()
                );
                return ['success' => false, 'reason' => 'unavailable'];
            }

            $coords = $outcome->result?->toLatLong() ?? ['Latitude' => 0.0, 'Longitude' => 0.0];
            $lat    = (float) $coords['Latitude'];
            $lng    = (float) $coords['Longitude'];

            if ($lat === 0.0 && $lng === 0.0) {
                $this->logger->warning(
                    'geocodeWithReason: no result from any geocoding provider for family ' . $family->getId()
                );
                $family->recordGeocode(null);
                $family->saveWithoutUpdateNote();
                return ['success' => false, 'reason' => 'no_result'];
            }

            $family->recordGeocode(['Latitude' => $lat, 'Longitude' => $lng]);
            $family->saveWithoutUpdateNote();

            $this->logger->info(
                'geocodeWithReason: geocoded family ' . $family->getId() . ' -> ' . $lat . ', ' . $lng
            );
            return ['success' => true, 'reason' => null];
        } catch (\Throwable $e) {
            $this->logger->warning(
                'geocodeWithReason: exception for family ' . $family->getId() . ': ' . $e->getMessage()
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
     *   skip: int,
     *   processed: int,
     *   remaining: int,
     *   remainingNotFound: int,
     *   failures: list<array{id:int,name:string,address:string,editUrl:string,reason:string}>,
     *   failuresTruncated: bool
     * }
     */
    public function geocodeAllMissingFamilies(int $skip = 0): array
    {
        $skip = max(0, $skip);
        // Count the total BEFORE fetching the batch so `remaining` stays accurate
        // even when the batch is limited by MAX_GEOCODE_PER_RUN.
        $familyTotal = $this->buildMissingCoordinatesQuery()->count();
        $total       = $familyTotal + $this->buildMissingPersonCoordinatesQuery()->count();

        // Fetch only the rows we will process, not all missing rows into PHP memory.
        // The order is fixed (families by ID, then people by ID) and the caller passes back how
        // many records failed in its earlier batches as $skip, so an address Nominatim cannot
        // resolve is not re-queried at the front of every batch — that stalled installs with
        // more than one batch of unresolvable addresses (#9846).
        $batch = [];
        if ($skip < $familyTotal) {
            $batch = $this->buildMissingCoordinatesQuery()
                ->orderById()
                ->offset($skip)
                ->limit(self::MAX_GEOCODE_PER_RUN)
                ->find()
                ->getArrayCopy();
        }
        $room = self::MAX_GEOCODE_PER_RUN - count($batch);
        if ($room > 0) {
            $batch = array_merge($batch, $this->buildMissingPersonCoordinatesQuery()
                ->orderById()
                ->offset(max(0, $skip - $familyTotal))
                ->limit($room)
                ->find()
                ->getArrayCopy());
        }

        $geocoded = 0;
        $failed   = 0;
        $failures = [];
        $count    = count($batch);

        $this->logger->info('geocodeAllMissingFamilies: starting batch', [
            'total'     => $total,
            'skip'      => $skip,
            'batchSize' => $count,
        ]);

        foreach ($batch as $index => $family) {
            $result = $this->geocodeWithReason($family);
            $isPerson = $family instanceof Person;

            if ($result['success']) {
                $geocoded++;
            } else {
                $failed++;
                // Collect failure detail, capped at MAX_FAILURE_DETAILS
                if (count($failures) < self::MAX_FAILURE_DETAILS) {
                    $failures[] = [
                        'id'      => (int) $family->getId(),
                        'type'    => $isPerson ? 'person' : 'family',
                        'name'    => $isPerson ? $family->getFullName() : (string) $family->getName(),
                        'address' => (string) $family->getAddress(),
                        'editUrl' => $isPerson
                            ? SystemURLs::getRootPath() . '/PersonEditor.php?PersonID=' . (int) $family->getId()
                            : SystemURLs::getRootPath() . '/FamilyEditor.php?FamilyID=' . (int) $family->getId(),
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
        // during the batch (no geocoding provider returned a result for them).
        $remaining = max(0, $total - $geocoded);

        $this->logger->info('geocodeAllMissingFamilies: batch complete', [
            'geocoded'  => $geocoded,
            'failed'    => $failed,
            'remaining' => $remaining,
        ]);

        return [
            'total'             => $total,
            'skip'              => $skip,
            'processed'         => $count,
            'geocoded'          => $geocoded,
            'failed'            => $failed,
            'remaining'         => $remaining,
            'remainingNotFound' => $this->getGeocodeStatusCounts()['not_found'],
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
        return $this->geocodeWithReason($family)['success'];
    }

    /**
     * Geocode a person's own address after it was saved. A person without a street address is skipped.
     *
     * @return bool True if geocoding succeeded or wasn't needed, false if it found nothing or could not run
     */
    public function autoGeocodePerson(Person $person): bool
    {
        return $this->geocodeWithReason($person)['success'];
    }
}
