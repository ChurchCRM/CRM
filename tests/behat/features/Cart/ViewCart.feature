Feature: Cart
  In order to interact with groups of people
  As a User
  I am able to interact with a ephemeral cart of people

  Scenario: Cart Add and Remove
    Given I am authenticated as "admin" using "changeme"
    And I am on "/v2/cart"
    And I wait for AJAX to finish
    Then I should see "You have no items in your cart"
    And I am on "PersonView.php?PersonID=1"
    And I wait for AJAX to finish
    And I click "#AddPersonToCart"
    And I wait for AJAX to finish
    And I am on "/v2/cart"
    Then I should see "Cart Functions"
    And I should see "Church Admin"
    And I click "#emptyCart"
    And I wait for AJAX to finish
    Then I should see "You have no items in your cart"