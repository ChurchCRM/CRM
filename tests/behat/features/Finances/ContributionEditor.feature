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
    And I fill in select2 input "#ContributorName" with "Smith" and select "Smith Paul - 5572 Robinson Rd Santa Clarita, KY USA"
    #And I fill in select2 input "contribType" with "Ch" and select "Check"
    #And I fill in "contribCheck" with "867"
    #And I press "Add New Split"
    #And I wait for AJAX to finish
    #And I fill in select2 input "AddFund" with "New" and select "New Building Fund"
    #And I fill in "AddAmount" with "1000"
    #And I press "submitContrib"
    #And I wait for AJAX to finish
    #Then I should see "Showing 1 to 1 of 1 entries"
