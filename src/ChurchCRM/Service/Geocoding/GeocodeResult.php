<?php

namespace ChurchCRM\Service\Geocoding;

/**
 * One provider's answer: coordinates plus the address the provider matched.
 * The matched address lets a caller compare it with what is stored.
 */
final class GeocodeResult
{
    /**
     * @param array{street?: string, city?: string, state?: string, zip?: string, country?: string} $address
     */
    public function __construct(
        public readonly float $latitude,
        public readonly float $longitude,
        public readonly string $provider,
        public readonly array $address = [],
    ) {
    }

    public function hasCoordinates(): bool
    {
        return $this->latitude !== 0.0 || $this->longitude !== 0.0;
    }

    /**
     * @return array{Latitude: float, Longitude: float}
     */
    public function toLatLong(): array
    {
        return ['Latitude' => $this->latitude, 'Longitude' => $this->longitude];
    }
}
