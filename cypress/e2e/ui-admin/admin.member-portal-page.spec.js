/// <reference types="cypress" />

import { contrastRatio, effectiveBackground } from "../../support/colour";

/**
 * Admin → Member Portal (MP3, #9864).
 *
 * Design: .agents/skills/churchcrm/member-portal-design.md §4 (decision P9).
 *   - administrators only; a staff login without the Admin flag is turned away
 *   - reached through People Settings; Statistics is the first tab
 *   - the portal ConfigItems carry no System Settings category, so they
 *     must not appear on /SystemSettings.php; each one lives in the tab it
 *     affects (Themes, Permissions, Directory) and saves from there
 *   - activating a valid theme changes what a member sees; activating a broken
 *     one is refused and the findings are listed under its row
 *   - Developer mode prints the template name in an HTML comment
 *   - a section switch turned off removes that section from the portal, and
 *     sharing no calendar removes the calendar card
 *
 * The spec writes throwaway theme folders on disk (the webserver container
 * bind-mounts ../src) and removes them again in after().
 */
const GOOD_THEME = "cypressadmingood";
const BROKEN_THEME = "cypressadminbroken";
const GOOD_DIR = `src/Include/themes/${GOOD_THEME}`;
const BROKEN_DIR = `src/Include/themes/${BROKEN_THEME}`;
const WARNING_THEME = "cypressadminwarning";
const WARNING_DIR = `src/Include/themes/${WARNING_THEME}`;
const HEADER_PURPLE = "rgb(75, 0, 130)";

const adminKey = () => Cypress.testEnv("admin.api.key");

/** Each portal ConfigItem: its tab, its panel there, and its label. */
const PORTAL_SWITCHES = [
    ["bPortalDeveloperMode", "portal-themes", "#portalDeveloperModePanel", "Developer mode"],
    ["bPortalAllowBirthdayEdit", "portal-permissions", "#portalMemberFeaturesPanel", "Let members edit birthdays"],
    ["bPortalShowVolunteer", "portal-permissions", "#portalMemberFeaturesPanel", "Show volunteering"],
    ["bPortalShowDirectory", "portal-directory", "#portalDirectorySectionPanel", "Show the member directory"],
];
const savedSwitches = {};
const CHURCH_CALENDAR = { type: "calendar", id: 1 };
let savedCalendars;

/** Stashed in `before` and restored in `after` — see the comment there. */
let originalVolunteerVersion = "v1";

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
    cy.signInAsMember(100);
    cy.visit("/portal/");
};

const openThemesTab = () => {
    cy.visit("/admin/member-portal");
    cy.get("#portal-themes-tab").click();
    cy.get("#portal-themes").should("be.visible");
};

const themeRowAction = (theme, action) => {
    cy.get(`#portalThemesTable tr[data-theme="${theme}"] [data-bs-toggle="dropdown"]`).click();
    cy.get(`#portalThemesTable tr[data-theme="${theme}"] .portal-theme-${action}`).should("be.visible").click();
};

const themeFindings = (theme) => cy.get(`#portalFindings-${theme}`, { timeout: 10000 });

const themeStatus = (theme) => cy.get(`#portalThemesTable tr[data-theme="${theme}"] .portal-theme-status`);

// Tabler's own white-on-success pairing measures 2.7:1; the unstyled grey badge text measured 1.0-1.8:1.
const MIN_BADGE_CONTRAST = 2.5;

const expectReadableBadges = () =>
    cy.get("#memberPortalPage .badge").should(($badges) => {
        $badges.each((_, badge) => {
            const $badge = Cypress.$(badge);
            expect(
                contrastRatio($badge.css("color"), effectiveBackground(badge)),
                `"${$badge.text().trim()}" badge contrast`
            ).to.be.at.least(MIN_BADGE_CONTRAST);
        });
    });

const expectReadableBadgesInBothModes = () => {
    cy.get("html").invoke("removeAttr", "data-bs-theme");
    expectReadableBadges();
    cy.get("html").invoke("attr", "data-bs-theme", "dark");
    expectReadableBadges();
    cy.get("html").invoke("removeAttr", "data-bs-theme");
};

