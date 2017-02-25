Feature: AddPerson
  In order to add a person
  As a User
  I am able to enter data into the Person Editor

  Scenario: Entering a new Person
    Given I am authenticated as "admin" using "changeme"
    And I am on "/PersonEditor.php"
    Then I should see "Personal Info"