/// <reference types="cypress" />

/**
 * UI Test for Issue #7257: Payment Submission Error 500
 *
 * This test validates that users can successfully add payments for donations
 * without encountering HTTP 500 errors caused by type mismatches.
 *
 * Bug Context:
 * - Issue #7257: Users reported Error 500 when adding payments
 * - Root Cause: Type mismatch in FinancialService.php (array vs object)
 * - Fix: PR #7421 - Changed type hints from array to object
 *
 * Test Strategy:
 * - Test CASH payment (simplest case)
 * - Test CHECK payment (validates validateChecks method)
 * - Test split funds (validates FundSplit object handling)
 * - Verify no type errors or 500 responses
 *
 * Note on FamilyID: The pledge editor requires a family to be selected via a
 * TomSelect widget.  In CI this widget has no pre-populated options because
 * the deposit-slip "Add Payment" button does not pass a familyId URL param.
 * We work around this by directly setting the hidden #FamilyID input to "1"
 * (family 1 always exists in the test environment) before submitting.
 *
 * Note on URL assertion: The pledge-editor URL contains
 * "DepositSlipEditor.php" in its encoded linkBack query parameter.  A plain
 * cy.url() "contain" check would therefore pass even when the page has NOT
 * yet navigated back.  We use cy.location('pathname') to check the pathname
 * only, which correctly distinguishes the two pages.
 *
 * Note on cy.intercept prefix: ChurchCRM runs under both root ('/') and a
 * subdirectory ('/churchcrm/') in CI.  Always prefix cy.intercept paths
 * with the double-glob ('**') so the intercept matches regardless of
 * installation path (e.g. '**' + '/api/payments/pledges').
 */