before(() => {
    cy.rememberTestEnv(["admin.api.key"]);
});

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
        // A theme that overrides a template ChurchCRM does not have: warnings only.
        cy.writeFile(`${WARNING_DIR}/templates/no-such-page.html.twig`, "{# never rendered #}\n");
    });

    before(() => {
        for (const [name] of PORTAL_SWITCHES) {
            cy.getSystemConfig(name).then((value) => {
                savedSwitches[name] = value;
            });
        }
        cy.getMemberPortalCalendars().then((calendars) => {
            savedCalendars = calendars;
        });
    });

    before(() => {
        // Two tests below switch the volunteer rollout flag to `v2` to make the
        // volunteering card real. Left on, it retires the V1 opportunity editor by
        // design (#9704), and `admin.people` and
        // `admin.volunteer-opportunity-editor` then fail later in the suite looking
        // for a page that quietly redirected away. Stash it and put it back (#9869).
        cy.request({
            url: "/admin/api/system/config/sVolunteerVersion",
            headers: { "x-api-key": adminKey() },
            failOnStatusCode: false,
        }).then((resp) => {
            originalVolunteerVersion = resp.body.value ?? resp.body.data ?? "v1";
        });
    });

    after(() => {
        activateTheme("default");
        setConfig("bPortalDeveloperMode", "0");
        setConfig("bPortalShowVolunteer", "1");
        setConfig("sVolunteerVersion", originalVolunteerVersion);
        cy.then(() => cy.shareMemberPortalCalendars(savedCalendars));
        for (const [name] of PORTAL_SWITCHES) {
            cy.restoreSystemConfig(name, savedSwitches[name]);
        }
        cy.exec(`rm -rf ${GOOD_DIR} ${BROKEN_DIR} ${WARNING_DIR}`, { failOnNonZeroExit: false });
    });

    describe("As an administrator", () => {
        beforeEach(() => {
            cy.setupAdminSession();
        });

        it("Opens on Statistics, the first tab, and has no Settings tab", () => {
            cy.visit("/admin/member-portal");
            cy.get("#memberPortalTabs").should("be.visible");
            cy.get("#memberPortalTabs .nav-link")
                .first()
                .should("have.id", "portal-statistics-tab")
                .and("have.class", "active")
                .and("contain.text", "Statistics");
            cy.get("#portal-statistics").should("be.visible");
            cy.get("#portal-settings-tab").should("not.exist");
            cy.get("#portal-settings").should("not.exist");
            cy.get("#portalSettingsPanel").should("not.exist");
            cy.get("#portal-themes-tab").should("contain.text", "Themes");
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

        it("Breadcrumbs through People Settings and links back to it from the header", () => {
            cy.visit("/admin/member-portal");
            cy.get(".breadcrumb a[href$='/people/dashboard']").should("contain.text", "People");
            cy.get(".breadcrumb a[href$='/admin/people']").should("contain.text", "People Settings");
            cy.get(".breadcrumb .breadcrumb-item.active").should("contain.text", "Member Portal");
            cy.get(".page-header .btn-list").should("have.length", 1);
            cy.get(".page-header .btn-list a[href$='/admin/people']").should("contain.text", "People Settings");
            cy.get(".page-header .btn-list a[href$='/portal/']").should("contain.text", "Open the Member Portal");
        });

        for (const [name, tab, panel, label] of PORTAL_SWITCHES) {
            it(`${name} is on the ${tab} tab and saves from there`, () => {
                cy.intercept("POST", `**/admin/api/system/config/${name}`).as("saveSwitch");
                cy.visit("/admin/member-portal");
                cy.get(`#${tab}-tab`).click();
                cy.get(`#${tab} ${panel} .settings-panel-fields`, { timeout: 10000 })
                    .should("not.be.disabled")
                    .and("contain.text", label);
                cy.get(`${panel} .settings-panel-save`).should("not.exist");

                cy.get(`${panel} input[name='${name}'][value='1']`).then(($yes) => {
                    const flipped = $yes.is(":checked") ? "0" : "1";
                    cy.get(`${panel} input[name='${name}'][value='${flipped}']`).click({ force: true });
                    cy.wait("@saveSwitch").then((interception) => {
                        expect(interception.response.statusCode).to.eq(200);
                        expect(String(interception.request.body.value)).to.eq(flipped);
                    });
                    cy.getSystemConfig(name).should("eq", flipped);
                });
                cy.then(() => cy.restoreSystemConfig(name, savedSwitches[name]));
            });
        }

        it("Lists every discovered theme with a validation badge", () => {
            cy.visit("/admin/member-portal");
            cy.get("#portal-themes-tab").click();
            cy.get(`#portalThemesTable tr[data-theme="default"]`).should("contain.text", "System default");
            cy.get(`#portalThemesTable tr[data-theme="${GOOD_THEME}"]`)
                .should("contain.text", "Cypress Good")
                .and("contain.text", "Valid");
            cy.get(`#portalThemesTable tr[data-theme="${BROKEN_THEME}"]`).should("contain.text", "Errors");
            cy.get("#portalThemesLead").should("contain.text", "Include/themes/");
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
                "bPortalShowVolunteer",
                "bPortalAllowBirthdayEdit",
            ]) {
                cy.get("body").should("not.contain.text", name);
            }
        });

        it("Refuses a broken theme and lists the findings under its row", () => {
            activateTheme("default");
            cy.setupAdminSession();
            openThemesTab();
            themeRowAction(BROKEN_THEME, "activate");
            themeFindings(BROKEN_THEME)
                .should("be.visible")
                .and("contain.text", "This theme cannot be activated")
                .and("contain.text", "home.html.twig");
            themeStatus(BROKEN_THEME).should("contain.text", "Errors");
            cy.get("#portal-themes").should("be.visible");
            // Nothing was written: the portal is still on the theme it had.
            cy.request("/admin/api/system/config/sMemberPortalTheme").then((resp) => {
                expect(resp.body.value).to.eq("default");
            });
        });

        it("Every badge has readable text", () => {
            openThemesTab();
            cy.get(`#portalThemesTable tr[data-theme="${WARNING_THEME}"]`).should("contain.text", "Warnings");
            expectReadableBadgesInBothModes();

            for (const [theme, template] of [
                [GOOD_THEME, "This theme has no problems."],
                [BROKEN_THEME, "home.html.twig"],
                [WARNING_THEME, "no-such-page.html.twig"],
            ]) {
                themeRowAction(theme, "check");
                themeFindings(theme).should("be.visible").and("contain.text", template);
                expectReadableBadgesInBothModes();
            }
        });

        it("Check shows what it found under the checked theme, on the Themes tab", () => {
            openThemesTab();
            themeFindings(GOOD_THEME).should("not.be.visible");
            themeRowAction(GOOD_THEME, "check");
            themeFindings(GOOD_THEME).should("be.visible").and("contain.text", "This theme has no problems.");
            themeStatus(GOOD_THEME).should("contain.text", "Valid");

            themeRowAction(WARNING_THEME, "check");
            themeFindings(WARNING_THEME)
                .should("be.visible")
                .and("contain.text", "What the check found")
                .and("contain.text", "no-such-page.html.twig");
            cy.get(`#portalThemesTable tr[data-theme="${WARNING_THEME}"] .portal-theme-findings-toggle`)
                .should("be.visible")
                .and("contain.text", "1");
            cy.get("#portal-themes").should("be.visible");
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

        it("Sharing no calendar hides the calendar card in the portal", () => {
            activateTheme("default");
            // The volunteering card needs the V2 rollout flag on as well as its own switch.
            setConfig("sVolunteerVersion", "v2");
            setConfig("bPortalShowVolunteer", "1");
            cy.shareMemberPortalCalendars([CHURCH_CALENDAR]);
            loginAsMember();
            cy.get(".portal-card-calendar").should("exist");

            cy.shareMemberPortalCalendars([]);
            loginAsMember();
            cy.get(".portal-card-calendar").should("not.exist");
            cy.get("#portal-volunteering-card").should("exist");
        });

        it("Turning the volunteering section off hides its card in the portal", () => {
            activateTheme("default");
            cy.shareMemberPortalCalendars([CHURCH_CALENDAR]);
            setConfig("sVolunteerVersion", "v2");
            setConfig("bPortalShowVolunteer", "0");
            loginAsMember();
            cy.get("#portal-volunteering-card").should("not.exist");
            cy.get(".portal-card-calendar").should("exist");

            setConfig("bPortalShowVolunteer", "1");
        });
    });
});
