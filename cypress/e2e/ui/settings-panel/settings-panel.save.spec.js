/// <reference types="cypress" />

/**
 * Settings Panel — load and Save lifecycle (#9852, #10216)
 *
 * The panel fills its fields from the config API after it renders, and Save
 * posts every field, so the fields and Save stay disabled until every value
 * has loaded (#10216). It also disables its Save button while the config POSTs
 * run (#9852). The Finance dashboard panel is the host: it has a Save button
 * (the People Settings hub auto-saves) and stays on the page after saving.
 * Saving posts the values it just loaded, so nothing changes.
 */
describe("Settings Panel — load and Save", () => {
    beforeEach(() => cy.setupAdminSession());

    const openPanel = () => {
        cy.visit("/finance/");
        cy.contains("Financial Settings").click();
        cy.get("#financialSettings", { timeout: 10000 }).should("have.class", "show").and("not.have.class", "collapsing");
    };

    const openSettings = () => {
        openPanel();
        cy.get("#financialSettings .settings-panel-fields", { timeout: 10000 }).should("not.be.disabled");
    };

    // The held config GETs time out after defaultCommandTimeout, so give them
    // room and keep the disabled checks on the usual 5s.
    it("Finance Settings: fields and Save stay disabled until every value has loaded", { defaultCommandTimeout: 30000 }, () => {
        const held = [];
        cy.intercept("GET", "**/admin/api/system/config/iMaxTaxYears", (req) =>
            new Promise((release) => held.push(release)).then(() => req.continue()),
        );
        cy.intercept("POST", "**/admin/api/system/config/*").as("saveConfig");

        openPanel();
        cy.wrap(held).should("have.length", 1);

        cy.get("#financialSettings .settings-panel-save", { timeout: 5000 }).should("be.disabled");
        cy.get("#financialSettings select[name='iFYMonth']", { timeout: 5000 }).should("be.disabled");
        cy.get("#financialSettings input[name='iMaxTaxYears']", { timeout: 5000 }).should("be.disabled");

        cy.then(() => held.forEach((release) => release()));

        cy.get("#financialSettings .settings-panel-save").should("not.be.disabled");
        cy.get("#financialSettings select[name='iFYMonth']").should("not.be.disabled");
        cy.get("#financialSettings input[name='iMaxTaxYears']").should("not.be.disabled");
        cy.get("#financialSettings .settings-panel-load-error").should("not.exist");
        cy.get("@saveConfig.all").should("have.length", 0);
    });

    it("Finance Settings: a value that fails to load keeps the panel disabled and says so", () => {
        cy.intercept("GET", "**/admin/api/system/config/iMaxTaxYears", {
            statusCode: 500,
            body: { message: "simulated failure" },
        });

        openPanel();
        cy.get("#financialSettings .settings-panel-load-error", { timeout: 10000 })
            .should("be.visible")
            .and("contain.text", "Could not load the current settings");
        cy.get("#financialSettings .settings-panel-save").should("be.disabled");
        cy.get("#financialSettings select[name='iFYMonth']").should("be.disabled");
    });

    it("Finance Settings: Save re-enables the button after a successful save", () => {
        cy.intercept("POST", "**/admin/api/system/config/*").as("saveConfig");

        openSettings();

        cy.get("#financialSettings .settings-panel-save").click();
        cy.wait("@saveConfig");

        // The panel POSTs every setting in parallel and re-enables the button only
        // once all of them settle, so the button is the real synchronisation point.
        cy.get("#financialSettings .settings-panel-save", { timeout: 10000 })
            .should("not.be.disabled")
            .and("contain.text", "Save Settings");

        // By then every POST has completed; all must have succeeded
        cy.get("@saveConfig.all").should("have.length.at.least", 1).then((calls) => {
            for (const call of calls) {
                expect(call.response.statusCode).to.eq(200);
            }
        });
    });

    it("Finance Settings: a failed save re-enables the button and reports the failure", () => {
        cy.intercept("POST", "**/admin/api/system/config/*", {
            statusCode: 500,
            body: { message: "simulated failure" },
        }).as("saveConfigFail");

        openSettings();

        cy.get("#financialSettings .settings-panel-save").click();
        cy.wait("@saveConfigFail");

        cy.get("#financialSettings .settings-panel-save", { timeout: 10000 })
            .should("not.be.disabled")
            .and("contain.text", "Save Settings");
        cy.contains("Failed to save settings").should("exist");
    });
});
