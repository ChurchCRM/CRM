Feature: FamilyList
  In order to view all Families
  As a User
  I am able to open the Family Listing

  Scenario: Listing Families
    Given I am authenticated as "admin" using "changeme"
    And I am on "/v2/family"
    Then I should see "Active Family List"