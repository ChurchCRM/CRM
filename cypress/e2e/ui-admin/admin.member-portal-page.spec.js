/// <reference types="cypress" />

/**
 * Admin → Member Portal (MP3, #9864).
 *
 * Design: .agents/skills/churchcrm/member-portal-design.md §4 (decision P9).
 *   - administrators only; a staff login without the Admin flag is turned away
 *   - three tabs: Settings, Themes, Statistics
 *   - the four portal ConfigItems carry no System Settings category, so they
 *     must not appear on /SystemSettings.php
 *   - activating a valid theme changes what a member sees; activating a broken
 *     one is refused and the findings are listed
 *   - Developer mode prints the template name in an HTML comment
 *   - a section switch turned off removes that section from the portal
 *
 * The spec writes throwaway theme folders on disk (the webserver container
 * bind-mounts ../src) and removes them again in after().
 */
const GOOD_THEME = "cypressadmingood";
const BROKEN_THEME = "cypressadminbroken";
const GOOD_DIR = `src/Include/themes/${GOOD_THEME}`;
const BROKEN_DIR = `src/Include/themes/${BROKEN_THEME}`;
const HEADER_PURPLE = "rgb(75, 0, 130)";

const MEMBER_USER = "lena.black.editself.notes@exampl";
const MEMBER_PASSWORD = "changeme";

const adminKey = () => Cypress.env("admin.api.key");

/*
 * NOTE: every x-api-key request below replaces the browser session cookie with
 * an API-token session, so a UI step that follows one has to re-establish the
 * browser session first (cy.setupAdminSession() / loginAsMember()).
 */

const setConfig = (name, value) =>
    cy.request({
        method: "POST",
        url: `/admin/api/system/config/${name}`,
        headers: { "content-type": "application/json", "x-api-key": adminKey() },
        body: { value },
        failOnStatusCode: false,
    });

const activateTheme = (name) =>
    cy.request({
        method: "POST",
        url: "/admin/api/member-portal/theme",
        headers: { "content-type": "application/json", "x-api-key": adminKey() },
        body: { name },
        failOnStatusCode: false,
    });

const loginAsMember = () => {
    cy.clearCookies();
    cy.visit("/session/begin");
    cy.get("input[name=User]").type(MEMBER_USER);
    cy.get("input[name=Password]").type(`${MEMBER_PASSWORD}{enter}`);
    cy.url({ timeout: 10000 }).should("include", "/portal");
};

