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

    public function geocode(string $street, ?string $city, ?string $state, ?string $zip, ?string $country): ?array
    {
        $params = [
            'format' => 'json',
            'limit' => 1,
            'accept-language' => Bootstrapper::getCurrentLocale()->getShortLocale(),
        ];

        // Structured query when components are provided (better accuracy)
        if (!empty($city) || !empty($state) || !empty($zip)) {
            $params['street'] = trim($street);
            if (!empty($city)) {
                $params['city'] = trim($city);
            }
            if (!empty($state)) {
                $params['state'] = trim($state);
            }
            if (!empty($zip)) {
                $params['postalcode'] = trim($zip);
            }
            // Only add country if it's actually provided (not empty/null)
            if (!empty($country)) {
                $params['country'] = trim($country);
            }
        } else {
            // Free-form query. Country is deliberately left out - it often makes matching fail.
            $params['q'] = trim($street);
        }

        $results = $this->fetchJson(
            self::ENDPOINT . '?' . http_build_query($params),
            ['User-Agent: ChurchCRM/7.0 (+https://churchcrm.io)']
        );
        if (empty($results) || !isset($results[0]['lat'], $results[0]['lon'])) {
            return null;
        }

        return [
            'Latitude'  => (float) $results[0]['lat'],
            'Longitude' => (float) $results[0]['lon'],
        ];
    }
}
