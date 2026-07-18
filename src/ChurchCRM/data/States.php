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

            $this->states = json_decode($statesFile, true, 512, JSON_THROW_ON_ERROR);
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
}
