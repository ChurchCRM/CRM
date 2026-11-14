/// <reference types="cypress" />

/**
 * Settings Panel — load and Save lifecycle (#9852, #10216)
 *
 * The panel fills its fields from the config API after it renders, and Save
 * posts every field, so the fields and Save stay disabled until every value
 * has loaded (#10216). It also disables its Save button while the config POSTs
 * run (#9852). Ministry Settings keeps a Save button: those timing fields apply
 * together and the page reloads. Saving posts the values it just loaded.
 */
const VERSION_URL = "/admin/api/system/config/sVolunteerVersion";
let originalVersion;

before(() => {
    cy.rememberTestEnv(["admin.username", "admin.password"]);
});

describe("Settings Panel — load and Save", () => {
    // Ministry Settings only renders while Volunteer V2 is on.
    before(() => {
        cy.makePrivateAdminAPICall("GET", VERSION_URL, null, 200).then((resp) => {
            originalVersion = String(resp.body.value);
        });
        cy.makePrivateAdminAPICall("POST", VERSION_URL, { value: "v2" }, 200);
    });

    after(() => {
        cy.restoreSystemConfig("sVolunteerVersion", originalVersion);
    });

    beforeEach(() => cy.setupAdminSession());

    const openPanel = () => {
        cy.visit("/admin/ministry-settings");
        cy.get("#ministrySettingsPanel", { timeout: 10000 }).should("be.visible");
    };

    const openSettings = () => {
        openPanel();
        cy.get("#ministrySettingsPanel .settings-panel-fields", { timeout: 10000 }).should("not.be.disabled");
    };

    // The held config GETs time out after defaultCommandTimeout, so give them
    // room and keep the disabled checks on the usual 5s.
    it("Ministry Settings: fields and Save stay disabled until every value has loaded", { defaultCommandTimeout: 30000 }, () => {
        const held = [];
        cy.intercept("GET", "**/admin/api/system/config/iVolunteerReminderLeadHours", (req) =>
            new Promise((release) => held.push(release)).then(() => req.continue()),
        );
        cy.intercept("POST", "**/admin/api/system/config/*").as("saveConfig");

        openPanel();
        cy.wrap(held).should("have.length", 1);

        cy.get("#ministrySettingsPanel .settings-panel-save", { timeout: 5000 }).should("be.disabled");
        cy.get("#ministrySettingsPanel input[name='iVolunteerReminderLeadHours']", { timeout: 5000 }).should("be.disabled");
        cy.get("#ministrySettingsPanel input[name='iVolunteerSchedulingHorizonWeeks']", { timeout: 5000 }).should("be.disabled");

        cy.then(() => held.forEach((release) => release()));

        cy.get("#ministrySettingsPanel .settings-panel-save").should("not.be.disabled");
        cy.get("#ministrySettingsPanel input[name='iVolunteerReminderLeadHours']").should("not.be.disabled");
        cy.get("#ministrySettingsPanel input[name='iVolunteerSchedulingHorizonWeeks']").should("not.be.disabled");
        cy.get("#ministrySettingsPanel .settings-panel-load-error").should("not.exist");
        cy.get("@saveConfig.all").should("have.length", 0);
    });

    it("Ministry Settings: a value that fails to load keeps the panel disabled and says so", () => {
        cy.intercept("GET", "**/admin/api/system/config/iVolunteerReminderLeadHours", {
            statusCode: 500,
            body: { message: "simulated failure" },
        });

        openPanel();
        cy.get("#ministrySettingsPanel .settings-panel-load-error", { timeout: 10000 })
            .should("be.visible")
            .and("contain.text", "Could not load the current settings");
        cy.get("#ministrySettingsPanel .settings-panel-save").should("be.disabled");
        cy.get("#ministrySettingsPanel input[name='iVolunteerSchedulingHorizonWeeks']").should("be.disabled");
    });

    it("Ministry Settings: Save re-enables the button after a successful save", () => {
        cy.intercept("POST", "**/admin/api/system/config/*").as("saveConfig");

        openSettings();

        cy.get("#ministrySettingsPanel .settings-panel-save").click();
        cy.wait("@saveConfig");

        // The panel POSTs every setting in parallel and re-enables the button only
        // once all of them settle, so the button is the real synchronisation point.
        cy.get("#ministrySettingsPanel .settings-panel-save", { timeout: 10000 })
            .should("not.be.disabled")
            .and("contain.text", "Save Settings");

        // By then every POST has completed; all must have succeeded
        cy.get("@saveConfig.all").should("have.length.at.least", 1).then((calls) => {
            for (const call of calls) {
                expect(call.response.statusCode).to.eq(200);
            }
        });
    });

    it("Ministry Settings: a failed save re-enables the button and reports the failure", () => {
        cy.intercept("POST", "**/admin/api/system/config/*", {
            statusCode: 500,
            body: { message: "simulated failure" },
        }).as("saveConfigFail");

        openSettings();

        cy.get("#ministrySettingsPanel .settings-panel-save").click();
        cy.wait("@saveConfigFail");

        cy.get("#ministrySettingsPanel .settings-panel-save", { timeout: 10000 })
            .should("not.be.disabled")
            .and("contain.text", "Save Settings");
        cy.contains("Failed to save settings").should("exist");
    });
});
