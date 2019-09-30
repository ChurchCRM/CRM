Feature: FamilyAdd
  In order to add a family
  As a User
  I am able to enter data into the Family Editor

  Scenario: Entering a new Family
    Given I am authenticated as "admin" using "changeme"
    And I am on "/FamilyEditor.php"
    Then I should see "Family Info"
    And I fill in "FamilyName" with "Troy"
    And I fill in "Address1" with "4222 Clinton Way"
    And I fill in "City" with "Los Angelas"
    And I fill in "State" with "CA"
    And I fill in "FirstName1" with "Mike" 
    And I fill in "FirstName2" with "Carol" 
    And I fill in "FirstName3" with "Alice" 
    And I fill in "FirstName4" with "Greg" 
    And I fill in "FirstName5" with "Marcia"
    And I fill in "FirstName6" with "Peter" 
    And I fill in "Classification1" with "1" 
    And I fill in "Classification2" with "1" 
    And I fill in "Classification3" with "1" 
    And I fill in "Classification4" with "1" 
    And I fill in "Classification5" with "1" 
    And I fill in "Classification6" with "1" 
    And I press "FamilySubmit"
    Then I should see "Troy - Family"
    And I should see "Mike Troy"
    And I should see "Carol Troy"
    And I should see "Alice Troy"
    And I should see "Greg Troy"
    And I should see "Marcia Troy"
    And I should see "Peter Troy"
    And I should see "4222 Clinton Way Los Angelas, CA"