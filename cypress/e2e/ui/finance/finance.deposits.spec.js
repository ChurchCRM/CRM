/// <reference types="cypress" />

describe("Finance Deposits", () => {
    it("Envelope Manager", () => {
        cy.loginAdmin("ManageEnvelopes.php");
        cy.contains("Envelope Manager");
    });

    it("Create a new Deposit without comment", () => {
        cy.loginAdmin("FindDepositSlip.php");
        cy.get("#depositComment").clear();
        cy.get("#addNewDeposit").click();
        cy.contains("You are about to add a new deposit without a comment");
    });

    it("Create a new Deposit", () => {
        const uniqueSeed = Date.now().toString();
        const name = "New Test Deposit " + uniqueSeed;

        cy.loginAdmin("FindDepositSlip.php");
        cy.contains("Add New Deposit");
        cy.contains("Deposits");
        cy.get("#depositComment").type(name);
        cy.get("#addNewDeposit").click();

        cy.url().should("contain", "DepositSlipEditor.php");

        cy.get(".btn-success").click();
        cy.url().should("contain", "PledgeEditor.php");

        cy.get("#1_Amount").type("1000");
        cy.get("#CheckNo").type(uniqueSeed);

        cy.get("#saveBtn").click();
        cy.get("#DepositSlipEditor").submit();
        cy.url().should("contain", "DepositSlipEditor.php");
    });

    it("Open the Deposits page & Add Payment", () => {
        cy.loginAdmin("DepositSlipEditor.php?DepositSlipID=5");
        cy.contains("Bank Deposit Slip Number: 5");
        cy.contains("Payments on this deposit slip");

        cy.get(".btn-success").click();
        cy.url().should("contain", "PledgeEditor.php");
        
        cy.get("#1_Amount").type("1000");
        cy.get("#CheckNo").type("111");

        cy.get("#saveBtn").click();
        cy.get("#DepositSlipEditor").submit();
        cy.url().should("contain", "DepositSlipEditor.php");
    });

    it("Edit Deposit without an ID", () => {
        cy.loginAdmin("DepositSlipEditor.php?DepositSlipID=9999", false);
        cy.url().should("contain", "FindDepositSlip.php");
        cy.contains("Deposit Listing");
    });

    it("Open Deposit with the Bad / deleted Deposits id", () => {
        cy.loginAdmin("DepositSlipEditor.php?", false);
        cy.url().should("contain", "FindDepositSlip.php");
        cy.contains("Deposit Listing");
    });

    it("Create a Deposit with XSS attempt - should be sanitized", () => {
        const uniqueSeed = Date.now().toString();
        const xssPayload = "<script>alert('XSS')</script>Test" + uniqueSeed;
        const sanitizedComment = "Test" + uniqueSeed; // The script tags should be stripped

        cy.loginAdmin("FindDepositSlip.php");
        cy.contains("Add New Deposit");
        cy.get("#depositComment").type(xssPayload);
        cy.get("#addNewDeposit").click();

        cy.url().should("contain", "DepositSlipEditor.php");
        
        // Verify the comment field contains sanitized text (no script tags)
        cy.get("#Comment").should("have.value", sanitizedComment);
        
        // Navigate back to deposits list
        cy.visit("/FindDepositSlip.php");
        
        // Verify the deposit appears in the table with sanitized comment
        cy.get("#depositsTable").should("contain", sanitizedComment);
        cy.get("#depositsTable").should("not.contain", "<script>");
    });
});
