Feature: Groups List
  In order to manage groups
  As a User
  I am able to visit the group listing

  Scenario: Open the Group Listing
    Given I am authenticated as "admin" using "changeme"
    And  I am on "/GroupList.php"
    Then I should see "Add New Group"

  Scenario: Create a Group
    Given I am authenticated as "admin" using "changeme"
    And  I am on "/GroupList.php"
    And I fill in "groupName" with "BehatGroup"
    And I press "Add New Group"
    And I wait for AJAX
    Then I should see "BehatGroup"