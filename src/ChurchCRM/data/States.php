<?php

namespace ChurchCRM\data;

use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Utils\MiscUtils;

class States
{
    private $countryCode;
    private $states = [];

    public function __construct($countryCode)
    {
        $this->countryCode = $countryCode;

        $stateFileName = SystemURLs::getDocumentRoot().'/locale/states/'.$countryCode.'.json';
        if (is_file($stateFileName)) {
            $statesFile = file_get_contents($stateFileName);
            MiscUtils::throwIfFailed($statesFile);

            $this->states = json_decode($statesFile, true, 512, JSON_THROW_ON_ERROR);
        }
    }

    public function getNames()
    {
        return array_values($this->states);
    }

    public function getAll()
    {
        return $this->states;
    }
}
