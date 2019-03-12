Feature: Calendar
  In order to manage calendar events
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
    And I wait for AJAX to finish
    Then I should see "New Calendar"
    And I fill in "calendarName" with "Test Calendar"
    And I fill in "ForegroundColor" with "000000"
    And I fill in "BackgroundColor" with "FFFFFF"
    And I press "Save"
    And I wait for AJAX to finish
    Then I should see "Test Calendar"
      
  Scenario: Create a new event;
    Given I am authenticated as "admin" using "changeme"
    And  I am on "/v2/calendar"
    And I wait for AJAX to finish
    Then I click on "#calendar > div.fc-view-container > div > table > tbody > tr > td > div > div > div:nth-child(3) > div.fc-content-skeleton > table > tbody > tr:nth-child(1) > td:nth-child(3)"
    And I wait for AJAX to finish
    Then I should see "Save"
    And I fill in "Title" with "Selenium Test Event"
    And I update react-select with "EventType" with "Church Service"
    And I fill in "Desc" with "Test Description"
    And I fill in date "Start" with today
    And I fill in date "End" with today
    And I update react-select with "PinnedCalendars" with "Public Calendar"
    And I fill in "Text" with "Test Text"
    Then I press "Save"
    And I wait for the calendar to load
    Then I should see "Selenium Test Event"