Feature: Dropdown User Menu
  In order to access tailored features
  As a User
  I am able to visit the dropdown user menu from the header

  Scenario: Open the Dropdown User Menu
    Given I am authenticated as "admin" using "changeme"
    And I am on "/Menu.php"
	Then I should see "Welcome to"
	And I click the "#dropdown-toggle" element
    Then I should see "Profile"
    And I should see "Sign Out"
	And I click "Profile"
	And I am on "/PersonView.php?PersonID=1"
	And I should see "Change Settings"
	And I should see "Change Password"