# Tests

## Page Tests
ChurchCRM uses [Behat](http://behat.org/en/latest/quick_start.html) for page load testing [Behavior Driven Development](https://en.wikipedia.org/wiki/Behavior-driven_development).

### Running Behat Page Tests

1.  While SSH'd into the vagrant development box, run the following commands:
```
cd /vagrant
npm test
```
2. You should see the result of all individual tests.


### Writing Behat Page Tests

Behat page tests use the [gherkin syntax](http://docs.behat.org/en/v2.5/guides/1.gherkin.html).  
Behat uses tests written with this syntax to execute requests and validate responses against the defined pages.


All tests should follow this outline:
```
Feature: Calendar
  In order to see calendar events
  As a User
  I am able to visit the calendar

  Scenario: Open the calendar
    Given I am authenticated as "admin" using "changeme"
    And  I am on "/calendar.php"
    Then I should see "Church Calendar"
```

### Useful reference tests

* Supplying input values, Navigating Pages: [FamilyAdd.Feature](https://github.com/ChurchCRM/CRM/blob/master/tests/behat/features/Families/FamilyAdd.feature)
* Defining new gherkin verbs: ```iAmAuthenticatedAs```: [FeatureContext.php](https://github.com/ChurchCRM/CRM/blob/master/tests/behat/features/bootstrap/FeatureContext.php#L27)


## Unit Tests
Unit testing is not yet fully implemented.