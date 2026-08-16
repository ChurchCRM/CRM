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
        // The subdir CI environment (user 3) starts with finance.show.payments='0'
        // and finance.show.pledges='0', so the page JS fires two POST requests to
        // update these before initialising DataTables.  In some CI timing
        // conditions this causes 2-4 rapid page reloads before settling.
        //
        // cy.get() re-queries the live DOM on every retry, so it is safe to use
        // through page reloads.  initComplete appends one FY <option> per fiscal
        // year found in the data BEFORE calling draw(), so waiting for at least
        // 2 options (static "All Time" + at least one FY added by initComplete)
        // is a reliable signal that draw() has been called and rows are rendered.
        //
        // 30-second timeout covers any reload loop plus DataTable Ajax latency.
        cy.get("#giving-fy-select option", { timeout: 30000 }).should(
            "have.length.at.least",
            2,
        );

        // Data rows must now be visible
        cy.contains("Music Ministry").should("be.visible");

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

        cy.contains("New Building Fund").should("be.visible");
    });
});
