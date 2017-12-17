Feature: Events Add
  In order to add church Events
  As a User
  I am able to open the Church Event Editor

  Scenario: Add Event
    Given I am authenticated as "admin" using "changeme"
    And I am on "/EventEditor.php"
    Then I should see "Create a new Event"