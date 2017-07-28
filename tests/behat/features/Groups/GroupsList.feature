Feature: Groups List
  In order to manage groups
  As a User
  I am able to visit the group listing

  Scenario: Add a Group
    Given I am authenticated as "admin" using "changeme"
    And I am on "/GroupList.php"
    And I fill in "groupName" with "Test Group"
    And I press "addNewGroup"
    And I wait for AJAX to finish
    Then I should see "Test Group"
    
  Scenario: Add a member to a group
    Given I am authenticated as "admin" using "changeme"
    And I am on "GroupView.php?GroupID=1"
    And I fill in select2 input "addGroupMember" with "admin" and select "Church Admin"
    And I wait for AJAX to finish
    Then I should see "Select Role"
    And I press "OK"
    And I wait for AJAX to finish
    Then I should see "Church Admin"