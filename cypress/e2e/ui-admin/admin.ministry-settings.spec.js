/// <reference types="cypress" />

/**
 * Admin → Ministry Settings (epic #9701; product-owner decision 2026-09-18).
 *
 * The rollout state and the reminder lead time have ONE home, this page, in
 * every rollout state — the Ministry Dashboard they used to sit on only exists
 * once V2 is on. Proved here:
 *
 *   1. the page is in the Admin menu and renders its three cards, including
 *      the V1 / V2 / Both explanation;
 *   2. it is reachable while V1 is active, and saving V2 from it makes the
 *      Ministries heading appear;
 *   3. the two settings are no longer on the System Settings page;
 *   4. the Ministry Dashboard has no settings strip any more, only the
 *      notification card with a link back here;
 *   5. a non-administrator is turned away (the admin app's own gate);
 *   6. D31: the scheduling horizon and the default event type are set here,
 *      and the last schedule top-up is shown with what it made.
 *
 * Order inside every hook is API setup → fresh login → cy.visit(), because
 * cy.request() rotates the PHP session cookie (cypress-testing.md).
 */

const SETTING_URL = "/admin/api/system/config/sVolunteerVersion";
const LEAD_URL = "/admin/api/system/config/iVolunteerReminderLeadHours";
const HORIZON_URL = "/admin/api/system/config/iVolunteerSchedulingHorizonWeeks";
const DEFAULT_TYPE_URL = "/admin/api/system/config/iVolunteerDefaultEventTypeId";
const PAGE_URL = "/admin/ministry-settings";

let originalVersion = "v1";
let originalLead = "48";

function freshAdminLogin() {
    cy.clearCookies();
    cy.visit("/session/begin");
    cy.get("input[name=User]").type(Cypress.env("admin.username"));
    cy.get("input[name=Password]").type(Cypress.env("admin.password") + "{enter}");
    // One retry: under CI load the submit occasionally lands back on the login
    // page although the server logged the login (a lost cookie on the redirect).
    cy.url().then((url) => {
        if (url.includes("/session/begin")) {
            cy.get("input[name=User]").clear().type(Cypress.env("admin.username"));
            cy.get("input[name=Password]").clear().type(Cypress.env("admin.password") + "{enter}");
        }
    });
    cy.url().should("not.include", "/session/begin");
}

function freshStandardLogin() {
    cy.clearCookies();
    cy.visit("/session/begin");
    cy.get("input[name=User]").type(Cypress.env("standard.username"));
    cy.get("input[name=Password]").type(Cypress.env("standard.password") + "{enter}");
    // One retry: under CI load the submit occasionally lands back on the login
    // page although the server logged the login (a lost cookie on the redirect).
    cy.url().then((url) => {
        if (url.includes("/session/begin")) {
            cy.get("input[name=User]").clear().type(Cypress.env("standard.username"));
            cy.get("input[name=Password]").clear().type(Cypress.env("standard.password") + "{enter}");
        }
    });
    cy.url().should("not.include", "/session/begin");
}

function adminApi(method, url, body, expectedStatus = 200) {
    return cy.makePrivateAdminAPICall(method, url, body, expectedStatus);
}

