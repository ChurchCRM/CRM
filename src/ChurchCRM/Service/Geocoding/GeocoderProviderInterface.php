<?php

namespace ChurchCRM\Service\Geocoding;

/**
 * One geocoding service. Providers are tried in the order configured in the
 * `sGeocoderProviders` setting (see GeocoderChain); the first one that
 * returns coordinates wins.
 */
interface GeocoderProviderInterface
{
    /**
     * Name used in the `sGeocoderProviders` setting, e.g. "Nominatim". Matched case-insensitively.
     */
    public function getName(): string;

    /**
     * Whether this provider can be asked about an address in the given country.
     * A null/empty country means "unknown" and every provider should accept it.
     */
    public function supports(?string $country): bool;

    /**
     * Look up an address. Returns null when the service had no answer or the
     * request failed, so the chain can move on to the next provider.
     *
     * @return array{Latitude: float, Longitude: float}|null
     */
    public function geocode(string $street, ?string $city, ?string $state, ?string $zip, ?string $country): ?array;
}
