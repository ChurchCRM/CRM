/// <reference types="cypress" />

/**
 * Settings Panel Choice Dropdowns
 *
 * Verifies that settings panels with choice-type fields render their
 * dropdown options correctly. Choices come from SystemConfig via PHP
 * json_encode(SystemConfig::getChoices(...)).
 */

describe("Settings Panel — Choice Dropdowns", () => {
    beforeEach(() => cy.setupAdminSession());

    describe("Finance Settings hub — Deposits & Payments", () => {
        let originalTaxYears;
        let originalBillCounts;

        before(() => {
            cy.getSystemConfig("iMaxTaxYears").then((value) => { originalTaxYears = value; });
            cy.getSystemConfig("bDisplayBillCounts").then((value) => { originalBillCounts = value; });
        });

        after(() => {
            cy.restoreSystemConfig("iMaxTaxYears", originalTaxYears);
            cy.restoreSystemConfig("bDisplayBillCounts", originalBillCounts);
        });

        it("auto-saves a number setting and keeps it after reload", () => {
            const next = originalTaxYears === "12" ? "13" : "12";
            cy.intercept("POST", "**/admin/api/system/config/iMaxTaxYears").as("saveTaxYears");
            cy.visit("/admin/finance");
            cy.get("#financeDeposits", { timeout: 10000 }).should("be.visible");

            cy.get("#financeDeposits").within(() => {
                cy.get(".settings-panel-save").should("not.exist");
                cy.contains("First month of the fiscal year");
                cy.contains("Deposit ticket type");
                cy.contains("Maximum tax years shown per family");
                cy.contains("Display bill counts on deposit slip");
            });

            cy.get("#financeDeposits .settings-panel-fields").should("not.be.disabled");
            cy.get("#financeDeposits input[name='iMaxTaxYears']").clear().type(next).blur();
            cy.wait("@saveTaxYears").then(({ request, response }) => {
                expect(request.body.value).to.eq(next);
                expect(response.statusCode).to.eq(200);
            });
            cy.request("/admin/api/system/config/iMaxTaxYears").its("body.value").should("eq", next);
            cy.reload();
            cy.get("#financeDeposits .settings-panel-fields").should("not.be.disabled");
            cy.get("#financeDeposits input[name='iMaxTaxYears']").should("have.value", next);
        });

        it("keeps a failed save visible without reloading", () => {
            cy.intercept("POST", "**/admin/api/system/config/iMaxTaxYears", {
                statusCode: 500,
                body: { message: "simulated failure" },
            }).as("failedSave");
            cy.visit("/admin/finance");
            cy.get("#financeDeposits .settings-panel-fields").should("not.be.disabled");
            cy.clock(null, ["setTimeout", "clearTimeout"]);
            cy.document().as("originalDocument", { type: "static" });
            cy.get("#financeDeposits input[name='iMaxTaxYears']").then(($input) => {
                const previous = $input.val();
                const next = previous === "16" ? "17" : "16";
                cy.wrap($input).clear().type(next).blur();
                cy.wait("@failedSave");
                // The frozen clock also holds the toast's fade-in, so let it finish first.
                cy.contains("Failed to save settings").should("exist");
                cy.tick(500);
                cy.contains("Failed to save settings").should("be.visible");
                cy.get("#financeDeposits input[name='iMaxTaxYears']").should("have.value", previous);
                cy.tick(1500);
                cy.get("@originalDocument").then((doc) => cy.document().should("eq", doc));
                cy.request("/admin/api/system/config/iMaxTaxYears").its("body.value").should("eq", previous);
            });
        });

        it("fiscal year month dropdown has 12 month options", () => {
            cy.visit("/admin/finance");
            cy.get("#financeDeposits", { timeout: 10000 }).should("be.visible");

            cy.get("#financeDeposits").within(() => {
                cy.get("select[name='iFYMonth']").should("exist");
                cy.get("select[name='iFYMonth'] option").should("have.length", 12);
            });
        });

        it("deposit slip type dropdown has QBDT option", () => {
            cy.visit("/admin/finance");
            cy.get("#financeDeposits", { timeout: 10000 }).should("be.visible");

            cy.get("#financeDeposits").within(() => {
                cy.get("select[name='sDepositSlipType']").should("exist");
                cy.get("select[name='sDepositSlipType']").contains("option", "QBDT");
            });
        });

        it("boolean settings render as one switch", () => {
            cy.visit("/admin/finance");
            cy.get("#financeDeposits", { timeout: 10000 }).should("be.visible");

            cy.get("#financeDeposits").within(() => {
                cy.get("input[name='bDisplayBillCounts']").should("have.length", 1).and("have.attr", "type", "checkbox");
            });
        });
    });

    describe("Admin Logs — Log Level", () => {
        it("log level dropdown has severity options from SystemConfig", () => {
            cy.visit("admin/system/logs");
            cy.get("#logSettings", { timeout: 10000 }).should("be.visible");

            cy.get("#logSettings").within(() => {
                cy.get("select[name='sLogLevel']").should("exist");
                cy.get("select[name='sLogLevel'] option").should("have.length", 4);
                cy.get("select[name='sLogLevel']").contains("option", "DEBUG");
                cy.get("select[name='sLogLevel']").contains("option", "INFO");
                cy.get("select[name='sLogLevel']").contains("option", "WARNING");
                cy.get("select[name='sLogLevel']").contains("option", "ERROR");
            });
        });
    });
});
