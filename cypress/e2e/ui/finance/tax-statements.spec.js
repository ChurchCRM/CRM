/// <reference types="cypress" />

describe("Tax statements", () => {
    beforeEach(() => {
        cy.setupAdminSession();
    });

    it("loads the tax statements page", () => {
        cy.visit("/finance/reports/tax");
        cy.contains("Tax statements");
        cy.get("#Year").should("exist");
    });

    it("continues into the year-end wizard", () => {
        cy.visit("/finance/reports/tax");
        cy.get("form").submit();
        cy.url().should("include", "/finance/reports/year-end");
        cy.url().should("include", "step=3");
    });
});
