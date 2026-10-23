/// <reference types="cypress" />

describe("Finance Reports Index", () => {
    beforeEach(() => {
        cy.setupAdminSession();
    });

    it("should load the reports index and lead with year-end", () => {
        cy.visit("/finance/reports");
        cy.contains("Financial Reports");
        cy.contains("Send year-end giving statements");
        cy.contains("See who has not given");
        cy.contains("Review pledges vs payments");
    });

    it("should list more reports", () => {
        cy.visit("/finance/reports");
        cy.contains("More reports");
        cy.contains("Send pledge reminders");
        cy.contains("Print a deposit slip");
    });

    it("should open the year-end wizard from the primary card", () => {
        cy.visit("/finance/reports");
        cy.contains("Send year-end giving statements").click();
        cy.url().should("include", "/finance/reports/year-end");
    });
});
