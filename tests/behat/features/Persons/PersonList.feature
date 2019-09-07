Feature: Person List
  In order to view all persons
  As a User
  I am able to open the Person Listing

  Scenario: Listing all persons
    Given I am authenticated as "admin" using "changeme"
    And I am on "/v2/people"
    Then I should see "Person Listing"
    And I should see "Admin"
    And I should see "Church"
    And I should see "Barker"
    And I should see "Bob"

  Scenario: Add and remove all persons to cart
    Given I am authenticated as "admin" using "changeme"
    And I am on "/v2/people?Gender=0"
    And I wait for AJAX to finish
    Then I should see "Showing 1 to 2 of 2 entries (filtered from 96 total entries)"
    And I should see "Admin"
    And I should see "Church"
    And I should see "Kennedy"
    And I should see "Judith"