describe("Admin → Member Portal page", () => {
    before(() => {
        // A second, perfectly good theme: colour only.
        cy.writeFile(`${GOOD_DIR}/theme.css`, `:root { --portal-header-bg: ${HEADER_PURPLE}; }\n`);
        cy.writeFile(
            `${GOOD_DIR}/theme.json`,
            JSON.stringify({ name: "Cypress Good", author: "Cypress", description: "A valid throwaway theme" }, null, 2)
        );
        // A theme whose home template does not compile.
        cy.writeFile(
            `${BROKEN_DIR}/templates/home.html.twig`,
            '{% extends "@default/layout.html.twig" %}\n{% block content %}\n'
        );
    });

    after(() => {
        activateTheme("default");
        setConfig("bPortalDeveloperMode", "0");
        setConfig("bPortalShowCalendar", "1");
        setConfig("bPortalShowVolunteer", "1");
        cy.exec(`rm -rf ${GOOD_DIR} ${BROKEN_DIR}`, { failOnNonZeroExit: false });
    });

    describe("Access", () => {
        it("A staff login without the Admin flag is denied", () => {
            cy.setupStandardSession();
            cy.visit("/admin/member-portal");
            cy.url({ timeout: 10000 }).should("include", "/v2/access-denied");
            cy.get("#memberPortalPage").should("not.exist");
        });
    });

    describe("As an administrator", () => {
        beforeEach(() => {
            cy.setupAdminSession();
        });

        it("Shows the Settings, Themes, Statistics and Calendars tabs", () => {
            cy.visit("/admin/member-portal");
            cy.get("#memberPortalTabs").should("be.visible");
            cy.get("#portal-settings-tab").should("contain.text", "Settings");
            cy.get("#portal-themes-tab").should("contain.text", "Themes");
            cy.get("#portal-statistics-tab").should("contain.text", "Statistics");
            // The Calendars tab was MP3's hidden placeholder until MP5 (#9866)
            // filled it in; what it contains is covered by
            // admin.member-portal-calendars.spec.js.
            cy.get("#portal-calendars-tab").should("contain.text", "Calendars");
            cy.get("#portal-calendars-tab-item").should("not.have.class", "d-none");
            cy.get("#portal-calendars").should("exist");
        });

        it("Is reachable from the Admin menu", () => {
            cy.visit("/v2/dashboard");
            cy.get('a[href$="/admin/member-portal"]').should("exist");
        });

        it("Lists every discovered theme with a validation badge", () => {
            cy.visit("/admin/member-portal");
            cy.get("#portal-themes-tab").click();
            cy.get(`#portalThemesTable tr[data-theme="default"]`).should("contain.text", "System default");
            cy.get(`#portalThemesTable tr[data-theme="${GOOD_THEME}"]`)
                .should("contain.text", "Cypress Good")
                .and("contain.text", "Valid");
            cy.get(`#portalThemesTable tr[data-theme="${BROKEN_THEME}"]`).should("contain.text", "Errors");
            cy.get("#portalThemeDisclaimer").should(
                "contain.text",
                "Themes are provided by your church, not by ChurchCRM, and are not verified by the ChurchCRM project."
            );
        });

        it("The four portal settings are absent from the System Settings page", () => {
            cy.visit("/SystemSettings.php");
            for (const name of [
                "sMemberPortalTheme",
                "bPortalDeveloperMode",
                "bPortalShowCalendar",
                "bPortalShowVolunteer",
                "bPortalAllowBirthdayEdit",
            ]) {
                cy.get("body").should("not.contain.text", name);
            }
        });

        it("Refuses a broken theme and lists the findings", () => {
            activateTheme("default");
            cy.setupAdminSession();
            cy.visit("/admin/member-portal");
            cy.get("#portalThemeSelect").select(BROKEN_THEME);
            cy.get("#portalThemeActivateButton").click();
            cy.get("#portalThemeFindings", { timeout: 10000 }).should("contain.text", "home.html.twig");
            cy.get("#portalThemeStatusBadge").should("contain.text", "Errors");
            // Nothing was written: the portal is still on the theme it had.
            cy.request("/admin/api/system/config/sMemberPortalTheme").then((resp) => {
                expect(resp.body.value).to.eq("default");
            });
        });

        it("The Check button re-runs the validator for the selected theme", () => {
            cy.visit("/admin/member-portal");
            cy.get("#portalThemeSelect").select(GOOD_THEME);
            cy.get("#portalThemeCheckButton").click();
            cy.get("#portalThemeFindings", { timeout: 10000 }).should("contain.text", "This theme has no problems.");
            cy.get("#portalThemeStatusBadge").should("contain.text", "Valid");
        });
    });

    describe("What the settings do to the portal", () => {
        it("Activating a valid second theme changes the portal", () => {
            activateTheme(GOOD_THEME).then((resp) => {
                expect(resp.status).to.eq(200);
                expect(resp.body.activated).to.eq(true);
            });
            loginAsMember();
            cy.get(`link[href*="/portal/theme/${GOOD_THEME}/theme.css"]`).should("exist");
            cy.get(".portal-header").should("have.css", "background-color", HEADER_PURPLE);
            activateTheme("default");
        });

        it("Developer mode prints the template name in an HTML comment", () => {
            activateTheme("default");
            setConfig("bPortalDeveloperMode", "1");
            loginAsMember();
            // The comment is the first thing in the response, ahead of the
            // doctype, so read the raw body rather than the parsed document.
            cy.request("/portal/").its("body").should("contain", "<!-- portal template: home.html.twig -->");

            setConfig("bPortalDeveloperMode", "0");
            loginAsMember();
            cy.request("/portal/").its("body").should("not.contain", "<!-- portal template:");
        });

        it("Turning the calendar section off hides its card in the portal", () => {
            activateTheme("default");
            setConfig("bPortalShowCalendar", "1");
            loginAsMember();
            cy.get(".portal-card-calendar").should("exist");

            setConfig("bPortalShowCalendar", "0");
            loginAsMember();
            cy.get(".portal-card-calendar").should("not.exist");
            cy.get(".portal-card-volunteer").should("exist");

            setConfig("bPortalShowCalendar", "1");
        });
    });
});
