<?php
namespace ChurchCRM\data;

use ChurchCRM\dto\SystemURLs;

class States
{
    private $countryCode;
    private $states = [];
    
    public function __construct($countryCode)
    {
        $this->countryCode = $countryCode;
        $stateFileName = SystemURLs::getDocumentRoot() . '/locale/states/'. $countryCode .'.json';
        if( is_file($stateFileName)) {
            $satesFile = file_get_contents($stateFileName);
            $this->states = json_decode($satesFile, true);
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
