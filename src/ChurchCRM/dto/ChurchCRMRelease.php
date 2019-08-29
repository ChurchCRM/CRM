<?php


namespace ChurchCRM\dto;

Class ChurchCRMRelease {
    public $MAJOR;
    public $MINOR;
    public $PATCH;

    private $rawRelease;

    public function __construct(array $releaseArray){
        $this->rawRelease = $releaseArray;
        $versions = explode(".",$releaseArray["name"]);
        $this->MAJOR = $versions[0];
        $this->MINOR = $versions[1];
        $this->PATCH = $versions[2];
    }

    public static function FromString(string $releaseString) {
        return new ChurchCRMRelease(@["name" => $releaseString]);
    }

    public function equals(ChurchCRMRelease $b) {
        return $this->MAJOR == $b->MAJOR && $this->MINOR == $b->MINOR && $this->PATCH == $b->PATCH;
    }

    public function __toString()
    {
        try 
        {
            return (string) $this->MAJOR.".".$this->MINOR.".".$this->PATCH;
        } 
        catch (Exception $exception) 
        {
            return '';
        }
    }
}