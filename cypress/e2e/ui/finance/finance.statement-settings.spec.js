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

        it("shows a charity-details notice until the receipt fields are set", () => {
            const keys = ["sCharityLegalName", "sCharityRegisteredAddress", "sCharityRegistrationNumber", "sReceiptIssueLocality"];
            keys.forEach((key) => cy.setSystemConfig(key, ""));
            cy.visit("/finance/");
            cy.get("#charity-details-banner").should("be.visible");
            cy.get("#year-end-entry a.btn-primary").should("be.visible");
            keys.forEach((key) => cy.setSystemConfig(key, "Sample charity"));
            cy.reload();
            cy.get("#charity-details-banner").should("not.exist");
            cy.visit("/finance/statements/settings");
            cy.get("#statementTax input[name='sCharityLegalName']").should("have.value", "Sample charity");
            keys.forEach((key) => cy.setSystemConfig(key, ""));
        });

        it("lets each user dismiss the charity-details notice", () => {
            const dismissKey = "notification.dismissed.charity-details";
            cy.setSystemConfig("sCharityLegalName", "");
            cy.visit("/finance/");
            cy.window().its("CRM.userId").then((userId) => {
                const resetDismissal = () => cy.request("POST", `/api/user/${userId}/setting/${dismissKey}`, { value: "" });
                resetDismissal();
                cy.reload();
                cy.intercept("POST", `**/api/user/${userId}/setting/${dismissKey}`).as("dismiss");
                cy.get("#charity-details-banner .js-dismiss-notification").click();
                cy.wait("@dismiss").its("response.statusCode").should("eq", 200);
                cy.reload();
                cy.get("#year-end-entry a.btn-primary").should("be.visible");
                cy.get("#charity-details-banner").should("not.exist");
                resetDismissal();
                cy.reload();
                cy.get("#charity-details-banner").should("be.visible");
            });
        });

        it("rejects a statement setting save without the session CSRF token", () => {
            cy.request({ method: "POST", url: "/finance/api/config/sTaxSigner", body: { value: "No token" }, failOnStatusCode: false })
                .its("status")
                .should("eq", 403);
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
            cy.get("#statementTax textarea[name='sCharityRegisteredAddress']").should("be.visible");
            cy.get("#statementTax input[name='sCharityLegalName']").should("be.visible");
            cy.get("#statementTax input[name='bCanadianGivingStatement']").should("exist");
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
