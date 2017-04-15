Feature: AddPerson
  In order to add a person
  As a User
  I am able to enter data into the Person Editor

  Scenario: Entering a new Person
    Given I am authenticated as "admin" using "changeme"
    And I am on "/PersonEditor.php"
    Then I should see "Personal Info"
    And I fill in "Gender" with "1"
    And I fill in "First Name" with "Bob"
    And I fill in "Last Name" with "Barker"
    And I fill in "FamilyRole" with "1"
    And I fill in "Family" with "-1"
    And I fill in "Classification" with "1"
    And I press "PersonSubmit"
    Then I should see "Person Profile"
    Then I should see "Bob Barker"
    Then I should see "About Me"
    Then I should see "Timeline"