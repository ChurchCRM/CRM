Feature: Assign Person to Group
  In order to Assign a person to a group
  As a User
  I am able to follow the "add to group" steps from the PersonView page

  Scenario: Open the Person View Page
    Given I am authenticated as "admin" using "changeme"
    And  I am on "PersonView.php?PersonID=1"
    And I click on "Assigned Groups" link
    Then I should see "Assign New Group"
    And I click on "Assign New Group" link
    Then I should see "Select Group and Role"
    And I fill in "targetGroupSelection" with "BehatGroup"
    And I fill in "targetRoleSelection" with "BehatGroup"
    And I press "OK"
    And I click on  "Assigned Groups" link
    Then I should see "BehatGroup" 
    And I should see "Member"