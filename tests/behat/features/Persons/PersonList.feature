Feature: Person List
  In order to view all persons
  As a User
  I am able to open the Person Listing

  Scenario: Listing all persons
    Given I am authenticated as "admin" using "changeme"
    And I am on "/v2/people"
    Then I should see "Person Listing"
    And I should see "Admin, Church"
    And I should see "Barker, Bob"
    
  Scenario: Add and remove all persons to cart
    Given I am authenticated as "admin" using "changeme"
    And I am on "/v2/people"
    Then I should see "0"
    And I click the "#AddAllToCart" element
    And I wait for AJAX to finish
    Then I should not see "0"
    And I click the "#RemoveAllFromCart" element
    And I wait for AJAX to finish
    Then I should see "0" 

   