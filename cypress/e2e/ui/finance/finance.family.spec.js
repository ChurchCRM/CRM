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
        // cy.request reaches the real server through the current session and updates
        // the DB before the page visits below fire their own XHRs.
        // failOnStatusCode: false silently ignores 401s (user 3 cannot write user 1's
        // settings, and vice-versa in the contaminated-session case).
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

        // Intercept the in-page setting POSTs that FamilyView.js fires on every
        // page load.  The DB is already correct (above); intercepting them ensures
        // Promise.all resolves instantly and eliminates any server-side side-effect
        // (the source of the 3-4 page reload loop observed in CI).
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

        // Gate: wait for initComplete to populate the FY dropdown.
        //
        // FamilyView.js initialises DataTable with ajax:... and pre-sets a
        // column-5 filter to the current FY.  initComplete fires after the first
        // Ajax draw, reads all rows, and appends one <option> per unique FY.
        // ≥2 options (All Time + at least one FY year) proves initComplete ran.
        //
        // Explicit 30 s timeout overrides docker.config.ts's 5 s default.
        cy.get("#giving-fy-select option", { timeout: 30000 }).should(
            "have.length.at.least",
            2,
        );

        // Switch to "All Time" to clear the active FY column filter.
        //
        // The default view shows the current FY (2026 as of this writing).
        // Family 1 has giving records in FY 2026, so initComplete does NOT
        // auto-clear the filter — historical records like "Music Ministry"
        // (from an earlier FY) are hidden.  Selecting the empty ("All Time")
        // option fires the jQuery change handler which calls
        // pledgeTable.column(5).search("").draw(), making all rows visible.
        //
        // This mirrors the old master spec's `.pledge-fy-pill[data-fy=""].click()`
        // and is robust regardless of whether the family has current-year data.
        cy.get("#giving-fy-select").select("");

        // Content assertion: "Music Ministry" is a historical fund for this family.
        // After selecting All Time, the redraw is synchronous (client-side mode),
        // so a short timeout is sufficient.  30 s used defensively for CI latency.
        cy.contains("#pledge-payment-v2-table", "Music Ministry", {
            timeout: 30000,
        }).should("be.visible");

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

        // Same gate as test 1 — FY options populated by initComplete.
        cy.get("#giving-fy-select option", { timeout: 30000 }).should(
            "have.length.at.least",
            2,
        );

        // Switch to "All Time" for the same reason as test 1.
        cy.get("#giving-fy-select").select("");

        cy.contains("#pledge-payment-v2-table", "New Building Fund", {
            timeout: 30000,
        }).should("be.visible");
    });
});
