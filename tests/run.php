<?php

require_once "vendor/autoload.php";
require_once "runner/SeleniumTestConfig.php";

use PHPSauceConnect\SauceLabsSauceConnect;
use Symfony\Component\Console\Input\ArgvInput;

function ConsoleWriteLine($string) {
    echo $string."\n";
}

function parse_template($source_file, $output_file, $data) {
    // example template variables {a} and {bc}
    // example $data array
    // $data = Array("a" => 'one', "bc" => 'two');
    $q = file_get_contents($source_file);
    foreach ($data as $key => $value) {
        $q = str_replace('{'.$key.'}', $value, $q);
    }
    file_put_contents($output_file,$q);
}

function run_security_tests() {
    chdir("tests/security");
    if (!file_exists("security-checker.phar")){
        file_put_contents("security-checker.phar",fopen("http://get.sensiolabs.org/security-checker.phar","r"));
    }
    $security_tests_return_value = 0;
    passthru("php security-checker.phar security:check ../composer.lock", $security_tests_return_value);
    if ($security_tests_return_value){
        exit($security_tests_return_value);
    }
    passthru("php security-checker.phar security:check ../../src/composer.lock", $security_tests_return_value);
    if ($security_tests_return_value){
        exit($security_tests_return_value);
    }
}

function run_browser_automation_tests(SeleniumTestConfig $SeleniumTestConfg) {

    chdir("tests/behat");
    if($SeleniumTestConfg->SeleniumTestHostType == SeleniumTestHostType::REMOTE_SAUCE_LABS){
        $sauce = SauceLabsSauceConnect::GetSauceInstance($SeleniumTestConfg->GetSauceUsername(),$SeleniumTestConfg->GetSauceAccessKey());
        $sauce->Connect();
        parse_template("behat.yml.template","behat.yml",array(
            "URL" => $SeleniumTestConfg->TestURL,
            "WD_HOST" => $sauce->GetWDHost()
        ));
    }
    elseif ($SeleniumTestConfg->SeleniumTestHostType == SeleniumTestHostType::LOCAL_SELENIUM) {
        parse_template("behat.yml.template","behat.yml",array(
            "URL" =>    $SeleniumTestConfg->TestURL,
            "WD_HOST" => "http://localhost:4444/wd/hub"
        ));
    }
    elseif ($SeleniumTestConfg->SeleniumTestHostType == SeleniumTestHostType::REMOTE_DOCKER_HEADLESS) {
        //  docker run -d -p 4444:4444 --shm-size=2g selenium/standalone-chrome:3.141.5
        //     sleep 10
        //    sed -i "s;localhost$;$(hostname -f);g" ./behat/behat.yml
        //    sed -i "s|Chrome|chrome|g" ./behat/behat.yml
        //    sed -i "s|platform: Windows 10|browser: chrome|g" ./behat/behat.yml
        //    sed -i "s|version: 67.0|marionette: true\r          chrome:\r            switches: ['--disable-gpu', '--window-size=1280,1600']|g" ./behat/behat.yml
        //    sed -i "s;'/src';'';g" ./bootstrap.php
    }
    
    define('BEHAT_BIN_PATH', dirname(__FILE__)."/vendor/behat/behat/bin");

    $factory = new \Behat\Behat\ApplicationFactory();
    $options = array( 
        "0"
    );
    if (is_array($SeleniumTestConfg->BehatTestsToRun)  && count($SeleniumTestConfg->BehatTestsToRun) > 0 ) {
        $options = array_merge($options,array_values($SeleniumTestConfg->BehatTestsToRun));
    }

    $input = new ArgvInput($options);
    $factory->createApplication()->run($input);

}

$basedir = getcwd();

if (!file_exists("BuildConfig.json")){
    print("Must define BuildConfig.json to run tests");
    exit(1);
}

$buildconfigs = \json_decode(file_get_contents("BuildConfig.json"), true);

run_security_tests();
chdir($basedir);

$seleniumConfigs = new SeleniumTestConfig();
foreach ($buildconfigs['SeleniumTests'] as $key => $value) $seleniumConfigs->{$key} = $value;

run_browser_automation_tests($seleniumConfigs);
chdir($basedir);


?>