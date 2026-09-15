<?php

namespace ChurchCRM\Service\Geocoding;

/**
 * The US Census Bureau geocoder. Free, no API key, United States only.
 * Interpolates house numbers from TIGER street ranges, which makes it far
 * more forgiving than Nominatim for suburban and rural US addresses.
 *
 * @see https://geocoding.geo.census.gov/geocoder/Geocoding_Services_API.html
 */
class CensusGeocoder extends AbstractHttpGeocoder
{
    public const NAME = 'Census';

    private const ENDPOINT = 'https://geocoding.geo.census.gov/geocoder/locations/';
    private const BENCHMARK = 'Public_AR_Current';

    /** Spellings of the United States accepted in the family/church country field. */
    private const US_COUNTRY_VALUES = ['us', 'usa', 'u.s.', 'u.s.a.', 'united states', 'united states of america'];

    public function getName(): string
    {
        return self::NAME;
    }

    public function supports(?string $country): bool
    {
        $country = strtolower(trim((string) $country));
        return $country === '' || \in_array($country, self::US_COUNTRY_VALUES, true);
    }

    public function geocode(string $street, ?string $city, ?string $state, ?string $zip, ?string $country): ?array
    {
        $params = ['benchmark' => self::BENCHMARK, 'format' => 'json'];

        if (!empty($city) || !empty($state) || !empty($zip)) {
            $params['street'] = trim($street);
            if (!empty($city)) {
                $params['city'] = trim($city);
            }
            if (!empty($state)) {
                $params['state'] = trim($state);
            }
            if (!empty($zip)) {
                $params['zip'] = trim($zip);
            }
            $url = self::ENDPOINT . 'address?' . http_build_query($params);
        } else {
            $params['address'] = trim($street);
            $url = self::ENDPOINT . 'onelineaddress?' . http_build_query($params);
        }

        // The Census service is slower than Nominatim; allow a longer timeout.
        $data = $this->fetchJson($url, [], 20);
        $match = $data['result']['addressMatches'][0]['coordinates'] ?? null;
        if (!\is_array($match) || !isset($match['x'], $match['y'])) {
            return null;
        }

        return [
            'Latitude'  => (float) $match['y'],
            'Longitude' => (float) $match['x'],
        ];
    }
}
