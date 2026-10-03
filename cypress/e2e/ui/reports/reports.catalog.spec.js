/// <reference types="cypress" />

describe("Reports menu (#10259)", () => {
    describe("as admin", () => {
        beforeEach(() => cy.setupAdminSession());

        it("lists People and Financial reports and not a v2 index", () => {
            cy.visit("/v2/dashboard");
            cy.get("body").should("not.contain", "Fatal error");
            cy.get('a[href$="/people/reports"]').should("contain", "People Reports");
            cy.get('a[href$="/finance/reports"]').should("contain", "Financial Reports");
            cy.get("a[href$='/v2/reports']").should("not.exist");
            cy.contains("a", "Fundraiser Reports").should("not.exist");
            cy.request({ url: "/v2/reports", failOnStatusCode: false }).its("status").should("eq", 404);
        });

        it("does not link to the retired query list", () => {
            cy.visit("/v2/dashboard");
            cy.get('a[href$="/people/reports"]').should("exist");
            cy.get('a[href$="QueryList.php"]').should("not.exist");
            cy.request({ url: "/QueryList.php", failOnStatusCode: false }).its("status").should("eq", 404);
        });

        it("serves People Reports from /people/reports", () => {
            cy.visit("/people/reports");
            cy.get("#peopleReports").should("exist");
            cy.request({ url: "/v2/reports/people", failOnStatusCode: false }).its("status").should("eq", 404);
        });
    });

    describe("as a finance-only user", () => {
        beforeEach(() => cy.setupFinanceOnlySession());

        it("sees only Financial Reports", () => {
            cy.visit("/v2/dashboard");
            cy.get('a[href$="/finance/reports"]').should("exist");
            cy.get('a[href$="/people/reports"]').should("not.exist");
            cy.get('a[href$="QueryList.php"]').should("not.exist");
        });
    });

    describe("as a user without finance", () => {
        beforeEach(() => cy.setupNoFinanceSession());

        it("does not list financial or people reports", () => {
            cy.visit("/v2/dashboard");
            cy.get('a[href$="/finance/reports"]').should("not.exist");
            cy.get('a[href$="/people/reports"]').should("not.exist");
        });
    });
});
