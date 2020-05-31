Feature: GeoPage
  In order to determine which people live near eachother
  As a User
  I am able to use the Family Geographic Utilities

  Scenario: Find Demo Family Neighbors and Test Cart
    Given I am authenticated as "admin" using "changeme"
    And I am on "/GeoPage.php"
    Then I should see "Family Geographic Utilities"
    And I fill in select2 input "Family" with "Berry" and select "Berry - 1931 Edwards Rd Riverside, PA United States"
    And I fill in "MaxDistance" with "500"
    And I wait for AJAX to finish
    And I check "Regular Attender"
    And I press "Show Neighbors"
    Then I should see "Rafael Dixon"
    Then I should see "0" in the "#iconCount" element
    And I click "#AddAllToCart"
    And I wait for AJAX to finish
    Then I should not see "0" in the "#iconCount" element
    And I click the "#RemoveAllFromCart" element
    And I wait for AJAX to finish
    Then I should see "0" in the "#iconCount" element

    

