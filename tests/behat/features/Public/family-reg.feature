Feature: Family Self-Registration
  In order to track members, we allow families to self-reg
  As a unauthenticated user
  I am able to visit the self-reg page and complete reg, and see the family in the system after.

  Scenario: Family is able to see and self-reg.
    Given I am on "/external/register/"
    Then I should see "Register your family"
    And I should see "How many people are in your family"
    And I fill in "familyName" with "Self-Reg-Family"
    And I fill in "familyAddress1" with "4222 Clinton Way"
    And I fill in "familyCity" with "Los Angelas"
    And I fill in "familyState" with "CA"
    And I fill in "familyZip" with "98121"
    And I fill in "familyHomePhone" with "555-555-5555"
    And I fill in "familyCount" with "2"
#    And I press "Next"
#    And I should see "Family Member #2"
#    And I should see "Register Self-Reg-Family Family Members"
#    And I fill in "memberFirstName-1" with "Mark"
#    And I fill in "memberLastName-1" with "Hanna"
#    And I fill in "memberEmail-1" with "email@test.com"
#    And I fill in "memberPhone-1" with "555-999-9129"
#    And I fill in "memberFirstName-2" with "Sarah"
#    And I fill in "memberLastName-2" with "Hanna"
#    And I fill in "memberEmail-2" with "email2@test.com"
#    And I fill in "memberPhone-2" with "555-999-0000"
#    And I fill in "memberPhoneType-2" with "work"
#    And I press "familyMemberSubmit"
