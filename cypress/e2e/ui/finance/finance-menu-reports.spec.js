/// <reference types="cypress" />

describe("Finance menu", () => {
    beforeEach(() => cy.setupAdminSession());

    it("links to Financial Reports", () => {
        cy.visit("/finance/");
        cy.get("a[href$='/finance/reports']").should("exist");
    });
});
