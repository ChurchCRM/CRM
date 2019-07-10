Feature: Group Assignment Helper
  In order to ???
  As a User
  I am able to visit the Group Assignment Helper

  Scenario: Open the Group Assignment Helper
    Given I am authenticated as "admin" using "changeme"
    And  I am on "/v2/people?groupassign"
    Then I should see "Group Assignment Helper"
    And I should see "Admin, Church"