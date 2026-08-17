/// <reference types="cypress" />

describe("Query List Page", () => {
    // QueryList.php and QueryView.php are now admin-only (GHSA-6rgg-mrx3-92w7)
    beforeEach(() => cy.setupAdminSession());

    it("loads query listing without errors", () => {
        cy.visit("QueryList.php");
        cy.contains("Query Listing");
        cy.get("body").should("not.contain", "Fatal error");
        cy.get("body").should("not.contain", "Warning:");
    });

    it("displays available queries with run links", () => {
        cy.visit("QueryList.php");
        cy.get(".list-group-item").should("have.length.greaterThan", 0);
        cy.get('a[href*="QueryView.php?QueryID="]').should(
            "have.length.greaterThan",
            0,
        );
    });

    it("loads a QueryView page without crash \u2014 RunPreparedQuery smoke (PR #9351)", () => {
        // QueryView.php uses RunPreparedQuery to load the query record and its
        // parameters. Visiting a real QueryID verifies the prepared-statement
        // path introduced in PR #9351 does not crash (no 500, no Fatal error).
        cy.visit("QueryList.php");
        cy.get('a[href*="QueryView.php?QueryID="]').first().then(($link) => {
            const href = $link.attr("href");
            cy.visit(href);
            cy.contains("Query View").should("exist");
            cy.get("body").should("not.contain", "Fatal error");
            cy.get("body").should("not.contain", "Warning:");
        });
    });
});
