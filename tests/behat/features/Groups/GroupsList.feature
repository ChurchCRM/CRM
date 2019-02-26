Feature: Groups List
  In order to manage groups
  As a User
  I am able to visit the group listing

  Scenario: Add a Group
    Given I am authenticated as "admin" using "changeme"
    And I am on "/GroupList.php"
    And I wait for AJAX to finish
    And I fill in "groupName" with "Test Group"
    And I press "addNewGroup"
    And I wait for AJAX to finish
    Then I should see "Test Group"

  Scenario: Add a member to a group
    Given I am authenticated as "admin" using "changeme"
    And I am on "GroupView.php?GroupID=1"
    And I wait for AJAX to finish
    And I fill in select2 input "addGroupMember" with "admin" and select "Church Admin"
    And I wait for AJAX to finish
    Then I should see "Select Role"
    And I press "OK"
    And I wait for AJAX to finish
    Then I should see "Church Admin"

  Scenario: Add group member to cart
    Given I am authenticated as "admin" using "changeme"
    And I am on "GroupView.php?GroupID=1"
    And I wait for AJAX to finish
    And I click the ".groupRow" element
    And I wait for AJAX to finish
    Then I should see "Add (1) Members to Cart"
    And I click the "#addSelectedToCart" element
    And I wait for AJAX to finish
    And I reload the page
    Then I should see "1" in the "#iconCount" element

  Scenario: Copy a member to a different group
    Given I am authenticated as "admin" using "changeme"
    And I am on "GroupView.php?GroupID=1"
    And I wait for AJAX to finish
    And I click the ".groupRow" element
    And I wait for AJAX to finish
    Then I should see "Add (1) Members to Cart"
    And I click the "#buttonDropdown" element
    And I click the "#addSelectedToGroup" element
    And I wait for AJAX to finish
    Then I should see "Select Group and Role"
    And I fill in select2 input "targetGroupSelection" with "Class 1-3" and select "Class 1-3"
    And I wait for AJAX to finish
    And I fill in select2 input "targetRoleSelection" with "Student" and select "Student"
    And I press "OK"

  Scenario: Move a member to a different group
    Given I am authenticated as "admin" using "changeme"
    And I am on "GroupView.php?GroupID=1"
    And I wait for AJAX to finish
    And I click the ".groupRow" element
    And I wait for AJAX to finish
    Then I should see "Add (1) Members to Cart"
    And I click the "#buttonDropdown" element
    And I click the "#moveSelectedToGroup" element
    And I wait for AJAX to finish
    Then I should see "Select Group and Role"
    And I fill in select2 input "targetGroupSelection" with "Class 4-5" and select "Class 4-5"
    And I wait for AJAX to finish
    And I fill in select2 input "targetRoleSelection" with "Student" and select "Student"
    And I press "OK"
