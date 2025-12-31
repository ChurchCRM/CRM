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
 */

describe("Finance Payment Submission - Issue #7257 Regression Test", () => {
    beforeEach(() => {
        cy.setupAdminSession();
    });

    it("Add CASH payment", () => {
        const uniqueSeed = Date.now().toString();
        const depositComment = "Test Deposit Cash " + uniqueSeed;
        const paymentAmount = (Math.floor(Math.random() * 900) + 100).toString(); // Random amount 100-999

        // Create a new deposit
        cy.visit("FindDepositSlip.php");
        cy.contains("Add New Deposit");
        cy.get("#depositComment").type(depositComment);
        cy.get("#addNewDeposit").click();
        cy.url().should("contain", "DepositSlipEditor.php");

        // Add a cash payment
        cy.get(".btn-success").click();
        cy.url().should("contain", "PledgeEditor.php");

        // Wait for form to be ready and select CASH payment method
        cy.get("#1_Amount").should("be.visible");
        cy.get("#Method").select("CASH");
        
        // Fill payment form - this triggers the POST /api/payments endpoint
        cy.intercept('POST', '/api/payments').as('submitPayment');
        cy.get("#1_Amount").clear().type(paymentAmount);

        // Submit the payment - this is where issue #7257 occurred
        cy.get("#saveBtn").click();

        // Verify payment appears in deposit slip with correct amount
        cy.url().should("contain", "DepositSlipEditor.php");
        cy.get("#paymentsTable").should("be.visible");
        cy.get("#paymentsTable").contains(paymentAmount).should("be.visible");
        cy.get("#paymentsTable").contains("CASH").should("be.visible");
    });

    it("Add CHECK payment with check number - validates validateChecks method", () => {
        const uniqueSeed = Date.now().toString();
        const depositComment = "Test Deposit Check " + uniqueSeed;
        const paymentAmount = (Math.floor(Math.random() * 900) + 100).toString(); // Random amount 100-999
        const checkNumber = uniqueSeed.substring(0, 8);

        // Create a new deposit
        cy.visit("FindDepositSlip.php");
        cy.get("#depositComment").type(depositComment);
        cy.get("#addNewDeposit").click();
        cy.url().should("contain", "DepositSlipEditor.php");

        // Add a check payment
        cy.get(".btn-success").click();
        cy.url().should("contain", "PledgeEditor.php");

        // Wait for form to be ready
        cy.get("#1_Amount").should("be.visible");
        cy.get("#CheckNo").type(checkNumber);  // Fill check number first

        // Fill payment form
        cy.intercept('POST', '/api/payments').as('submitCheckPayment');
        cy.get("#1_Amount").clear().type(paymentAmount);

        // Submit and verify no errors
        cy.get("#saveBtn").click();

        // Verify payment appears in deposit slip with correct amount and check number
        cy.url().should("contain", "DepositSlipEditor.php");
        cy.get("#paymentsTable").should("be.visible");
        cy.get("#paymentsTable").contains(paymentAmount).should("be.visible");
        cy.get("#paymentsTable").contains(checkNumber).should("be.visible");
    });

    it("Add payment with multiple funds - validates FundSplit object handling", () => {
        const uniqueSeed = Date.now().toString();
        const depositComment = "Test Deposit Split " + uniqueSeed;
        const checkNumber = uniqueSeed.substring(0, 8);
        const fund1Amount = (Math.floor(Math.random() * 400) + 50).toString(); // Random 50-449
        const fund2Amount = (Math.floor(Math.random() * 400) + 50).toString(); // Random 50-449
        const totalAmount = (parseInt(fund1Amount) + parseInt(fund2Amount)).toString();
        
        // Create a new deposit
        cy.visit("FindDepositSlip.php");
        cy.get("#depositComment").type(depositComment);
        cy.get("#addNewDeposit").click();
        cy.url().should("contain", "DepositSlipEditor.php");

        // Add a payment with fund split
        cy.get(".btn-success").click();
        cy.url().should("contain", "PledgeEditor.php");

        // Wait for form to be ready
        cy.get("#1_Amount").should("be.visible");
        cy.get("#CheckNo").type(checkNumber);  // Fill check number first

        // Fill payment form with multiple funds
        cy.intercept('POST', '/api/payments').as('submitSplitPayment');
        cy.get("#1_Amount").clear().type(fund1Amount);
        cy.get("#2_Amount").clear().type(fund2Amount);

        // Submit and verify
        cy.get("#saveBtn").click();

        // Verify payment appears in deposit slip with correct total amount
        cy.url().should("contain", "DepositSlipEditor.php");
        cy.get("#paymentsTable").should("be.visible");
        cy.get("#paymentsTable").contains(totalAmount).should("be.visible");
        cy.get("#paymentsTable").contains(checkNumber).should("be.visible");

        // Close the deposit slip
        cy.get("#Closed").check();
        cy.get("button[name='DepositSlipSubmit']").click();
        
        // Verify we're still on the deposit slip editor page
        cy.url().should("contain", "DepositSlipEditor.php");
        cy.get("#Closed").should("be.checked");
    });
});
