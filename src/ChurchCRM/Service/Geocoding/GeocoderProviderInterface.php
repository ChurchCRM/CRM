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
     * Whether this provider should be asked about an address in the given country.
     * $country is the resolved country (the record's own, else the default country, else
     * the church country); null means none of them is set. A provider that covers only some
     * countries must decline a null country.
     */
    public function supports(?string $country): bool;

    /**
     * Look up an address. Reports found, not found (the service answered and has no
     * such address) or unavailable (timeout, 429, 5xx), so the chain can retry or move on.
     * No single request may take longer than $timeoutSeconds.
     */
    public function geocode(string $street, ?string $city, ?string $state, ?string $zip, ?string $country, int $timeoutSeconds = 10): GeocodeOutcome;
}
