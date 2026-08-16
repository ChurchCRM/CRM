/// <reference types="cypress" />

describe("Finance Family", () => {
    beforeEach(() => {
        cy.setupAdminSession();

        // Pre-set finance display settings for both users that may own the
        // admin-session cookie cache (user 1 = admin, user 3 = tony.wade).
        //
        // Background: cy.session() cross-contamination from finance.deposits.spec.js
        // (which calls both setupAdminSession and setupStandardSession) means the
        // "admin-session" cache sometimes holds user 3's cookies.  User 3 starts
        // with finance.show.pledges='0' and finance.show.payments='0' in a fresh DB.
        // When those settings are '0', GET /api/payments/family/{id}/list filters
        // them out and returns 0 rows — DataTables draws an empty-state row.
        //
        // These cy.request calls reach the real server through the current session
        // and update the DB before the page visits below fire their own XHRs.
        // failOnStatusCode: false silently ignores 401s when the logged-in user
        // is not admin and therefore cannot write another user's settings.
        cy.request({
            method: "POST",
            url: "/api/user/1/setting/finance.show.pledges",
            body: { value: "true" },
            failOnStatusCode: false,
        });
        cy.request({
            method: "POST",
            url: "/api/user/1/setting/finance.show.payments",
            body: { value: "true" },
            failOnStatusCode: false,
        });
        cy.request({
            method: "POST",
            url: "/api/user/3/setting/finance.show.pledges",
            body: { value: "true" },
            failOnStatusCode: false,
        });
        cy.request({
            method: "POST",
            url: "/api/user/3/setting/finance.show.payments",
            body: { value: "true" },
            failOnStatusCode: false,
        });

        // Intercept the identical setting POSTs that FamilyView.js fires on
        // every page load.  The JS fires them unconditionally (it does not
        // check current value before writing).  Stubbing them ensures:
        //   (a) Promise.all resolves immediately — no server round-trip latency
        //       between session setup and DataTable initialisation
        //   (b) no server-side write occurs on page load, preventing any
        //       session / ORM side-effect that may be triggering the observed
        //       3-4 page reload loop in CI
        cy.intercept("POST", "**/api/user/*/setting/finance.show.pledges", {
            statusCode: 200,
            body: { value: "true" },
        }).as("showPledges");
        cy.intercept("POST", "**/api/user/*/setting/finance.show.payments", {
            statusCode: 200,
            body: { value: "true" },
        }).as("showPayments");
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

        // Gate 1: wait for initComplete to populate the FY dropdown.
        //
        // FamilyView.js calls DataTable({ajax:...}).  On the first Ajax draw
        // initComplete fires, reads all rows, appends FY <option> elements,
        // and (for families with no current-year data) clears the pre-init
        // column-5 FY filter so all rows are shown.
        //
        // ≥2 options means initComplete ran AND found at least one historical FY.
        // Explicit 30 s timeout overrides docker.config.ts's 5 s default.
        cy.get("#giving-fy-select option", { timeout: 30000 }).should(
            "have.length.at.least",
            2,
        );

        // Gate 2 + content assertion: wait for the data row with "Music Ministry"
        // to appear inside the table.  Scoping cy.contains to the table element
        // avoids matching stray page text AND skips DataTables' empty-state row
        // ('<tr class="odd"><td class="dataTables_empty">No data available…</td></tr>')
        // which was causing our earlier tbody-tr count gate to fire prematurely.
        // 30 s timeout covers the initComplete redraw + any CI latency.
        cy.contains("#pledge-payment-v2-table", "Music Ministry", {
            timeout: 30000,
        });

        // Type filter pills: client-side filter on column 3 (independent of FY)
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

        // Same pattern as test 1.  Family 20 has giving history in FY 2018 only
        // (no current-FY data) so initComplete auto-switches to All Time.
        cy.get("#giving-fy-select option", { timeout: 30000 }).should(
            "have.length.at.least",
            2,
        );

        cy.contains("#pledge-payment-v2-table", "New Building Fund", {
            timeout: 30000,
        });
    });
});
