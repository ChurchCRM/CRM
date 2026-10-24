<?php

namespace ChurchCRM\Service\Geocoding;

/**
 * What a lookup came back with. "Not found" means the service answered and has
 * no such address; "unavailable" means it did not answer (timeout, 429, 5xx),
 * so the address may still resolve later.
 */
final class GeocodeOutcome
{
    public const FOUND = 'found';
    public const NOT_FOUND = 'not_found';
    public const UNAVAILABLE = 'unavailable';

    private function __construct(
        public readonly string $status,
        public readonly ?GeocodeResult $result = null,
        public readonly ?int $retryAfterSeconds = null,
    ) {
    }

    public static function found(GeocodeResult $result): self
    {
        return new self(self::FOUND, $result);
    }

    public static function notFound(): self
    {
        return new self(self::NOT_FOUND);
    }

    public static function unavailable(?int $retryAfterSeconds = null): self
    {
        return new self(self::UNAVAILABLE, null, $retryAfterSeconds);
    }

    public function isFound(): bool
    {
        return $this->status === self::FOUND;
    }

    public function isUnavailable(): bool
    {
        return $this->status === self::UNAVAILABLE;
    }
}
