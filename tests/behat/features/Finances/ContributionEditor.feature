Feature: Contribution Editor
  In order to manage Contributions
  As a User
  I am able to visit the Contribution Editor

  Scenario: Open the Contribution page
    Given I am authenticated as "admin" using "changeme"
    And  I am on "/FindContributions.php"
    Then I should see "Contribution Listing"
    And I should see "Add New Contribution"

  Scenario: Add Payments
    Given I am authenticated as "admin" using "changeme"
    And I am on "/ContributionEditor.php"
    Then I should see "Contribution Details"
    And the "Save" button should be disabled
    
    
    And I fill in "Check #" with "867"
    And I fill in "Payment by" with "Check"
    And I fill in "Contributor" with "Smith Paul - 5572 Robinson Rd Santa Clarita, KY USA"
    And I press "Add New Split"
    And I wait for AJAX to finish

    And I fill in select2 input "AddFund" with "New" and select "New Building Fund"
    And I fill in "AddAmount" with "1000.00"
    And I press "Submit"
    And I wait for AJAX to finish
    Then I should see "New Building Fund"
    And I should see "867"
    And I should see "Check"
