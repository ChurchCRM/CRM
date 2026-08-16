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

        // Finance section should be visible as Giving tab with pill filters
        cy.contains("Giving");
        cy.get(".pledge-type-pill").should("have.length", 3);
        cy.get("#giving-fy-select").should("exist");

        // Wait for DataTable to init (wrapper is created synchronously)
        cy.get("#pledge-payment-v2-table_wrapper", { timeout: 15000 }).should("exist");

        // This family's data is in FY 2018 — initComplete auto-switches to All Time view.
        // In DataTables 3.x the loading row has class `dt-empty` on the <td>, not the <tr>,
        // so wait directly for the fund text rather than a row-count assertion (which would
        // spuriously pass on the loading row and advance Cypress before Ajax returns).
        cy.contains("Music Ministry", { timeout: 15000 }).should("be.visible");

        // Test type filter pills (client-side filter, independent of FY)
        cy.get('.pledge-type-pill[data-filter="Pledge"]').click();
        cy.get(".pledge-type-pill.active").should("contain", "Pledges");

        cy.get('.pledge-type-pill[data-filter=""]').click();
        cy.get(".pledge-type-pill.active").should("contain", "All");
    });

    it("View another Family with finance data", () => {
        cy.visit("people/family/20");
        cy.contains("Black");
        cy.contains("Family Profile");

        // Giving tab is present
        cy.contains("Giving");

        // Wait for DataTable to init
        cy.get("#pledge-payment-v2-table_wrapper", { timeout: 15000 }).should("exist");

        // This family has giving history in FY 2018 only (no current-FY data).
        // initComplete auto-switches to All Time view — wait directly for fund text.
        cy.contains("New Building Fund", { timeout: 15000 }).should("be.visible");
    });
});
