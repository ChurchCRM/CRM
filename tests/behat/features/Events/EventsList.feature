Feature: Events List
  In order to view church Events
  As a User
  I am able to open the Events Listing

  Scenario: Listing Events
    Given I am authenticated as "admin" using "changeme"
    And I am on "/ListEvents.php"
    Then I should see "Listing All Church Events"
    And I should see "Select Event Types To Display"
    And I should see "Display Events in Year"
    And I should see "Add New Event"