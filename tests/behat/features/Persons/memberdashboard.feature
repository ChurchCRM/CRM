Feature: Members Dashboard
  In order to see membership data at a glance
  As a User
  I am able to visit the members dashboard

  Scenario: Open the Members Dashboard
    Given I am authenticated as "admin" using "changeme"
    And I am on "/MembersDashboard.php"
    Then I should see "Members Dashboard"
    And I should see "Members Functions"
    And I should see "Reports"
    And I should see "Family Roles"
    And I should see "People Classification"
    And I should see "Gender Demographics"