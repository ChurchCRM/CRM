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


    public function equals(ChurchCRMRelease $b) {
        return $this->MAJOR == $b->MAJOR && $this->MINOR == $b->MINOR && $this->PATCH == $b->PATCH;
    }

    public function compareTo(ChurchCRMRelease $b) {
        if ($this->MAJOR < $b->MAJOR )
        {
            return -1;
        }
        elseif ($this->MAJOR > $b->MAJOR ) {
            return 1;
        }
        elseif ($this->MAJOR  ==  $b->MAJOR ) {
            if ($this->MINOR < $b->MINOR) {
                return -1;
            }
            elseif ($this->MINOR > $b->MINOR) {
                return 1;
            }
            elseif ($this->MINOR == $b->MINOR) {
                if ($this->PATCH < $b->PATCH)
                {
                    return -1;
                }
                else if ($this->PATCH > $b->PATCH) {
                    return 1;
                }
                else if($this->PATCH == $b->PATCH) {
                    return 0;
                }
            }
        }
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

    public function getDownloadURL() {
        foreach ($this->rawRelease['assets'] as $asset) {
            if ($asset['name'] == "ChurchCRM-" . $this->rawRelease['name'] . ".zip") {
            $url = $asset['browser_download_url'];
            }
        }
        return $url;
    }

    public function getReleaseNotes(): string {
        return $this->rawRelease['body'];
    }

    public function isPreRelease(): bool {
        // yeah, it's a boolean in the JSON, but 
        // let's check it to be sure this function returns a boolean.
        return $this->rawRelease['prerelease'] == true;
    }
}