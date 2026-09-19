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
 *   5. a non-administrator is turned away (the admin app's own gate).
 *
 * Order inside every hook is API setup → fresh login → cy.visit(), because
 * cy.request() rotates the PHP session cookie (cypress-testing.md).
 */

const SETTING_URL = "/admin/api/system/config/sVolunteerVersion";
const LEAD_URL = "/admin/api/system/config/iVolunteerReminderLeadHours";
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

    it("is reachable while V1 is active, and switching to V2 here makes Ministries appear", () => {
        adminApi("POST", SETTING_URL, { value: "v1" }, 200);
        freshAdminLogin();
        cy.visit(PAGE_URL);

        // No Ministries heading and no dashboard button in V1 — this page is the way in.
        cy.get('a[href$="/ministries/dashboard"]').should("not.exist");
        cy.get("#ministrySettingsPanel select[name='sVolunteerVersion']").should("have.value", "v1").select("v2");
        cy.get("#ministrySettingsPanel #settingsPanelSaveBtn").click();

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
