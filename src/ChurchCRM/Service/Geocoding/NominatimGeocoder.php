<?php

namespace ChurchCRM\Service\Geocoding;

use ChurchCRM\Bootstrapper;

/**
 * OpenStreetMap's Nominatim service. Worldwide, free, no API key.
 * Usage policy: at most one request per second and a descriptive User-Agent.
 *
 * @see https://nominatim.org/release-docs/latest/api/Search/
 */
class NominatimGeocoder extends AbstractHttpGeocoder
{
    public const NAME = 'Nominatim';

    private const ENDPOINT = 'https://nominatim.openstreetmap.org/search';

    public function getName(): string
    {
        return self::NAME;
    }

    /**
     * Nominatim "addresstype" values that mean the match is only a locality
     * (the street was not found and Nominatim fell back to the city, county,
     * postcode...). A free-form query happily returns these, and a pin on the
     * wrong town is worse than no pin, so they are rejected.
     */
    private const LOCALITY_ADDRESS_TYPES = [
        'city', 'town', 'village', 'hamlet', 'suburb', 'neighbourhood', 'quarter', 'borough',
        'municipality', 'county', 'state', 'region', 'province', 'country', 'postcode', 'administrative',
    ];

    public function geocode(string $street, ?string $city, ?string $state, ?string $zip, ?string $country): ?array
    {
        $baseParams = [
            'format' => 'json',
            'limit' => 1,
            'accept-language' => Bootstrapper::getCurrentLocale()->getShortLocale(),
        ];

        $freeFormParts = [trim($street)];
        foreach ([$city, $state, $zip] as $part) {
            if (!empty($part)) {
                $freeFormParts[] = trim($part);
            }
        }
        // Country is deliberately left out of the free-form query - it often makes matching fail.
        $freeForm = $baseParams + ['q' => implode(', ', $freeFormParts)];

        // Structured query first when components are provided (better accuracy)
        if (!empty($city) || !empty($state) || !empty($zip)) {
            $structured = $baseParams + ['street' => trim($street)];
            if (!empty($city)) {
                $structured['city'] = trim($city);
            }
            if (!empty($state)) {
                $structured['state'] = trim($state);
            }
            if (!empty($zip)) {
                $structured['postalcode'] = trim($zip);
            }
            if (!empty($country)) {
                $structured['country'] = trim($country);
            }

            $result = $this->search($structured, false);
            if ($result !== null) {
                return $result;
            }

            // Structured queries need OSM's exact street spelling; retry once
            // free-form, after the 1 req/sec pause Nominatim's policy requires.
            // The pause holds this PHP worker for a second on every miss; at
            // ChurchCRM's scale that is acceptable, and the bulk action already
            // paces itself at one family per second (FamilyService).
            $this->logger->debug('Geocoding: Nominatim structured query found nothing, retrying free-form');
            sleep(1);
        }

        return $this->search($freeForm, true);
    }

    /**
     * @param array<string, mixed> $params
     * @param bool $requireStreetLevel reject a result that is only a locality (see LOCALITY_ADDRESS_TYPES)
     * @return array{Latitude: float, Longitude: float}|null
     */
    private function search(array $params, bool $requireStreetLevel): ?array
    {
        $results = $this->fetchJson(
            self::ENDPOINT . '?' . http_build_query($params),
            ['User-Agent: ChurchCRM/7.0 (+https://churchcrm.io)']
        );
        if (empty($results) || !isset($results[0]['lat'], $results[0]['lon'])) {
            return null;
        }

        $addressType = strtolower((string) ($results[0]['addresstype'] ?? $results[0]['type'] ?? ''));
        if ($requireStreetLevel && \in_array($addressType, self::LOCALITY_ADDRESS_TYPES, true)) {
            $this->logger->debug('Geocoding: Nominatim free-form result is only a locality (' . $addressType . '), ignoring');
            return null;
        }

        return [
            'Latitude'  => (float) $results[0]['lat'],
            'Longitude' => (float) $results[0]['lon'],
        ];
    }
}
