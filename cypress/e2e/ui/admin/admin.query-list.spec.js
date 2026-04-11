/// <reference types="cypress" />

describe("Query List Page", () => {
    beforeEach(() => cy.setupStandardSession());

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
});
