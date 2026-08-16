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

        // Wait for the actual Ajax round-trip to complete.
        cy.wait("@givingData", { timeout: 20000 });

        // Gate on initComplete having populated the FY dropdown.
        //
        // cy.wait resolves when Cypress intercepts the XHR response, which is
        // BEFORE the browser's DataTables Ajax callback fires.  initComplete
        // (which clears the FY filter and calls draw()) runs asynchronously
        // *after* cy.wait resolves.  initComplete appends <option> elements to
        // #giving-fy-select synchronously before calling draw(), so waiting
        // for at least 2 options (the static "All Time" + one FY added by
        // initComplete) is the observable DOM signal that draw() was called and
        // rows are rendered.
        //
        // Family 1 seed data is from 2018; no current-FY data, so initComplete
        // auto-clears the FY filter, appends one FY option, then calls draw().
        cy.get("#giving-fy-select option", { timeout: 15000 }).should(
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
        cy.intercept("GET", "**/api/payments/family/**").as("givingData");

        cy.visit("people/family/20");
        cy.contains("Black");
        cy.contains("Family Profile");

        // Giving tab is present
        cy.contains("Giving");

        // Wait for DataTable to init
        cy.get("#pledge-payment-v2-table_wrapper", { timeout: 15000 }).should("exist");

        // Wait for Ajax response, then gate on initComplete (same pattern as test 1)
        cy.wait("@givingData", { timeout: 20000 });
        cy.get("#giving-fy-select option", { timeout: 15000 }).should(
            "have.length.at.least",
            2,
        );

        // This family has giving history in FY 2018 only (no current-FY data).
        // initComplete auto-switches to All Time view.
        cy.contains("New Building Fund").should("be.visible");
    });
});
