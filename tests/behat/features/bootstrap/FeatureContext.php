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
      $this->fillField('UserBox', $username);
      $this->fillField('PasswordBox', $password);
      $this->pressButton('Login');
    }
}
