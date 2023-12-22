<?php

namespace ChurchCRM\data;

use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Utils\MiscUtils;

class States
{
    private string $countryCode;
    private array $states = [];

    public function __construct(string $countryCode)
    {
        $this->countryCode = $countryCode;

        $stateFileName = SystemURLs::getDocumentRoot() . '/locale/states/' . $countryCode . '.json';
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
