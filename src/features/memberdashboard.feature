Feature: Members Dashboard
  In order to see membership data at a glance
  As a User
  I am able to visit the members dashboard

  Scenario: Login to a system
    Given I am on "/MembersDashboard.php"
    And I am authenticated as "admin" using "changeme"
    Then I should see "Members Dashboard"