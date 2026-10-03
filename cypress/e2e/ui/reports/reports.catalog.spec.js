/// <reference types="cypress" />

describe("Reports catalog (#10259)", () => {
    describe("as admin", () => {
        beforeEach(() => cy.setupAdminSession());

        it("lists every module report on the Reports page", () => {
            cy.visit("/v2/reports");
            cy.get("body").should("not.contain", "Fatal error");
            cy.get("#reportCatalog .report-entry").should("have.length", 3);
            cy.get("#reportCatalog a[href$='/people/reports']").should("contain", "People Reports");
            cy.get("#reportCatalog a[href$='/finance/reports']").should("contain", "Financial Reports");
        });

        it("builds the Reports menu from the same catalog", () => {
            cy.visit("/v2/reports");
            cy.get("a[href$='/v2/reports']").should("exist");
            cy.get("a[href$='/people/reports']").should("exist");
            cy.get("a[href$='/QueryList.php']").should("exist");
        });

        it("serves People Reports from /people/reports and no longer from /v2/reports/people", () => {
            cy.visit("/people/reports");
            cy.get("#peopleReports").should("exist");
            cy.request({ url: "/v2/reports/people", failOnStatusCode: false }).its("status").should("eq", 404);
        });
    });

    describe("as a finance-only user", () => {
        beforeEach(() => cy.setupFinanceOnlySession());

        it("sees only the reports their permissions allow", () => {
            cy.visit("/v2/reports");
            cy.get("#reportCatalog a[href$='/finance/reports']").should("exist");
            cy.get("#reportCatalog a[href$='/people/reports']").should("not.exist");
            cy.get("a[href$='/QueryList.php']").should("not.exist");
        });
    });

    describe("as a user without finance", () => {
        beforeEach(() => cy.setupNoFinanceSession());

        it("does not list financial or people reports", () => {
            cy.visit("/v2/reports");
            cy.get("#reportCatalog a[href$='/finance/reports']").should("not.exist");
            cy.get("#reportCatalog a[href$='/people/reports']").should("not.exist");
        });
    });
});
