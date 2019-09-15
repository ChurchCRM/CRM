Feature: Deposit Editor
  In order to manage payments on a deposit
  As a User
  I am able to visit the Deposit Slip Editor

  Scenario: Open the Deposits page
    Given I am authenticated as "admin" using "changeme"
    And  I am on "/DepositSlipEditor.php?DepositSlipID=5"
    Then I should see "Bank Deposit Slip Number: 5"
    And I should see "Payments on this deposit slip"

  Scenario: Add Payments
    Given I am authenticated as "admin" using "changeme"
    And I am on "/DepositSlipEditor.php?DepositSlipID=5"
    And I press "Add Contributions"
    And I wait for AJAX to finish
    Then I should see "Contribution Listing"
    And I should see "Contributions"
    Then I press "Add to Deposit (#5)"
    And I wait for AJAX to finish
    Then I should see "Paul"
    And I should see "Smith"
    And I shoould see "1000.00"