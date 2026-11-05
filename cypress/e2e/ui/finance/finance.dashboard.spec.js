/// <reference types="cypress" />

describe("Finance Dashboard access", () => {
    it("allows a finance user to open the dashboard and funds page", () => {
        cy.setupFinanceOnlySession();

        cy.visit("/finance/");
        cy.get("h2.page-title").should("contain", "Finance Dashboard");

        cy.visit("/finance/funds");
        cy.contains("Donation Funds");
    });

    it("allows a standard user with finance permission to open the dashboard", () => {
        cy.setupStandardSession();

        cy.visit("/finance/");
        cy.get("h2.page-title").should("contain", "Finance Dashboard");
    });
});
