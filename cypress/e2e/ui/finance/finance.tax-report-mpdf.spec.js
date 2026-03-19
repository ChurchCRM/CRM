/// <reference types="cypress" />

/**
 * Tests for the new mPDF-based Tax Statement routes:
 *   GET  /finance/reports/tax-statements  (configuration form)
 *   POST /finance/reports/tax-report      (PDF generation)
 */

describe("Tax Statements mPDF — Configuration Form", () => {
    beforeEach(() => {
        cy.setupAdminSession();
    });

    it("should load the tax-statements form page", () => {
        cy.visit("/finance/reports/tax-statements");
        cy.contains("Tax Statements (Giving Report)");
        cy.get("#taxReportForm").should("exist");
        cy.get("#DateStart").should("exist");
        cy.get("#DateEnd").should("exist");
        cy.get("#classList").should("exist");
        cy.get("#fundsList").should("exist");
        cy.get("#family").should("exist");
        cy.get("#deposit").should("exist");
    });

    it("should show No Data Found alert when NoRows param is set", () => {
        cy.visit("/finance/reports/tax-statements?NoRows=1");
        cy.get(".alert-warning").should("be.visible");
        cy.contains("No Data Found");
    });

    it("should not show No Data Found alert on normal load", () => {
        cy.visit("/finance/reports/tax-statements");
        cy.get(".alert-warning").should("not.exist");
    });

    it("should have letterhead radio options", () => {
        cy.visit("/finance/reports/tax-statements");
        cy.get('input[name="letterhead"][value="graphic"]').should("exist");
        cy.get('input[name="letterhead"][value="address"]').should("be.checked");
        cy.get('input[name="letterhead"][value="none"]').should("exist");
    });

    it("should have remittance radio options", () => {
        cy.visit("/finance/reports/tax-statements");
        cy.get('input[name="remittance"][value="no"]').should("be.checked");
        cy.get('input[name="remittance"][value="yes"]').should("exist");
    });

    it("should support Add All / Clear All for classifications", () => {
        cy.visit("/finance/reports/tax-statements");
        cy.get("#addAllClasses").click();
        cy.get("#classList option:selected").should("have.length.greaterThan", 0);
        cy.get("#clearAllClasses").click();
        cy.get("#classList option:selected").should("have.length", 0);
    });

    it("should support Add All / Clear All for funds", () => {
        cy.visit("/finance/reports/tax-statements");
        cy.get("#addAllFunds").click();
        cy.get("#fundsList option:selected").should("have.length.greaterThan", 0);
        cy.get("#clearAllFunds").click();
        cy.get("#fundsList option:selected").should("have.length", 0);
    });
});

describe("Tax Statements mPDF — PDF Generation", () => {
    beforeEach(() => {
        cy.setupAdminSession();
    });

    it("should generate PDF or redirect when submitting with broad date range", () => {
        cy.intercept("POST", "**/finance/reports/tax-report").as("taxReport");
        cy.visit("/finance/reports/tax-statements");

        cy.get("#DateStart").clear().type("2005-01-01");
        cy.get("#DateEnd").clear().type("2025-12-31");
        cy.get("#taxReportForm").submit();

        cy.wait("@taxReport").then((interception) => {
            const status = interception.response.statusCode;
            // Either a PDF (200) or a no-data redirect (302) is acceptable
            expect([200, 302]).to.include(status);
            if (status === 200) {
                expect(interception.response.headers["content-type"]).to.include(
                    "application/pdf",
                );
            }
        });
    });

    it("should redirect with NoRows when date range has no data", () => {
        cy.visit("/finance/reports/tax-statements");

        cy.get("#DateStart").clear().type("2099-01-01");
        cy.get("#DateEnd").clear().type("2099-12-31");
        cy.get("#taxReportForm").submit();

        // Should land back on the form with the no-data alert
        cy.url().should("contain", "tax-statements");
        cy.contains("No Data Found");
    });

    it("should not return 500 for any letterhead option", () => {
        const letterheadOptions = ["graphic", "address", "none"];

        letterheadOptions.forEach((option) => {
            cy.intercept("POST", "**/finance/reports/tax-report").as(
                `lh-${option}`,
            );
            cy.visit("/finance/reports/tax-statements");

            cy.get("#DateStart").clear().type("2005-01-01");
            cy.get("#DateEnd").clear().type("2025-12-31");
            cy.get(`input[name="letterhead"][value="${option}"]`).check();
            cy.get("#taxReportForm").submit();

            cy.wait(`@lh-${option}`).then((interception) => {
                expect(interception.response.statusCode).to.not.equal(500);
            });
        });
    });
});

describe("Tax Statements mPDF — Access Control", () => {
    it("should deny access to users without finance permission", () => {
        cy.setupNoFinanceSession();
        cy.visit("/finance/reports/tax-statements", {
            failOnStatusCode: false,
        });
        cy.url().should("include", "/v2/access-denied");
    });
});
