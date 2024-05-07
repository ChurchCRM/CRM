/// <reference types="cypress" />

context("Finance Deposits", () => {
    it("Create a new Deposit", () => {
        cy.loginAdmin("FindDepositSlip.php");
        cy.contains("Add New Deposit");
        cy.contains("Deposits");
        cy.get("#depositComment").click();
        cy.get("#depositComment").type("Selenium Test Deposit");
        cy.get("#addNewDeposit").click();

        cy.contains("Selenium Test Deposit");

        cy.get("#depositComment").click();
        cy.get("#depositComment").clear();
        cy.get("#addNewDeposit").click();
        cy.contains("You are about to add a new deposit without a comment");
    });

    it("Open the Deposits page & Add Payment", () => {
        cy.loginAdmin("DepositSlipEditor.php?DepositSlipID=5");
        cy.contains("Bank Deposit Slip Number: 5");
        cy.contains("Payments on this deposit slip");

        cy.get(".btn-success").click();
        cy.url().should("contains", "PledgeEditor.php");
        // TODO: Select family via Select 2
        //cy.get('.select2-container--below .select2-selection').click();
        //cy.get('.select2-search__field').type('Berry{enter}', { delay: 500 });
        cy.get("#CheckNo").type("111");
        cy.get("#1_Amount").type("1000");
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
