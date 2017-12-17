Feature: Event Types
  In order to manage church event types
  As a User
  I am able to edit Church Event Types

  Scenario: Add Event
    Given I am authenticated as "admin" using "changeme"
    And I am on "/EventNames.php"
    Then I should see "Add Event Type"