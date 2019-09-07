Feature: Family View
  In order to view a family's details
  As a User
  I am able to load the famiy view page for the family

  Scenario: Person Not Found
    Given I am authenticated as "admin" using "changeme"
    And I am on "/v2/family/not-found?id=9999"
    Then Oops! FAMILY 9999 Not Found"


   