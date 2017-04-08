Feature: Person List
  In order to view all persons
  As a User
  I am able to open the Person Listing

  Scenario: Listing all persons
    Given I am authenticated as "admin" using "changeme"
    And I am on "/SelectList.php?mode=person"
    Then I should see "Person Listing"
    And I should see "Admin, Church"
    And I should see "Barker, Bob"


   