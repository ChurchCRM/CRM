/// <reference types="cypress" />

describe("Finance Family", () => {
    beforeEach(() => {
        cy.setupAdminSession();
    });

    it("View a Family with Pledges and Payments section", () => {
        cy.visit("people/family/1");

        // Basic page identity checks
        cy.contains("Campbell");
        cy.contains("Family Profile");
        cy.contains("Darren Campbell");

        // Finance section should be visible as Giving tab with pill filters
        cy.contains("Giving");
        cy.get(".pledge-type-pill").should("have.length", 3);
        cy.get("#giving-fy-select").should("exist");

        // Gate on initComplete having populated the FY dropdown.
        //
        // Background: the CI test runner may use a user whose
        // finance.show.pledges / finance.show.payments settings start at '0'.
        // FamilyView.js fires two POSTs to update those settings to 'true'
        // before initialising DataTables, which in some CI timing conditions
        // produces 2-4 rapid page reloads before the page settles.
        //
        // In DataTables 2.x (client-side mode), filtered-out rows are *removed*
        // from the DOM — they only appear in <tbody> once the matching filter
        // (or no filter) is applied.  initComplete appends one FY <option> per
        // fiscal year found in the data synchronously before calling draw(), and
        // auto-clears the FY filter when the family has no current-year data.
        //
        // Strategy:
        //  1. Wait for ≥2 FY options — proves initComplete ran at least once.
        //  2. Wait for ≥1 visible <tr> in <tbody> — proves the filter was cleared
        //     and rows were written to the DOM.
        //  3. Only then assert on specific content and filter interactions.
        //
        // 30-second timeouts cover any reload loop plus DataTable Ajax latency.
        cy.get("#giving-fy-select option", { timeout: 30000 }).should(
            "have.length.at.least",
            2,
        );

        // After initComplete cleared the filter, actual data rows must be in the DOM
        cy.get("#pledge-payment-v2-table tbody tr", { timeout: 30000 }).should(
            "have.length.at.least",
            1,
        );

        // Data rows must now be visible
        cy.contains("Music Ministry");

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

        // Same gate pattern as test 1 — wait for initComplete to populate FY options.
        // Family 20 has giving history in FY 2018 only (no current-FY data), so
        // initComplete auto-switches to All Time view.
        cy.get("#giving-fy-select option", { timeout: 30000 }).should(
            "have.length.at.least",
            2,
        );

        // Rows must be in the DOM (filter cleared)
        cy.get("#pledge-payment-v2-table tbody tr", { timeout: 30000 }).should(
            "have.length.at.least",
            1,
        );

        cy.contains("New Building Fund");
    });
});
