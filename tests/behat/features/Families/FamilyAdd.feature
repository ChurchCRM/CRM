Feature: FamilyAdd
  In order to add a family
  As a User
  I am able to enter data into the Family Editor

  Scenario: Entering a new Family
    Given I am authenticated as "admin" using "changeme"
    And I am on "/FamilyEditor.php"
    Then I should see "Family Info"