Feature: Family View
  In order to view a family's details
  As a User
  I am able to load the family view page for the family

  Scenario: Family Not Found
    Given I am authenticated as "admin" using "changeme"
    And I am on "/v2/family/not-found?id=9999"
    Then I should see "Oops! FAMILY 9999 Not Found"

  Scenario: Family View
    Given I am authenticated as "admin" using "changeme"
    And I am on "/v2/family/20/view"
    Then I should see "Black - Family"
    And I wait for AJAX to finish
    And I should see "New Building Fund"   

  Scenario: Family View 2
    Given I am authenticated as "admin" using "changeme"
    And I am on "/FamilyView.php?FamilyID=7"
    Then I should see "Stewart - Family"
    And I should see "Scouts"
