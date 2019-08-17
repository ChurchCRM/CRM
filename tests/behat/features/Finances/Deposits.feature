Feature: Deposit Slip Listing
  In order to manage finances
  As a User
  I am able to visit the Deposits listing

  Scenario: Open the Deposits page
    Given I am authenticated as "admin" using "changeme"
    And  I am on "/FindDepositSlip.php"
    Then I should see "Add New Deposit"
    And I should see "Deposits"

  Scenario: Create a new Deposit
    Given I am authenticated as "admin" using "changeme"
    And  I am on "/FindDepositSlip.php"
    And I press "Add New Deposit"
    And I wait for AJAX to finish
    Then I should see "You are about to add a new deposit without a comment"
    And I press "Cancel"
    And I fill in "depositComment" with "Selenium Test Deposit"
    And I press "Add New Deposit"
    And I wait for AJAX to finish
    Then I should see "Selenium Test Deposit" in the "#depositsTable" element

# TODO: Test deleting individual deposits
# This is currently hard because
# there's not a good way for tests to identify
# individual deposits in the table.
# Scenario: Delete a deposit
#    Given I am authenticated as "admin" using "changeme"
#    And  I am on "/FindDepositSlip.php"