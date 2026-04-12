/// <reference types="cypress" />

describe("Finance Family", () => {
    beforeEach(() => {
        cy.setupAdminSession();
    });

    it("View a Family with Pledges and Payments section", () => {
        cy.visit("people/family/1");
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

        // Default FY filter hides older data — "Music Ministry" should NOT be visible
        cy.contains("Music Ministry").should("not.exist");

        // Click "All Time" to reveal all records
        cy.get('.pledge-fy-pill[data-fy=""]').click();
        cy.get(".pledge-fy-pill.active").should("contain", "All Time");
        cy.contains("Music Ministry").should("be.visible");

        // Test type filter pills
        cy.get('.pledge-type-pill[data-filter="Pledge"]').click();
        cy.get(".pledge-type-pill.active").should("contain", "Pledges");

        cy.get('.pledge-type-pill[data-filter=""]').click();
        cy.get(".pledge-type-pill.active").should("contain", "All");
    });

    it("View another Family with finance data", () => {
        cy.visit("people/family/20");
        cy.contains("Black");
        cy.contains("Family Profile");

        // Wait for finance section and table to be ready
        cy.contains("Pledges and Payments");
        cy.get("#pledge-payment-v2-table").should("be.visible");

        // Default FY filter hides older data
        cy.contains("New Building Fund").should("not.exist");

        // Click "All Time" to reveal all records
        cy.get('.pledge-fy-pill[data-fy=""]').click();
        cy.get(".pledge-fy-pill.active").should("contain", "All Time");
        cy.contains("New Building Fund").should("be.visible");
    });
});
