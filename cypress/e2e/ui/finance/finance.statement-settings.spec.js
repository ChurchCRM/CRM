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

        it("saves and reloads each renamed wording setting", () => {
            for (const key of ["sGivingStatementOpening", "sGivingStatementClosing", "sGivingStatementQuestions"]) {
                cy.getSystemConfig(key).then((original) => {
                    const wording = "Élodie — saved giving statement wording";
                    cy.intercept("POST", `**/finance/api/config/${key}`).as("saveWording");
                    cy.visit("/finance/statements/settings");
                    cy.get(`#statementWording textarea[name='${key}']`).should("not.be.disabled").clear().type(wording).blur();
                    cy.wait("@saveWording").its("response.statusCode").should("eq", 200);
                    cy.reload();
                    cy.get(`#statementWording textarea[name='${key}']`).should("have.value", wording);
                    cy.makePrivateAdminAPICall("POST", `/admin/api/system/config/${key}`, { value: original }, 200);
                });
            }
        });

        it("lists each setting on its own row", () => {
            cy.visit("/finance/statements/settings");
            cy.get("#statementWording textarea[name='sGivingStatementOpening']").then(($first) => {
                cy.get("#statementWording textarea[name='sGivingStatementClosing']").should(($second) => {
                    expect($second[0].getBoundingClientRect().top).to.be.greaterThan($first[0].getBoundingClientRect().bottom - 1);
                });
            });
        });

        it("offers PDF handling as a dropdown", () => {
            cy.visit("/finance/statements/settings");
            cy.get("#statementOutput select[name='iPDFOutputType'] option").should("have.length", 2);
            cy.get("#statementOutput input[name='iPDFOutputType']").should("not.exist");
        });

        it("keeps statement settings out of System Settings", () => {
            cy.visit("/SystemSettings.php");
            for (const key of ["sGivingStatementOpening", "sGivingStatementClosing", "sGivingStatementQuestions", "sTaxSigner", "sChurchTaxId", "iPDFOutputType"]) {
                cy.get(`[name='new_value[${key}]']`).should("not.exist");
            }
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
