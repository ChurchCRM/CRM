/// <reference types="cypress" />

/**
 * Finance Statement Settings (#10936)
 *
 * The page at /finance/statements/settings surfaces contribution-statement
 * config keys with autoSave. It requires the Finance role (FinanceRoleAuthMiddleware).
 */

describe("Finance Statement Settings", () => {
    describe("Admin user", () => {
        beforeEach(() => cy.setupAdminSession());

        it("auto-saves a setting when its value changes", () => {
            cy.intercept("POST", "**/finance/api/config/sTaxSigner").as("saveSigner");
            const signer = `Test Signer ${Date.now()}`;

            cy.visit("/finance/statements/settings");
            cy.get("#statementWording", { timeout: 10000 }).should("exist");

            cy.get("#statementWording input[name='sTaxSigner']")
                .should("not.be.disabled")
                .clear()
                .type(signer)
                .blur();

            cy.wait("@saveSigner").its("response.statusCode").should("eq", 200);
        });

    });

    describe("Finance-only user (not admin)", () => {
        beforeEach(() => cy.setupFinanceOnlySession());

        it("can access the finance statement settings page", () => {
            cy.visit("/finance/statements/settings");
            cy.url().should("include", "/finance/statements/settings");
            cy.contains("Statement Settings");
        });
    });

    describe("Non-finance user", () => {
        beforeEach(() => cy.setupNoFinanceSession());

        it("is redirected away from the finance statement settings page", () => {
            cy.visit("/finance/statements/settings", { failOnStatusCode: false });
            cy.url().should("not.include", "/finance/statements/settings");
        });
    });
});