describe("Admin → Ministry Settings", () => {
    before(() => {
        adminApi("GET", SETTING_URL, null, 200).then((resp) => {
            originalVersion = String(resp.body.value ?? resp.body.Value ?? "v1");
        });
        adminApi("GET", LEAD_URL, null, 200).then((resp) => {
            originalLead = String(resp.body.value ?? resp.body.Value ?? "48");
        });
    });

    after(() => {
        adminApi("POST", SETTING_URL, { value: originalVersion }, 200);
        adminApi("POST", LEAD_URL, { value: originalLead }, 200);
        adminApi("POST", HORIZON_URL, { value: "8" }, 200);
        adminApi("POST", DEFAULT_TYPE_URL, { value: "" }, 200);
    });

    it("is in the Admin menu and renders the settings, the explanation and delivery health", () => {
        adminApi("POST", SETTING_URL, { value: "v2" }, 200);
        freshAdminLogin();
        cy.visit(PAGE_URL);

        cy.get('a[href$="/admin/ministry-settings"]').should("exist");
        cy.get(".page-title, h2").should("contain", "Ministry Settings");
        cy.get("#ministrySettingsPanel select[name='sVolunteerVersion']").should("have.value", "v2");
        cy.get("#ministrySettingsPanel input[name='iVolunteerReminderLeadHours']").should("have.value", originalLead);

        cy.get("#ministry-experience-card")
            .should("contain", "V1")
            .and("contain", "Volunteer Opportunities")
            .and("contain", "V2")
            .and("contain", "Ministries")
            .and("contain", "Both");

        cy.get("#ministry-delivery-card").should("be.visible");
        cy.get("#ministry-failed-count").should("exist");
        cy.get("#ministry-pending-count").should("exist");
        cy.get("#ministry-cron-hint").should("contain", "timerjobs");
        // "Run background jobs now" forces a run past the minimum interval and
        // reloads with a fresh "last ran" time (2026-09-18).
        cy.intercept("POST", "**/api/background/timerjobs").as("run");
        cy.get("#ministry-run-jobs-btn").should("be.visible").click();
        cy.wait("@run").its("response.body.ran").should("eq", true);
        cy.get("#ministry-last-run", { timeout: 10000 }).should("not.contain", "never");
        // V2 is on, so the header offers the dashboard.
        cy.get('a[href$="/ministries/dashboard"]').should("exist");
    });

    it("sets the scheduling horizon and the default event type, and shows the last top-up (D31)", () => {
        adminApi("POST", SETTING_URL, { value: "v2" }, 200);
        adminApi("POST", HORIZON_URL, { value: "8" }, 200);
        adminApi("POST", DEFAULT_TYPE_URL, { value: "" }, 200);
        cy.dbQuery("DELETE FROM config_cfg WHERE cfg_name IN ('sLastVolunteerTopUpRunDate', 'sLastVolunteerTopUpResult')");
        freshAdminLogin();
        cy.visit(PAGE_URL);

        cy.get("#ministrySettingsPanel input[name='iVolunteerSchedulingHorizonWeeks']")
            .should("have.value", "8")
            .and("have.attr", "min", "1")
            .and("have.attr", "max", "52");
        cy.get("#ministrySettingsPanel select[name='iVolunteerDefaultEventTypeId']").should("have.value", "");
        cy.get("#ministrySettingsPanel select[name='iVolunteerDefaultEventTypeId'] option").first().should("have.text", 'Not set: use "Other"');
        cy.get("#ministrySettingsPanel select[name='iVolunteerDefaultEventTypeId']")
            .should("contain", "Church Service")
            .and("contain", "Other")
            .and("contain", "Sunday School");
        cy.get("#ministry-topup-last-run").should("have.text", "never");
        cy.get("#ministry-topup-hint").should("contain", "up to 8 weeks ahead").and("contain", "default volunteer");

        cy.get("#ministrySettingsPanel input[name='iVolunteerSchedulingHorizonWeeks']").should("be.enabled").clear().type("10");
        cy.get("#ministrySettingsPanel select[name='iVolunteerDefaultEventTypeId']").select("Church Service");
        cy.get("#ministrySettingsPanel .settings-panel-save").click();
        cy.get("#ministry-topup-hint", { timeout: 10000 }).should("contain", "up to 10 weeks ahead");
        adminApi("GET", HORIZON_URL, null, 200).its("body.value").should("eq", "10");
        adminApi("GET", DEFAULT_TYPE_URL, null, 200).its("body.value").should("eq", "1");

        freshAdminLogin();
        // The page's own footer posts the same endpoint on load, rate limited; wait for the button's.
        cy.intercept("POST", "**/api/background/timerjobs", (req) => {
            if (JSON.stringify(req.body ?? "").includes("force")) {
                req.alias = "forcedRun";
            }
        });
        cy.visit(PAGE_URL);
        cy.get("#ministry-run-jobs-btn").click();
        cy.wait("@forcedRun").its("response.body.ran").should("eq", true);
        cy.get("#ministry-topup-last-run", { timeout: 10000 }).should("not.have.text", "never");
        cy.get("#ministry-topup-created").invoke("text").should("match", /— \d+ new occurrences?$/);
    });

    /** The reminder lead time arrives late, as on a slow connection. */
    function slowLeadTime() {
        cy.intercept("GET", "**/admin/api/system/config/iVolunteerReminderLeadHours", (req) => {
            req.on("response", (res) => {
                res.setDelay(2500);
            });
        }).as("leadValue");
    }

    it("offers no Save before the current values have loaded, so nothing is sent (D32)", () => {
        adminApi("POST", SETTING_URL, { value: "v2" }, 200);
        adminApi("POST", LEAD_URL, { value: "36" }, 200);
        freshAdminLogin();
        slowLeadTime();
        cy.intercept("POST", "**/admin/api/system/config/*").as("saveSetting");
        cy.visit(PAGE_URL);

        cy.get("#ministrySettingsPanel input[name='iVolunteerReminderLeadHours']").should("have.value", "");
        cy.get("#ministrySettingsPanel .settings-panel-save").should("be.disabled");

        cy.wait("@leadValue");
        cy.get("#ministrySettingsPanel input[name='iVolunteerReminderLeadHours']").should("have.value", "36");
        cy.then(() => {
            adminApi("GET", LEAD_URL, null, 200).its("body.value").should("eq", "36");
        });
        cy.get("@saveSetting.all").should("have.length", 0);
    });

    it("keeps Save disabled until the current values have loaded (D32)", () => {
        adminApi("POST", SETTING_URL, { value: "v2" }, 200);
        freshAdminLogin();
        slowLeadTime();
        cy.visit(PAGE_URL);

        cy.get("#ministrySettingsPanel .settings-panel-save").should("be.disabled");
        // A field changed now would be overwritten by the value on its way.
        cy.get("#ministrySettingsPanel input[name='iVolunteerSchedulingHorizonWeeks']").should("be.disabled");
        cy.wait("@leadValue");
        cy.get("#ministrySettingsPanel .settings-panel-save").should("be.enabled");
        cy.get("#ministrySettingsPanel input[name='iVolunteerSchedulingHorizonWeeks']").should("be.enabled");
    });

    it("keeps Save disabled and says so when a current value cannot be loaded (D32)", () => {
        adminApi("POST", SETTING_URL, { value: "v2" }, 200);
        freshAdminLogin();
        cy.intercept("GET", "**/admin/api/system/config/iVolunteerSchedulingHorizonWeeks", {
            statusCode: 500,
            body: { message: "boom" },
        }).as("horizonValue");
        cy.visit(PAGE_URL);

        cy.wait("@horizonValue");
        cy.get("#ministrySettingsPanel .settings-panel-load-error").should("be.visible");
        cy.get("#ministrySettingsPanel .settings-panel-save").should("be.disabled");
    });

    it("shows what the last top-up assigned and the defaults it skipped (D32)", () => {
        adminApi("POST", SETTING_URL, { value: "v2" }, 200);
        cy.dbQuery("REPLACE INTO config_cfg (cfg_name, cfg_value) VALUES ('sLastVolunteerTopUpResult', ?)", [
            JSON.stringify({ ranAt: "2026-09-30 06:00:00", schedules: 3, created: 5, failed: 0, assigned: 4, skipped: 1, unqualified: 2 }),
        ]);
        freshAdminLogin();
        cy.visit(PAGE_URL);

        // Shown in ChurchCRM's locale; the stored value stays on the <time> element.
        cy.get("#ministry-topup-last-run time")
            .should("have.attr", "datetime", "2026-09-30 06:00:00")
            .and("contain", "6:00")
            .and("not.contain", "2026-09-30");
        cy.get("#ministry-topup-created").should("contain", "5 new occurrences");
        cy.get("#ministry-topup-assigned").should("contain", "4 default volunteers assigned");
        cy.get("#ministry-topup-unqualified").should("contain", "2 defaults skipped: qualification revoked");
        cy.get("#ministry-topup-skipped").should("contain", "1 default skipped for another reason");
        cy.get("#ministry-topup-hint").should("contain", "default volunteer");
        cy.dbQuery("DELETE FROM config_cfg WHERE cfg_name = 'sLastVolunteerTopUpResult'");
    });

    it("is reachable while V1 is active, and switching to V2 here makes Ministries appear", () => {
        adminApi("POST", SETTING_URL, { value: "v1" }, 200);
        freshAdminLogin();
        cy.visit(PAGE_URL);

        // No Ministries heading and no dashboard button in V1 — this page is the way in.
        cy.get('a[href$="/ministries/dashboard"]').should("not.exist");
        cy.get("#ministrySettingsPanel select[name='sVolunteerVersion']").should("be.enabled").and("have.value", "v1").select("v2");
        cy.get("#ministrySettingsPanel .settings-panel-save").click();

        // onSave reloads the page; the sidebar and the header button follow.
        cy.get('a[href$="/ministries/dashboard"]', { timeout: 10000 }).should("exist");
        cy.get("#ministrySettingsPanel select[name='sVolunteerVersion']").should("have.value", "v2");
    });

    it("has taken both settings off the System Settings page", () => {
        freshAdminLogin();
        cy.visit("/SystemSettings.php");
        cy.get('select[name="new_value[sVolunteerVersion]"]').should("not.exist");
        cy.get('input[name="new_value[iVolunteerReminderLeadHours]"]').should("not.exist");
    });

    it("leaves the Ministry Dashboard with the notification card and a link here, not a settings strip", () => {
        adminApi("POST", SETTING_URL, { value: "v2" }, 200);
        freshAdminLogin();
        cy.visit("/ministries/dashboard");
        cy.get("#volunteerSettings").should("not.exist");
        cy.get("#volunteer-cron-hint").should("not.exist");
        cy.get("#volunteer-failed-card").should("be.visible");
        cy.get("#volunteer-settings-link").should("have.attr", "href").and("match", /\/admin\/ministry-settings$/);
    });

    it("turns a non-administrator away", () => {
        freshStandardLogin();
        cy.request({ url: PAGE_URL, followRedirect: false, failOnStatusCode: false }).then((resp) => {
            expect(resp.status).to.be.oneOf([302, 403]);
            if (resp.status === 302) {
                expect(resp.headers.location).to.include("access-denied");
            }
        });
    });
});
