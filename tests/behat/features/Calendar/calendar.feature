Feature: Calendar
  In order to see calendar events
  As a User
  I am able to visit the calendar

  Scenario: Open the calendar
    Given I am authenticated as "admin" using "changeme"
    And  I am on "/v2/calendar"
    Then I should see "Calendar"

  Scenario: Create a new calendar
    Given I am authenticated as "admin" using "changeme"
    And  I am on "/v2/calendar"
    And I click the "#newCalendarButton" element
    Then I should see "New Calendar"
    And I fill in "calendarName" with "Test Calendar"
    And I fill in "ForegroundColor" with "000000"
    And I fill in "BackgroundColor" with "FFFFFF"
    And I press "Save"
    And I wait for AJAX to finish
    Then I should see "Test Calendar"