Feature: Person View
  In order to view a person's details
  As a User
  I am able to load the person view page for the person

 

  Scenario: Add Person To Group
    Given I am authenticated as "admin" using "changeme"
    And I am on "/PersonView.php?PersonID=1"
    And I wait for AJAX to finish
    And I follow "Assigned Groups"
    And I wait for AJAX to finish
    And I click the "#addGroup" element
    And I wait for AJAX to finish
    Then I should see "Select Group and Role"
    And I fill in select2 input "targetGroupSelection" with "Class 1-3" and select "Class 1-3"
    And I wait for AJAX to finish
    And I fill in select2 input "targetRoleSelection" with "Student" and select "Student"
    And I press "OK"


   