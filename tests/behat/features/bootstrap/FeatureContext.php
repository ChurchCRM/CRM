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
     $this->getSession()->wait(3000, '(typeof(jQuery)=="undefined" || (0 === jQuery.active && 0 === jQuery(\':animated\').length))');
   }
}

