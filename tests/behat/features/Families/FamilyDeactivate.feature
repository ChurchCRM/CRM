Feature: FamilyDeactivate
  In order to deactivate / reactivate a family
  As a User
  I am able to toggle the active state from the v2 family page

  Scenario: Entering a new Family
    Given I am authenticated as "admin" using "changeme"
    And I am on "/v2/family/21/view"
    Then I should see "Smith - Family"
    And I should not see "This Family is Deactivated"
    Then I press "Deactivate this Family"
    And I wait for AJAX to finish
    And I press "OK"
    And I wait for AJAX to finish
    Then I should see "This Family is Deactivated"
    Then I press "Activate this Family"
    And I wait for AJAX to finish
    And I press "OK"
    And I wait for AJAX to finish
    Then I should not see "This Family is Deactivated"