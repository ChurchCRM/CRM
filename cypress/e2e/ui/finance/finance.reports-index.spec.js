/// <reference types="cypress" />

describe("Finance Reports Index", () => {
    beforeEach(() => {
        cy.setupAdminSession();
    });

    it("returns 404 for the retired tax statements URL", () => {
        cy.request({
            url: "/finance/reports/tax-statements",
            failOnStatusCode: false,
        }).its("status").should("eq", 404);
    });
});

describe("Finance Reports Index - Standard User Access", () => {
    beforeEach(() => {
        cy.setupStandardSession();
    });

    it("allows a standard user with finance permission to open reports", () => {
        cy.visit("/finance/reports");
        cy.location("pathname").should("include", "/finance/reports");
        cy.contains("Financial Reports");
    });
});
