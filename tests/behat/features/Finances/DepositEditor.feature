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
    And  I am on "/DepositSlipEditor.php?DepositSlipID=5"
    And I press "Add Payment"
    Then I should see "Payment Editor"
    And I should see "Payment Details"
    And I fill in select2 input "FamilyName" with "Berry" and select "Berry: Salvador - 1931 Edwards Rd Riverside, PA United States"
    And I select "Check" from "Method"
    And I fill in "CheckNo" with "867"
    And I fill in "1_Amount" with "1000"
    And I press "Save"
    # This scope of validation could be improved
    # Instead of checking the whole table for each component of this payments
    # We should try to find _the payment_ and make sure all values match
    And I wait for AJAX to finish
    Then I should see "Berry: Salvador - 1931 Edwards Rd Riverside, PA United States" in the "#paymentsTable" element
    And I should see "1000.00" in the "#paymentsTable" element
    And I should see "867" in the "#paymentsTable" element
    And I should see "CHECK" in the "#paymentsTable" element