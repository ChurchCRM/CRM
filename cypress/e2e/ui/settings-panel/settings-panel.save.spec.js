/// <reference types="cypress" />

/**
 * Settings Panel — load and Save lifecycle (#9852, #10216)
 *
 * The panel fills its fields from the config API after it renders, and Save
 * posts every field, so the fields and Save stay disabled until every value
 * has loaded (#10216). It also disables its Save button while the config POSTs
 * run (#9852). The Email dashboard panel is the host: it has a Save button
 * (the People Settings hub auto-saves) and stays on the page after saving.
 * Saving posts the values it just loaded, so nothing changes.
 */
describe("Settings Panel — load and Save", () => {
    beforeEach(() => cy.setupAdminSession());

    const openSettings = () => {
        cy.contains("Email Settings").click();
        cy.get("#emailSettings", { timeout: 10000 }).should("have.class", "show").and("not.have.class", "collapsing");
        cy.get("#emailSettings .settings-panel-fields", { timeout: 10000 }).should("not.be.disabled");
    };

    // The held config GETs time out after defaultCommandTimeout, so give them
    // room and keep the disabled checks on the usual 5s.
    it("Email Settings: fields and Save stay disabled until every value has loaded", { defaultCommandTimeout: 30000 }, () => {
        const held = [];
        cy.intercept("GET", "**/admin/api/system/config/sSMTPHost", (req) =>
            new Promise((release) => held.push(release)).then(() => req.continue()),
        );
        cy.intercept("POST", "**/admin/api/system/config/*").as("saveConfig");

        cy.visit("v2/email/dashboard");
        cy.contains("Email Settings").click();
        cy.get("#emailSettings", { timeout: 10000 }).should("have.class", "show").and("not.have.class", "collapsing");
        cy.wrap(held).should("have.length", 1);

        cy.get("#emailSettings .settings-panel-save", { timeout: 5000 }).should("be.disabled");
        cy.get("#emailSettings select[name='sPHPMailerSMTPSecure']", { timeout: 5000 }).should("be.disabled");
        cy.get("#emailSettings input[name='sSMTPHost']", { timeout: 5000 }).should("be.disabled");

        cy.then(() => held.forEach((release) => release()));

        cy.get("#emailSettings .settings-panel-save").should("not.be.disabled");
        cy.get("#emailSettings select[name='sPHPMailerSMTPSecure']").should("not.be.disabled");
        cy.get("#emailSettings input[name='sSMTPHost']").should("not.be.disabled");
        cy.get("#emailSettings .settings-panel-load-error").should("not.exist");
        cy.get("@saveConfig.all").should("have.length", 0);
    });

    it("Email Settings: a value that fails to load keeps the panel disabled and says so", () => {
        cy.intercept("GET", "**/admin/api/system/config/sSMTPHost", {
            statusCode: 500,
            body: { message: "simulated failure" },
        });

        cy.visit("v2/email/dashboard");
        cy.contains("Email Settings").click();
        cy.get("#emailSettings .settings-panel-load-error", { timeout: 10000 })
            .should("be.visible")
            .and("contain.text", "Could not load the current settings");
        cy.get("#emailSettings .settings-panel-save").should("be.disabled");
        cy.get("#emailSettings select[name='sPHPMailerSMTPSecure']").should("be.disabled");
    });

    it("Email Settings: Save re-enables the button after a successful save", () => {
        cy.intercept("POST", "**/admin/api/system/config/*").as("saveConfig");

        cy.visit("v2/email/dashboard");
        openSettings();

        cy.get("#emailSettings .settings-panel-save").click();
        cy.wait("@saveConfig");

        // The panel POSTs every setting in parallel and re-enables the button only
        // once all of them settle, so the button is the real synchronisation point.
        cy.get("#emailSettings .settings-panel-save", { timeout: 10000 })
            .should("not.be.disabled")
            .and("contain.text", "Save Settings");

        // By then every POST has completed; all must have succeeded
        cy.get("@saveConfig.all").should("have.length.at.least", 1).then((calls) => {
            for (const call of calls) {
                expect(call.response.statusCode).to.eq(200);
            }
        });
    });

    it("Email Settings: a failed save re-enables the button and reports the failure", () => {
        cy.intercept("POST", "**/admin/api/system/config/*", {
            statusCode: 500,
            body: { message: "simulated failure" },
        }).as("saveConfigFail");

        cy.visit("v2/email/dashboard");
        openSettings();

        cy.get("#emailSettings .settings-panel-save").click();
        cy.wait("@saveConfigFail");

        cy.get("#emailSettings .settings-panel-save", { timeout: 10000 })
            .should("not.be.disabled")
            .and("contain.text", "Save Settings");
        cy.contains("Failed to save settings").should("exist");
    });
});
