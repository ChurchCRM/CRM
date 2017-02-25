Feature: Calendar
  In order to see calendar events
  As a User
  I am able to visit the calendar

  Scenario: Open the calendar
    Given I am authenticated as "admin" using "changeme"
    And  I am on "/calendar.php"
    Then I should see "Church Calendar"