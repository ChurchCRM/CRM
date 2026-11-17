/// <reference types="cypress" />

describe("Finance: Fund Contributor Drill-down", () => {
    beforeEach(() => {
        cy.setupAdminSession();
    });

    it("filters fund contributors to the selected fiscal year", () => {
        cy.visit("/finance/fund/1/contributors");
        cy.get("#fyid").select("22");

        cy.location("search").should("include", "fyid=22");
        cy.get("#fundContributors tbody").should("contain.text", "Beck").and("contain.text", "Berry");
    });

    it("returns 404 for a nonexistent fund", () => {
        cy.request({
            url: "/finance/fund/99999/contributors",
            failOnStatusCode: false,
        }).its("status").should("eq", 404);
    });
});
