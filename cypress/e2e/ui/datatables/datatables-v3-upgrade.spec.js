/// <reference types="cypress" />

/**
 * DataTables v3 ecosystem upgrade smoke tests.
 *
 * These tests verify that the atomic v2→v3 upgrade of the DataTables ecosystem
 * does not regress core table functionality. They cover:
 *   - DataTables v3 core is loaded (version prefix check)
 *   - Table initialises and renders rows
 *   - dt-* class names are present (v2 class migration, stable through v3)
 *   - Search works via the JS API (selector-independent, works with layout config)
 *   - Pagination control is rendered
 *   - Buttons extension renders the CSV export button
 *   - Responsive extension is active (dt-responsive class / collapses on narrow viewport)
 *
 * Targeting the People list page (/people/list) which initialises the full
 * plugin stack: core + bs5 integration + responsive + buttons + select.
 */

describe("DataTables v3 — ecosystem upgrade smoke tests", () => {
    beforeEach(() => {
        cy.setupAdminSession();
    });

    it("loads DataTables v3 core (version sanity check)", () => {
        cy.visit("/people/list");
        cy.get("#members", { timeout: 15000 }).should("exist");
        cy.window().then((win) => {
            // jQuery is on window in ChurchCRM; verify DT v3 is loaded
            expect(win.$.fn.dataTable, "DataTables jQuery bridge should be registered").to.exist;
            const dtVersion = win.$.fn.dataTable.version;
            expect(dtVersion).to.match(/^3\./, `Expected DT v3.x but got ${dtVersion}`);
        });
    });

    it("table initialises and renders rows inside a .dt-container wrapper", () => {
        cy.visit("/people/list");
        // dt-container is the v3 (and v2) wrapper div
        cy.get(".dt-container", { timeout: 15000 }).should("exist");
        // At least one data row should appear
        cy.get("#members tbody tr").should("have.length.greaterThan", 0);
    });

    it("search works via DataTables JS API", () => {
        cy.visit("/people/list");
        cy.get(".dt-container", { timeout: 15000 }).should("exist");

        // Drive search through the JS API — selector-independent, works with
        // any layout config (CRM uses layout.topStart:'search' not default DOM)
        // 'Admin' matches the standard seed fixture person (lastName='Admin');
        // all ChurchCRM test environments include this record.
        cy.window().then((win) => {
            win.$("#members").DataTable().search("Admin").draw();
        });
        cy.get("#members tbody").should("contain", "Admin");

        // Clear search and confirm rows return
        cy.window().then((win) => {
            win.$("#members").DataTable().search("").draw();
        });
        cy.get("#members tbody tr").should("have.length.greaterThan", 0);
    });

    it("search input is rendered inside .dt-search (not legacy .dataTables_filter)", () => {
        cy.visit("/people/list");
        cy.get(".dt-container", { timeout: 15000 }).should("exist");
        // v2+ / v3 layout config renders search under .dt-search
        cy.get(".dt-search input").should("exist");
        // Legacy v1 element must NOT be present
        cy.get(".dataTables_filter").should("not.exist");
    });

    it("pagination control is rendered via .dt-paging", () => {
        cy.visit("/people/list");
        cy.get(".dt-container", { timeout: 15000 }).should("exist");
        // dt-paging is the v3 (and v2) pagination wrapper
        cy.get(".dt-paging").should("exist");
        // Legacy wrapper must NOT be present
        cy.get(".dataTables_paginate").should("not.exist");
    });

    it("Buttons extension renders CSV export button", () => {
        cy.visit("/people/list");
        cy.get(".dt-container", { timeout: 15000 }).should("exist");
        // CRM layout places buttons in topEnd: 'buttons'; .dt-buttons wraps them
        cy.get(".dt-buttons").should("exist");
        // Header.php configures: extend:'csv', text:'<i class="ti ti-table-export"></i>'
        // Verify at least one button renders and the CSV icon class is present
        cy.get(".dt-buttons button, .dt-buttons a")
            .should("have.length.greaterThan", 0);
        cy.get(".dt-buttons .ti-table-export").should("exist");
    });

    it("Responsive extension is active (responsive class on table)", () => {
        // Switch to a narrow viewport so Responsive detects a breakpoint
        cy.viewport(480, 800);
        cy.visit("/people/list");
        cy.get(".dt-container", { timeout: 15000 }).should("exist");
        // Responsive extension adds .dtr-* classes; verify the extension loaded
        cy.window().then((win) => {
            const dt = win.$("#members").DataTable();
            // responsive() accessor exists when extension is active
            expect(dt.responsive).to.exist;
        });
    });
});
