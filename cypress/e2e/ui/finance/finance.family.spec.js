/// <reference types="cypress" />

describe("Finance Family", () => {
    beforeEach(() => {
        cy.setupAdminSession();
    });

    it("View a Family with Pledges and Payments section", () => {
        cy.visit("v2/family/1");
        // Page title is family name, subtitle has "Family Profile"
        cy.contains("Campbell");
        cy.contains("Family Profile");
        cy.contains("Darren Campbell");

        // Finance section should be visible with pill filters
        cy.contains("Pledges and Payments");
        cy.get(".pledge-type-pill").should("have.length", 3);
        cy.get(".pledge-fy-pill").should("have.length", 2);

        // Table should load with data
        cy.get("#pledge-payment-v2-table").should("be.visible");
        cy.contains("Music Ministry");

        // Test type filter pills
        cy.get('.pledge-type-pill[data-filter="Pledge"]').click();
        cy.get(".pledge-type-pill.active").should("contain", "Pledges");

        cy.get('.pledge-type-pill[data-filter=""]').click();
        cy.get(".pledge-type-pill.active").should("contain", "All");

        // Test FY filter pills
        cy.get('.pledge-fy-pill[data-fy=""]').click();
        cy.get(".pledge-fy-pill.active").should("contain", "All Time");
    });

    it("View another Family with finance data", () => {
        cy.visit("v2/family/20");
        cy.contains("Black");
        cy.contains("Family Profile");
        cy.contains("New Building Fund");
    });
});
