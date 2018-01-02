Feature: AddPerson
  In order to add a person
  As a User
  I am able to enter data into the Person Editor

  Scenario: Entering a new Person With Various Birthdate Requirements
    Given I am authenticated as "admin" using "changeme"
    And I am on "/PersonEditor.php"
    Then I should see "Personal Info"
    And I fill in "Gender" with "1"
    And I fill in "First Name" with "Bob"
    And I fill in "Last Name" with "Barker"
    And I fill in "FamilyRole" with "1"
    And I fill in "Family" with "-1"
    And I fill in "Classification" with "1"
    And I fill in "Facebook" with "4"
    And I fill in "Twitter" with "test"
    And I fill in "LinkedIn" with "test"

    And I click the "#PersonSaveButton" element
    Then I should see "Person Profile"
    Then I should see "Bob Barker"
    Then I should see "About Me"
    Then I should see "Timeline"
    Then I should see "Facebook"
    Then I should see "Twitter"
    Then I should see "LinkedIn"
    And I should not see "Birthday"

    Then I click the "#EditPerson" element
    And I fill in "BirthMonth" with "03"
    And I fill in "BirthDay" with "04"
    And I click the "#PersonSaveButton" element
    Then I should see "Birth Date: 03/04"

    Then I click the "#EditPerson" element
    And I fill in "BirthYear" with "1992"
    And I click the "#PersonSaveButton" element
    Then I should see "Birth Date: 03/04/1992"
    And I should see "yrs old"

    Then I click the "#EditPerson" element
    And I fill in "HideAge" with "checked"
    And I click the "#PersonSaveButton" element
    Then I should see "Birth Date: 03/04"
    And I should not see "yrs old"
