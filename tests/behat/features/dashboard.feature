Feature: Dashboard
  In order to manage the system
  As a User
  I am able to visit the dashboard

  Scenario: Dashboard at Menu.php
    Given I am authenticated as "admin" using "changeme"
    And  I am on "/Menu.php"
    And I wait for AJAX to finish
    Then I should see "92" in the "#peopleStatsDashboard" element
    And I should see "18" in the "#familyCountDashboard" element
    And I should see "1931 Edwards Rd"

  Scenario: Dashboard at menu
    Given I am authenticated as "admin" using "changeme"
    And  I am on "/menu"
    And I wait for AJAX to finish
    Then I should see "92" in the "#peopleStatsDashboard" element
    And I should see "18" in the "#familyCountDashboard" element
    And I should see "1931 Edwards Rd"