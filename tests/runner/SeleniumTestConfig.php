<?php

class SeleniumTestHostType {
    const LOCAL_SELENIUM = 0;
    const REMOTE_SAUCE_LABS = 1;
    const REMOTE_DOCKER_HEADLESS = 2;
}

class SeleniumTestConfig {
    public $TestURL;
    public $SauceUsername;
    public $SauceAccessKey;
    /** 
     * @var array
     */
    public $BehatTestsToRun;
    /**
     * @var SeleniumTestHostType
     */
    public $SeleniumTestHostType;

    public function GetSauceUsername() {
        if (!empty($this->SauceUsername)){
            ConsoleWriteLine("Using SauceConnect username from buildconfig.json");
            return $this->SauceUsername;
        }
        elseif (!empty(getenv('SAUCE_USERNAME'))) {
            ConsoleWriteLine("Using SauceConnect username from environment variables");
            return getenv('SAUCE_USERNAME');
        }
        else {
            throw new \Exception("No SauceConnect username could be found");
        }
    }
    public function GetSauceAccessKey() {
        if (!empty($this->SauceAccessKey)){
            ConsoleWriteLine("Using SauceConnect access key from buildconfig.json");
            return $this->SauceAccessKey;
        }
        elseif (!empty(getenv('SAUCE_ACCESS_KEY'))) {
            ConsoleWriteLine("Using SauceConnect access key from environment variables");
            return getenv('SAUCE_ACCESS_KEY');
        }
        else {
            throw new \Exception("No SauceConnect access key could be found");
        }
    }
}