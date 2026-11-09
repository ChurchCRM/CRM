/// <reference types="cypress" />

/** Donation Funds page — /finance/funds */

describe("Finance: Donation Funds page - Add Fund", () => {
    beforeEach(() => {
        cy.setupAdminSession();
    });

    it("Shows error alert when adding a fund with no name", () => {
        cy.visit("/finance/funds");
        cy.get("#newFundName").clear();
        cy.get("#addNewFund").click();
        cy.get("#addFundError").should("not.have.class", "d-none").and("be.visible");
    });

    it("Successfully adds a new fund via the inline form", () => {
        const uniqueName = "CyAdd" + Date.now();

        cy.visit("/finance/funds");
        cy.get("#newFundName").clear().type(uniqueName);
        cy.get("#newFundDesc").clear().type("Cypress test fund");
        cy.get("#addNewFund").click();

        // After reload, fund should appear in the table
        cy.get("#fundsTable").should("contain", uniqueName);
    });
});
