Feature: People Dashboard
  In order to see people demographic data at a glance
  As a User
  I am able to visit the people dashboard

  Scenario: Open the People Dashboard
    Given I am authenticated as "admin" using "changeme"
    And I am on "/PeopleDashboard.php"
    Then I should see "People Dashboard"
    And I should see "People Functions"
    And I should see "Reports"
    And I should see "Family Roles"
    And I should see "People Classification"
    And I should see "Gender Demographics"