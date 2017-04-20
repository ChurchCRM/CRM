Feature: FamilyList
  In order to view all Families
  As a User
  I am able to open the Family Listing

  Scenario: Listing Families
    Given I am authenticated as "admin" using "changeme"
    And I am on "/FamilyList.php"
    Then I should see "Active Family List"