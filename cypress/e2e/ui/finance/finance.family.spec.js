/// <reference types="cypress" />

describe("Finance Family", () => {
    beforeEach(() => {
        cy.setupAdminSession();
    });

    it("View a Family with Pledges and Payments section", () => {
        // Intercept the DataTable Ajax before visiting — ensures cy.wait captures
        // the request even if the Promise.all resolves before cy.visit returns.
        cy.intercept("GET", "**/api/payments/family/**").as("givingData");

        cy.visit("people/family/1");
        // Page title is family name, subtitle has "Family Profile"
        cy.contains("Campbell");
        cy.contains("Family Profile");
        cy.contains("Darren Campbell");

        // Finance section should be visible as Giving tab with pill filters
        cy.contains("Giving");
        cy.get(".pledge-type-pill").should("have.length", 3);
        cy.get("#giving-fy-select").should("exist");

        // Wait for DataTable to init (wrapper created synchronously on DataTable() call)
        cy.get("#pledge-payment-v2-table_wrapper", { timeout: 15000 }).should("exist");

        // Wait for the actual Ajax round-trip to complete.  This is the only
        // reliable gate: the wrapper exists as soon as DataTable() is called but
        // the Ajax fires after the Promise.all resolves.  cy.wait blocks until
        // the response arrives, after which initComplete has run and rows are
        // rendered (initComplete auto-clears the FY filter because family 1 has
        // no current-FY giving data, so all-time rows are shown).
        cy.wait("@givingData", { timeout: 20000 });

        // Data rows must now be visible
        cy.contains("Music Ministry").should("be.visible");

        // Test type filter pills (client-side filter, independent of FY)
        cy.get('.pledge-type-pill[data-filter="Pledge"]').click();
        cy.get(".pledge-type-pill.active").should("contain", "Pledges");

        cy.get('.pledge-type-pill[data-filter=""]').click();
        cy.get(".pledge-type-pill.active").should("contain", "All");
    });

    it("View another Family with finance data", () => {
        cy.intercept("GET", "**/api/payments/family/**").as("givingData");

        cy.visit("people/family/20");
        cy.contains("Black");
        cy.contains("Family Profile");

        // Giving tab is present
        cy.contains("Giving");

        // Wait for DataTable to init
        cy.get("#pledge-payment-v2-table_wrapper", { timeout: 15000 }).should("exist");

        // Wait for Ajax response before asserting DOM content
        cy.wait("@givingData", { timeout: 20000 });

        // This family has giving history in FY 2018 only (no current-FY data).
        // initComplete auto-switches to All Time view.
        cy.contains("New Building Fund").should("be.visible");
    });
});
