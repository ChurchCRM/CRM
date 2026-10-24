<?php

namespace ChurchCRM\data;

use ChurchCRM\data\Countries;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Utils\MiscUtils;

class States
{
    private array $states = [];

    public function __construct(string $countryCode)
    {
        // Defense-in-depth: reject codes not in the canonical country list,
        // regardless of how this class is called (not just via the route).
        if (!array_key_exists(strtoupper($countryCode), Countries::getAll())) {
            return;
        }

        // State files are stored as lowercase ISO codes (e.g. us.json, ca.json).
        $stateFileName = SystemURLs::getDocumentRoot() . '/locale/states/' . strtolower($countryCode) . '.json';
        if (is_file($stateFileName)) {
            $statesFile = file_get_contents($stateFileName);
            MiscUtils::throwIfFailed($statesFile);

            $this->states = json_decode($statesFile, true, 512);
        }
    }

    public function getNames(): array
    {
        return array_values($this->states);
    }

    public function getAll(): array
    {
        return $this->states;
    }

    /**
     * The code for a state code or state name of this country; any other value unchanged.
     */
    public function toCode(?string $value): string
    {
        if ($value === null || $value === '' || isset($this->states[$value])) {
            return (string) $value;
        }
        $code = array_search($value, $this->states, true);

        return $code === false ? $value : (string) $code;
    }

    /**
     * The value to save when a form submits $submitted for a record that holds $stored.
     * The state select posts codes while many records hold names, so $stored is kept
     * when both are the same state of $country; otherwise an unchanged save would
     * rewrite the record and count as an address change.
     */
    public static function keepStoredIfSame(?string $country, ?string $stored, ?string $submitted): ?string
    {
        $states = new self(Countries::toISO($country));

        return $states->toCode($stored) === $states->toCode($submitted) ? $stored : $submitted;
    }
}
