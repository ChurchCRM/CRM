/// <reference types="cypress" />

/**
 * Volunteer v2 rollout — the browser-visible half of issue #9704.
 *
 * The API-only assertions live in
 * cypress/e2e/api/private/admin/private.volunteer.rollout.spec.js. The three
 * surfaces below cannot be asserted there because both `/people/*` and the
 * legacy `VolunteerOpportunityEditor.php` load `Include/PageInit.php`, whose
 * `ensureAuthentication()` call runs at file-load time — before any Slim
 * middleware — so an x-api-key request to them 302s to /session/begin.
 *
 * Order inside every hook is API setup → freshAdminLogin() → cy.visit(),
 * because cy.request() rotates the PHP session cookie (cypress-testing.md).
 */

const SETTING_URL = "/admin/api/system/config/sVolunteerVersion";
const PERSON_VIEW_URL = "/people/view/1";
const LEGACY_EDITOR_URL = "/VolunteerOpportunityEditor.php";

// Local helper — NOT a cy.* command (cypress-testing.md).
function freshAdminLogin() {
    cy.clearCookies();
    cy.visit("/session/begin");
    cy.get("input[name=User]").type(Cypress.env("admin.username"));
    cy.get("input[name=Password]").type(Cypress.env("admin.password") + "{enter}");
    cy.url().should("not.include", "/session/begin");
}

function setVersion(value) {
    cy.makePrivateAdminAPICall("POST", SETTING_URL, { value }, 200);
}

describe("Volunteer v2 rollout — navigation and person view (#9704)", () => {
    before(() => {
        // Cleanup-before: restore the default in case an earlier run crashed.
        setVersion("v1");
    });

    after(() => {
        setVersion("v1");
    });

    describe("v1 (default, legacy only)", () => {
        beforeEach(() => {
            setVersion("v1");
            freshAdminLogin();
        });

        it("shows one Volunteer tab on the person view, the legacy one", () => {
            cy.visit(PERSON_VIEW_URL);
            cy.get("#nav-item-volunteer").should("exist").and("contain", "Volunteer");
            cy.get("#nav-item-volunteer").should("not.contain", "Legacy");
            cy.get("#nav-item-volunteer-v2").should("not.exist");
            cy.get("#volunteer").should("exist");
        });

        it("keeps the legacy Volunteer Opportunities admin menu item and hides the V2 menu", () => {
            cy.visit(PERSON_VIEW_URL);
            cy.get('a[href$="VolunteerOpportunityEditor.php"]').should("exist");
            cy.get('a[href$="/volunteer/dashboard"]').should("not.exist");
        });

        it("serves the legacy editor without redirecting", () => {
            cy.visit(LEGACY_EDITOR_URL);
            cy.url().should("include", "VolunteerOpportunityEditor.php");
        });
    });

    describe("v2 (new experience only)", () => {
        beforeEach(() => {
            setVersion("v2");
            freshAdminLogin();
        });

        it("shows one Volunteer tab, the V2 placeholder", () => {
            cy.visit(PERSON_VIEW_URL);
            cy.get("#nav-item-volunteer-v2").should("exist").and("contain", "Volunteer");
            cy.get("#nav-item-volunteer").should("not.exist");
            cy.get("#volunteer").should("not.exist");
            cy.get("#volunteer-v2").should("exist");
        });

        it("shows the V2 menu entry and hides the legacy admin menu item", () => {
            cy.visit(PERSON_VIEW_URL);
            cy.get('a[href$="/volunteer/dashboard"]').should("exist");
            cy.get('a[href$="VolunteerOpportunityEditor.php"]').should("not.exist");
        });

        it("redirects the legacy editor to the V2 dashboard", () => {
            cy.visit(LEGACY_EDITOR_URL);
            cy.url().should("include", "/volunteer/dashboard");
        });

        // #9711 replaced #9704's placeholder with the real S1 dashboard, so this
        // asserts the page is the coordinator dashboard rather than the old
        // "Volunteer Management" placeholder heading.
        it("renders the V2 coordinator dashboard", () => {
            cy.visit("/volunteer/dashboard");
            cy.get("#volunteer-dashboard").should("exist");
            cy.contains("Ministry Dashboard").should("be.visible");
        });
    });

    describe("switching to v2 in the middle of a session (#9706 memo)", () => {
        // Carl's review path: log in while the setting is still v1, then change
        // it on the System Settings page WITHOUT logging out. The logged-in User
        // object lives in the PHP session; a memo of "not a coordinator" computed
        // during the v1 page loads must not follow the session, or the admin sees
        // no Ministries heading at all until the next login.
        beforeEach(() => {
            setVersion("v1");
            freshAdminLogin();
            cy.visit(PERSON_VIEW_URL);
            cy.get('a[href$="/volunteer/dashboard"]').should("not.exist");
        });

        after(() => {
            setVersion("v1");
        });

        it("shows the coordinator entries as soon as the setting is saved, without a new login", () => {
            cy.visit("/SystemSettings.php");
            cy.get('select[name="new_value[sVolunteerVersion]"]').select("v2", { force: true });
            cy.get('input[name="save"]').first().click({ force: true });

            cy.visit(PERSON_VIEW_URL);
            // "Setup" is gone from the menu: the guided wizard was removed and
            // its one irreplaceable step is a "New ministry" button now.
            cy.get('a[href$="/volunteer/setup"]').should("not.exist");
            // The coordinator half is the Ministries heading now — the
            // dashboard entry moved there out of the Volunteer heading, and
            // the retired ministries list page has no entry at all.
            cy.get('a[href$="/volunteer/ministries"]').should("not.exist");
            cy.get('a[href$="/volunteer/dashboard"]').should("exist");
            // The member entries are NOT in the sidebar in any state (#9867):
            // they moved into the Member Portal with the pages themselves.
            cy.get('a[href$="/volunteer/my-schedule"]').should("not.exist");
        });
    });

    describe("both (side-by-side transition)", () => {
        beforeEach(() => {
            setVersion("both");
            freshAdminLogin();
        });

        it("shows two clearly labelled Volunteer tabs", () => {
            cy.visit(PERSON_VIEW_URL);
            cy.get("#nav-item-volunteer").should("exist").and("contain", "Legacy");
            cy.get("#nav-item-volunteer-v2").should("exist").and("contain", "Volunteer");
            cy.get("#volunteer").should("exist");
            cy.get("#volunteer-v2").should("exist");
        });

        it("shows both the legacy admin menu item and the V2 menu entry", () => {
            cy.visit(PERSON_VIEW_URL);
            cy.get('a[href$="VolunteerOpportunityEditor.php"]').should("exist");
            cy.get('a[href$="/volunteer/dashboard"]').should("exist");
        });

        it("serves the legacy editor without redirecting", () => {
            cy.visit(LEGACY_EDITOR_URL);
            cy.url().should("include", "VolunteerOpportunityEditor.php");
        });
    });
});
