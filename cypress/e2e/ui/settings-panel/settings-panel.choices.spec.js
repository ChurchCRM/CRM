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

    describe("Email Dashboard — SMTP Encryption", () => {
        it("encryption dropdown has None, TLS, and SSL options", () => {
            cy.visit("v2/email/dashboard");
            cy.contains("Email Settings").click();
            cy.get("#emailSettings", { timeout: 10000 }).should("be.visible");

            cy.get("#emailSettings").within(() => {
                cy.get("select[name='sPHPMailerSMTPSecure']").should("exist");
                cy.get("select[name='sPHPMailerSMTPSecure'] option").should("have.length.at.least", 3);
                cy.get("select[name='sPHPMailerSMTPSecure']").contains("option", "TLS");
                cy.get("select[name='sPHPMailerSMTPSecure']").contains("option", "SSL");
            });
        });
    });

    describe("Finance Dashboard — Settings Panel", () => {
        it("admin can open financial settings panel", () => {
            cy.visit("/finance/");
            cy.contains("Finance Dashboard", { timeout: 10000 });
            cy.contains("Financial Settings").click();
            cy.get("#financialSettings", { timeout: 10000 }).should("be.visible");

            cy.get("#financialSettings").within(() => {
                cy.contains("First month of the fiscal year");
                cy.contains("Deposit ticket type");
                cy.contains("Number of checks for Deposit Slip Report");
                cy.contains("Display bill counts on deposit slip");
            });
        });

        it("fiscal year month dropdown has 12 month options", () => {
            cy.visit("/finance/");
            cy.contains("Finance Dashboard", { timeout: 10000 });
            cy.contains("Financial Settings").click();
            cy.get("#financialSettings", { timeout: 10000 }).should("be.visible");

            cy.get("#financialSettings").within(() => {
                cy.get("select[name='iFYMonth']").should("exist");
                cy.get("select[name='iFYMonth'] option").should("have.length", 12);
            });
        });

        it("deposit slip type dropdown has QBDT option", () => {
            cy.visit("/finance/");
            cy.contains("Finance Dashboard", { timeout: 10000 });
            cy.contains("Financial Settings").click();
            cy.get("#financialSettings", { timeout: 10000 }).should("be.visible");

            cy.get("#financialSettings").within(() => {
                cy.get("select[name='sDepositSlipType']").should("exist");
                cy.get("select[name='sDepositSlipType']").contains("option", "QBDT");
            });
        });

        it("boolean settings render as Yes/No pill toggles", () => {
            cy.visit("/finance/");
            cy.contains("Finance Dashboard", { timeout: 10000 });
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

    describe("Map View — Zoom Level", () => {
        it("zoom level dropdown has geographic scale options", () => {
            cy.visit("v2/map");
            // Map settings may not render if church address is not geocoded;
            // skip gracefully if the settings panel container doesn't exist
            cy.get("body").then(($body) => {
                if ($body.find("#mapAdminSettings").length === 0) {
                    cy.log("Map settings panel not rendered (church address may not be geocoded) — skipping");
                    return;
                }

                cy.get("#mapAdminSettings", { timeout: 10000 }).should("exist");
                cy.get("#mapAdminSettings").within(() => {
                    cy.get("select[name='iMapZoom']").should("exist");
                    cy.get("select[name='iMapZoom'] option").should("have.length.at.least", 5);
                });
            });
        });
    });
});
