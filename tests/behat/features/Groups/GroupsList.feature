Feature: Groups List
  In order to manage groups
  As a User
  I am able to visit the group listing

  Scenario: Open the Group Listing
    Given I am authenticated as "admin" using "changeme"
    And  I am on "/GroupList.php"
    Then I should see "Add New Group"