<?php

namespace ChurchCRM\Service\Geocoding;

use ChurchCRM\data\Countries;

/**
 * The US Census Bureau geocoder. Free, no API key, United States only.
 * Interpolates house numbers from TIGER street ranges, which makes it far
 * more forgiving than Nominatim for suburban and rural US addresses.
 *
 * @see https://geocoding.geo.census.gov/geocoder/Geocoding_Services_API.html
 */
class CensusGeocoder extends AbstractHttpGeocoder
{
    public const NAME = 'US Census';

    private const ENDPOINT = 'https://geocoding.geo.census.gov/geocoder/locations/';
    private const BENCHMARK = 'Public_AR_Current';

    private const COUNTRY_NAME = 'United States';

    /** The service is slower than Nominatim, but GeocoderChain also caps the whole lookup. */
    private const TIMEOUT_SECONDS = 10;

    public function getName(): string
    {
        return self::NAME;
    }

    public function supports(?string $country): bool
    {
        return Countries::toISO($country) === 'US';
    }

    public function geocode(string $street, ?string $city, ?string $state, ?string $zip, ?string $country): ?GeocodeResult
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

        $data = $this->fetchJson($url, [], self::TIMEOUT_SECONDS);
        $match = $data['result']['addressMatches'][0] ?? null;
        $coordinates = \is_array($match) ? ($match['coordinates'] ?? null) : null;
        if (!\is_array($coordinates) || !isset($coordinates['x'], $coordinates['y'])) {
            return null;
        }

        $components = \is_array($match['addressComponents'] ?? null) ? $match['addressComponents'] : [];
        $address = array_filter([
            'street'  => trim(explode(',', (string) ($match['matchedAddress'] ?? ''))[0]),
            'city'    => (string) ($components['city'] ?? ''),
            'state'   => (string) ($components['state'] ?? ''),
            'zip'     => (string) ($components['zip'] ?? ''),
            'country' => self::COUNTRY_NAME,
        ], static fn (string $value): bool => $value !== '');

        return new GeocodeResult((float) $coordinates['y'], (float) $coordinates['x'], self::NAME, $address);
    }
}
