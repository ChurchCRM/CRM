/// <reference types="cypress" />

context("Finance Deposits", () => {
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

        cy.url().should("contains", "DepositSlipEditor.php");

        cy.get(".btn-success").click();
        cy.url().should("contains", "PledgeEditor.php");

        cy.get("#1_Amount").type("1000");
        cy.get("#CheckNo").type(uniqueSeed);

        cy.get("#saveBtn").click();
        cy.get("#DepositSlipEditor").submit();
        cy.url().should("contains", "DepositSlipEditor.php");
    });

    it("Open the Deposits page & Add Payment", () => {
        cy.loginAdmin("DepositSlipEditor.php?DepositSlipID=5");
        cy.contains("Bank Deposit Slip Number: 5");
        cy.contains("Payments on this deposit slip");

        cy.get(".btn-success").click();
        cy.url().should("contains", "PledgeEditor.php");
        
        cy.get("#1_Amount").type("1000");
        cy.get("#CheckNo").type("111");

        cy.get("#saveBtn").click();
        cy.get("#DepositSlipEditor").submit();
        cy.url().should("contains", "DepositSlipEditor.php");
    });

    it("Edit Deposit without an ID", () => {
        cy.loginAdmin("DepositSlipEditor.php?DepositSlipID=9999", false);
        cy.url().should("contains", "FindDepositSlip.php");
        cy.contains("Deposit Listing");
    });

    it("Open Deposit with the Bad / deleted Deposits id", () => {
        cy.loginAdmin("DepositSlipEditor.php?", false);
        cy.url().should("contains", "FindDepositSlip.php");
        cy.contains("Deposit Listing");
    });
});
