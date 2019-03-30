<?php

use Behat\Behat\Context\Context;
use Behat\Gherkin\Node\PyStringNode;
use Behat\Gherkin\Node\TableNode;
use Behat\MinkExtension\Context\MinkContext;

/**
 * Defines application features from the specific context.
 */
class FeatureContext extends MinkContext
{
    /**
     * Initializes context.
     *
     * Every scenario gets its own context instance.
     * You can also pass arbitrary arguments to the
     * context constructor through behat.yml.
     */
    public function __construct()
    {
    }

    /**
    * @Given /^I am authenticated as "([^"]*)" using "([^"]*)"$/
    */
    public function iAmAuthenticatedAs($username, $password)
    {
      #borrowed from https://vivait.co.uk/labs/handling-authentication-when-using-behat-mink
      $this->visit('/Login');
      if ( strpos($this->getSession()->getCurrentUrl(),"SystemDBUpdate"))
      {
        // do the upgrade,
        $this->pressButton("upgradeDatabase");
      }
      $this->fillField('UserBox', $username);
      $this->fillField('PasswordBox', $password);
      $this->pressButton('Login');
    }

    /**
    * @Then /^(?:|I )click (?:on |)(?:|the )"([^"]*)"(?:|.*)$/
    */
    public
    function iClickOn($arg1)
    {
        $findName = $this->getSession()->getPage()->find("css", $arg1);
        if (empty($findName)) {
            throw new Exception($arg1 . " could not be found");
        } else {
            $findName->click();
        }
    }

    /**
    * Wait for AJAX to finish.
    *
    * @Given /^I wait for AJAX to finish$/
    */
   public function iWaitForAjaxToFinish() {
    $waitTime = 10000;
    try {
        //Wait for jQuery
        if ($this->getSession()->evaluateScript("return (typeof jQuery != 'undefined')")) {
            $this->getSession()->wait($waitTime, '(0 === jQuery.active && 0 === jQuery(\':animated\').length)');
        }
    } catch (Exception $e) {
        var_dump($e->getMessage()); //Debug here.
    }
   }

    /**
    * Wait for FullCalendarJS to load
    *
    * @Given /^I wait for the calendar to load$/
    */
   public function iWaitForTheCalendarToLoad() {
        // need to wait for the initial AJAX post to succeed.
        $this->getSession()->wait(3000);
        // Then need to wait for FullCalendarJS to tell us that it's done loading
        $this->getSession()->wait(3000, 'window.CRM.isCalendarLoading === false');
   }

      /**
     * Fills in form field with specified id|name|label|value
     * Example: When I fill in "username" with: "bwayne"
     * Example: And I fill in "bwayne" for "username"
     *
     * @When /^(?:|I )update react-select with "(?P<field>(?:[^"]|\\")*)" with "(?P<value>(?:[^"]|\\")*)"$/
     */
    public function updateReactSelectWith($field, $value)
    {
        $value = $this->fixStepArgument($value);
        $driver = $this->getSession()->getDriver();
        $FieldXPath = '//*[@id="'.$field.'"]';
        $driver->click($FieldXPath."//parent::div//parent::div//parent::div//parent::div//parent::div");
        $driver->setValue($FieldXPath,$value);
    }


    /**
     * Fills in form field with today's date
     * Example: When I fill in date "Start" with today
     *
     * @When /^(?:|I )fill in date "(?P<field>(?:[^"]|\\")*)" with today$/
     */
    public function fillTodayDateField($field)
    {
        $field = $this->fixStepArgument($field);
        $value = date("Y/m/d");
        $this->getSession()->getPage()->fillField($field, $value);
    }

}

