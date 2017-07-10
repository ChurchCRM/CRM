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
    * Wait for AJAX https://gist.github.com/jmauerhan/f839926ea527ff5e74e7
    * 
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
    
    /**
     * @Given I wait for Ajax
     */
    public function iWaitForAjax()
    {
      $waitTime = 10000;
      try {
          //Wait for Angular
          $angularIsNotUndefined = $this->getSession()->evaluateScript("return (typeof angular != 'undefined')");
          if ($angularIsNotUndefined) {
              //If you run the below code on a page ending in #, the page reloads.
              if (substr($this->getSession()->getCurrentUrl(), -1) !== '#') {
                  $angular = 'angular.getTestability(document.body).whenStable(function() {
                  window.__testable = true;
              })';
                  $this->getSession()->evaluateScript($angular);
                  $this->getSession()->wait($waitTime, 'window.__testable == true');
              }

              /*
               * Angular JS AJAX can't be detected overall like in jQuery,
               * but we can check if any of the html elements are marked as showing up when ajax is running,
               * then wait for them to disappear.
               */
              $ajaxRunningXPath = "//*[@ng-if='ajax_running']";
              $this->waitForElementToDisappear($ajaxRunningXPath, $waitTime);
          }

          //Wait for jQuery
          if ($this->getSession()->evaluateScript("return (typeof jQuery != 'undefined')")) {
              $this->getSession()->wait($waitTime, '(0 === jQuery.active && 0 === jQuery(\':animated\').length)');
          }
      } catch (Exception $e) {
          var_dump($e->getMessage()); //Debug here.
      }
  }
  
  
    /**
    * Click some text  https://blog.mikepearce.net/2012/09/14/a-bit-of-behat-clicking-on-text/
    *
    * @When /^I click on the text "([^"]*)"$/
    */
    public function iClickOnTheText($text)
    {
        $session = $this->getSession();
        $element = $session->getPage()->find(
            'xpath',
            $session->getSelectorsHandler()->selectorToXpath('xpath', '*//*[text()="'. $text .'"]')
        );
        if (null === $element) {
            throw new \InvalidArgumentException(sprintf('Cannot find text: "%s"', $text));
        }
 
        $element->click();
 
    }
    
    /**
    * Click on Link Text https://stackoverflow.com/questions/26365722/behat-mink-phantomjs-not-following-anchor-element
    * @When /^(?:|I )click on "(?P<text>.+)" link$/
    */
    public function clickOnLink($text)
    {
        $element = $this->getSession()->getPage()->find('xpath', '//a[text() = "' . $text . '"]');
        $element->click();
    }
}
