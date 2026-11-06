<?php

namespace ChurchCRM\model\ChurchCRM;

use DateTimeImmutable;

/**
 * Stored geocode of a Family or Person address.
 *
 * Coordinates set: found. No coordinates with GeocodeTriedAt set: tried, no match.
 * Neither: never tried. A provider that could not answer records nothing, so the row stays untried.
 */
trait GeocodedAddress
{
    public const GEOCODE_FOUND = 'found';
    public const GEOCODE_NOT_FOUND = 'not_found';
    public const GEOCODE_UNTRIED = 'untried';

    public function hasLatitudeAndLongitude(): bool
    {
        return !empty($this->getLatitude()) && !empty($this->getLongitude());
    }

    public function getGeocodeStatus(): string
    {
        if ($this->hasLatitudeAndLongitude()) {
            return self::GEOCODE_FOUND;
        }

        return $this->getGeocodeTriedAt() === null ? self::GEOCODE_UNTRIED : self::GEOCODE_NOT_FOUND;
    }

    /** @param array{Latitude: float|int|string, Longitude: float|int|string}|null $coordinates null records "tried, no match" */
    public function recordGeocode(?array $coordinates): void
    {
        $this->setLatitude($coordinates['Latitude'] ?? null);
        $this->setLongitude($coordinates['Longitude'] ?? null);
        $this->setGeocodeTriedAt(new DateTimeImmutable());
    }

    /** Back to untried when an address column changed and the caller set no geocode column itself. */
    protected function forgetGeocodeOnAddressChange(array $addressColumns, array $geocodeColumns): void
    {
        $touched = static fn (array $columns, self $row): bool => array_reduce(
            $columns,
            static fn (bool $any, string $column): bool => $any || $row->isColumnModified($column),
            false,
        );

        if ($touched($addressColumns, $this) && !$touched($geocodeColumns, $this)) {
            $this->setLatitude(null);
            $this->setLongitude(null);
            $this->setGeocodeTriedAt(null);
        }
    }
}
