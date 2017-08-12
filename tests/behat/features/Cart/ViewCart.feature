Feature: Cart
  In order to interact with groups of people
  As a User
  I am able to interact with a ephemeral cart of people

  Scenario: Cart Add and Remove
    Given I am authenticated as "admin" using "changeme"
    And I am on "CartView.php"
    Then I should see "You have no items in your cart"
    And I am on "PersonView.php?PersonID=1"
    And I follow "Add to Cart"
    And I am on "CartView.php"
    Then I should see "Cart Functions"
    And I should see "Church Admin"
    And I am on "CartView.php?Action=EmptyCart"
    Then I should see "You have no items in your cart"