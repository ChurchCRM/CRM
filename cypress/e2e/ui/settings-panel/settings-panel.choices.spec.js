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

    describe("Finance Dashboard — Settings Panel", () => {
        let originalChecks;
        let originalBillCounts;

        before(() => {
            cy.getSystemConfig("iChecksPerDepositForm").then((value) => { originalChecks = value; });
            cy.getSystemConfig("bDisplayBillCounts").then((value) => { originalBillCounts = value; });
        });

        after(() => {
            cy.restoreSystemConfig("iChecksPerDepositForm", originalChecks);
            cy.restoreSystemConfig("bDisplayBillCounts", originalBillCounts);
        });

        it("a treasurer auto-saves the deposit slip check count", () => {
            const next = originalChecks === "12" ? "13" : "12";
            cy.intercept("POST", "**/admin/api/system/config/iChecksPerDepositForm").as("saveChecks");
            cy.visit("/finance/");
            cy.contains("Financial Settings").click();
            cy.get("#financialSettings", { timeout: 10000 }).should("be.visible");

            cy.get("#financialSettings").within(() => {
                cy.get(".settings-panel-save").should("not.exist");
                cy.contains("First month of the fiscal year");
                cy.contains("Deposit ticket type");
                cy.contains("Number of checks for Deposit Slip Report");
                cy.contains("Display bill counts on deposit slip");
            });

            cy.get("#financialSettings .settings-panel-fields").should("not.be.disabled");
            cy.get("#financialSettings input[name='iChecksPerDepositForm']").clear().type(next).blur();
            cy.wait("@saveChecks").then(({ request, response }) => {
                expect(request.body.value).to.eq(next);
                expect(response.statusCode).to.eq(200);
            });
            cy.request("/admin/api/system/config/iChecksPerDepositForm").its("body.value").should("eq", next);
            cy.reload();
            cy.contains("Financial Settings").click();
            cy.get("#financialSettings .settings-panel-fields").should("not.be.disabled");
            cy.get("#financialSettings input[name='iChecksPerDepositForm']").should("have.value", next);
        });

        it("waits for overlapping saves before reloading", () => {
            let release;
            const nextChecks = originalChecks === "14" ? "15" : "14";
            const nextBills = originalBillCounts === "1" ? "0" : "1";
            cy.intercept("POST", "**/admin/api/system/config/iChecksPerDepositForm").as("saveChecks");
            cy.intercept("POST", "**/admin/api/system/config/bDisplayBillCounts", (req) =>
                new Promise((resolve) => { release = resolve; }).then(() => req.continue()),
            ).as("saveBills");
            cy.visit("/finance/");
            cy.contains("Financial Settings").click();
            cy.get("#financialSettings .settings-panel-fields").should("not.be.disabled");
            cy.clock(null, ["setTimeout", "clearTimeout"]);
            cy.document().as("originalDocument", { type: "static" });
            cy.get("#financialSettings input[name='iChecksPerDepositForm']").clear().type(nextChecks).blur();
            cy.wait("@saveChecks").its("response.statusCode").should("eq", 200);
            cy.get(`#financialSettings input[name='bDisplayBillCounts'][value='${nextBills}']`).parent("label").click();
            cy.get("#financialSettings").contains("Display bill counts on deposit slip").click();
            cy.wrap(null).should(() => expect(release).to.be.a("function"));
            cy.tick(2000);
            cy.get("@originalDocument").then((doc) => cy.document().should("eq", doc));
            cy.then(() => release());
            cy.wait("@saveBills").its("response.statusCode").should("eq", 200);
            cy.request("/admin/api/system/config/iChecksPerDepositForm").its("body.value").should("eq", nextChecks);
            cy.request("/admin/api/system/config/bDisplayBillCounts").its("body.value").should("eq", nextBills);
            cy.tick(2000);
            cy.get("@originalDocument").then((doc) => cy.document().should("not.eq", doc));
            cy.contains("Financial Settings").click();
            cy.get("#financialSettings .settings-panel-fields").should("not.be.disabled");
            cy.get("#financialSettings input[name='iChecksPerDepositForm']").should("have.value", nextChecks);
            cy.get(`#financialSettings input[name='bDisplayBillCounts'][value='${nextBills}']`).should("be.checked");
        });

        it("keeps a failed save visible without reloading", () => {
            cy.intercept("POST", "**/admin/api/system/config/iChecksPerDepositForm", {
                statusCode: 500,
                body: { message: "simulated failure" },
            }).as("failedSave");
            cy.visit("/finance/");
            cy.contains("Financial Settings").click();
            cy.get("#financialSettings .settings-panel-fields").should("not.be.disabled");
            cy.clock(null, ["setTimeout", "clearTimeout"]);
            cy.document().as("originalDocument", { type: "static" });
            cy.get("#financialSettings input[name='iChecksPerDepositForm']").then(($input) => {
                const previous = $input.val();
                const next = previous === "16" ? "17" : "16";
                cy.wrap($input).clear().type(next).blur();
                cy.wait("@failedSave");
                // The frozen clock also holds the toast's fade-in, so let it finish first.
                cy.contains("Failed to save settings").should("exist");
                cy.tick(500);
                cy.contains("Failed to save settings").should("be.visible");
                cy.get("#financialSettings input[name='iChecksPerDepositForm']").should("have.value", previous);
                cy.tick(1500);
                cy.get("@originalDocument").then((doc) => cy.document().should("eq", doc));
                cy.request("/admin/api/system/config/iChecksPerDepositForm").its("body.value").should("eq", previous);
            });
        });

        it("fiscal year month dropdown has 12 month options", () => {
            cy.visit("/finance/");
            cy.contains("Financial Settings").click();
            cy.get("#financialSettings", { timeout: 10000 }).should("be.visible");

            cy.get("#financialSettings").within(() => {
                cy.get("select[name='iFYMonth']").should("exist");
                cy.get("select[name='iFYMonth'] option").should("have.length", 12);
            });
        });

        it("deposit slip type dropdown has QBDT option", () => {
            cy.visit("/finance/");
            cy.contains("Financial Settings").click();
            cy.get("#financialSettings", { timeout: 10000 }).should("be.visible");

            cy.get("#financialSettings").within(() => {
                cy.get("select[name='sDepositSlipType']").should("exist");
                cy.get("select[name='sDepositSlipType']").contains("option", "QBDT");
            });
        });

        it("boolean settings render as Yes/No pill toggles", () => {
            cy.visit("/finance/");
            cy.contains("Financial Settings").click();
            cy.get("#financialSettings", { timeout: 10000 }).should("be.visible");

            cy.get("#financialSettings").within(() => {
                // Each boolean setting should have two radio inputs (Yes/No)
                cy.get("input[name='bDisplayBillCounts'][value='1']").should("exist");
                cy.get("input[name='bDisplayBillCounts'][value='0']").should("exist");
            });
        });
    });

    describe("Admin Logs — Log Level", () => {
        it("log level dropdown has severity options from SystemConfig", () => {
            cy.visit("admin/system/logs");
            // The header button labeled "Settings" toggles the #logSettings collapse
            cy.get("[data-bs-target='#logSettings']").click();
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