describe("Finance Payment Submission - Issue #7257 Regression Test", () => {
    beforeEach(() => {
        cy.setupAdminSession();
    });

    it("Add CASH payment", () => {
        const uniqueSeed = Date.now().toString();
        const depositComment = "Test Deposit Cash " + uniqueSeed;
        const paymentAmount = (Math.floor(Math.random() * 900) + 100).toString(); // Random amount 100-999

        // Register intercept before any navigation that could trigger the request
        cy.intercept("POST", "**/api/payments/pledges").as("submitPayment");

        // Create a new deposit
        cy.visit("/finance/deposit/search");
        cy.get("[data-bs-target='#newDepositModal']").click();
        cy.contains("Add New Deposit");
        cy.get("#depositComment").type(depositComment);
        cy.get("#addNewDeposit").click();
        cy.location("pathname").should("include", "DepositSlipEditor.php");

        // Add a cash payment
        cy.get(".btn-success").click();
        cy.url().should("contain", "/finance/pledge/new");

        // Wait for form to be ready and select CASH payment method
        cy.get(".fund-amount").first().should("be.visible");
        cy.get("#Method").select("CASH");

        // Provide a family (required by the new pledge editor form)
        cy.get("#FamilyID").invoke("val", "1");

        cy.get(".fund-select").first().select(1);

        // Fill payment form - this triggers the POST **/api/payments/pledges endpoint
        cy.get(".fund-amount").first().clear().type(paymentAmount);

        // Submit the payment - this is where issue #7257 occurred
        cy.get("#savePledgeBtn").click();

        // Wait for the API call to complete, then verify redirect and table
        cy.wait("@submitPayment").its("response.statusCode").should("eq", 200);
        cy.location("pathname").should("include", "DepositSlipEditor.php");
        cy.get("#paymentsTable").should("be.visible");
        cy.get("#paymentsTable").contains(paymentAmount).should("be.visible");
        cy.get("#paymentsTable").contains("CASH").should("be.visible");
    });

    it("Add CHECK payment with check number - validates validateChecks method", () => {
        const uniqueSeed = Date.now().toString();
        const depositComment = "Test Deposit Check " + uniqueSeed;
        const paymentAmount = (Math.floor(Math.random() * 900) + 100).toString(); // Random amount 100-999
        // Use a random 8-digit numeric value to avoid duplicate-check-number failures
        // when two parallel CI runs occur within the same second.
        const checkNumber = (Math.floor(Math.random() * 90000000) + 10000000).toString();

        // Register intercept before any navigation that could trigger the request
        cy.intercept("POST", "**/api/payments/pledges").as("submitCheckPayment");

        // Create a new deposit
        cy.visit("/finance/deposit/search");
        cy.get("[data-bs-target='#newDepositModal']").click();
        cy.get("#depositComment").type(depositComment);
        cy.get("#addNewDeposit").click();
        cy.location("pathname").should("include", "DepositSlipEditor.php");

        // Add a check payment
        cy.get(".btn-success").click();
        cy.url().should("contain", "/finance/pledge/new");

        // Wait for form to be ready
        cy.get(".fund-amount").first().should("be.visible");

        // Provide a family (required by the new pledge editor form)
        cy.get("#FamilyID").invoke("val", "1");

        cy.get(".fund-select").first().select(1);
        cy.get("#CheckNo").type(checkNumber); // Fill check number first

        // Fill payment form
        cy.get(".fund-amount").first().clear().type(paymentAmount);

        // Submit and verify no errors
        cy.get("#savePledgeBtn").click();

        // Wait for the API call to complete, then verify redirect and table
        cy.wait("@submitCheckPayment").its("response.statusCode").should("eq", 200);
        cy.location("pathname").should("include", "DepositSlipEditor.php");
        cy.get("#paymentsTable").should("be.visible");
        cy.get("#paymentsTable").contains(paymentAmount).should("be.visible");
        cy.get("#paymentsTable").contains(checkNumber).should("be.visible");
    });

    it("Add payment with multiple funds - validates FundSplit object handling", () => {
        const uniqueSeed = Date.now().toString();
        const depositComment = "Test Deposit Split " + uniqueSeed;
        // Use a random 8-digit numeric value to avoid duplicate-check-number failures
        // when two parallel CI runs occur within the same second.
        const checkNumber = (Math.floor(Math.random() * 90000000) + 10000000).toString();
        const fund1Amount = (Math.floor(Math.random() * 400) + 50).toString(); // Random 50-449
        const fund2Amount = (Math.floor(Math.random() * 400) + 50).toString(); // Random 50-449
        const totalAmount = (parseInt(fund1Amount) + parseInt(fund2Amount)).toString();

        // Register intercept before any navigation that could trigger the request
        cy.intercept("POST", "**/api/payments/pledges").as("submitSplitPayment");

        // Create a new deposit
        cy.visit("/finance/deposit/search");
        cy.get("[data-bs-target='#newDepositModal']").click();
        cy.get("#depositComment").type(depositComment);
        cy.get("#addNewDeposit").click();
        cy.location("pathname").should("include", "DepositSlipEditor.php");

        // Add a payment with fund split
        cy.get(".btn-success").click();
        cy.url().should("contain", "/finance/pledge/new");

        // Wait for form to be ready
        cy.get(".fund-amount").first().should("be.visible");

        // Provide a family (required by the new pledge editor form)
        cy.get("#FamilyID").invoke("val", "1");

        cy.get("#CheckNo").type(checkNumber); // Fill check number first

        // Fill payment form with multiple funds
        cy.get(".fund-select").eq(0).select(1);
        cy.get(".fund-amount").eq(0).clear().type(fund1Amount);
        cy.get("#addFundRow").click();
        cy.get(".fund-select").eq(1).select(2);
        cy.get(".fund-amount").eq(1).clear().type(fund2Amount);

        // Submit and verify
        cy.get("#savePledgeBtn").click();

        // Wait for the API call to complete, then verify redirect and table
        cy.wait("@submitSplitPayment").its("response.statusCode").should("eq", 200);
        cy.location("pathname").should("include", "DepositSlipEditor.php");
        cy.get("#paymentsTable").should("be.visible");
        cy.get("#paymentsTable").contains(totalAmount).should("be.visible");
        cy.get("#paymentsTable").contains(checkNumber).should("be.visible");

        // Close the deposit slip
        cy.get("#Closed").check();
        cy.get("button[name='DepositSlipSubmit']").click();

        // Verify we're still on the deposit slip editor page
        cy.location("pathname").should("include", "DepositSlipEditor.php");
        cy.get("#Closed").should("be.checked");
    });
});
