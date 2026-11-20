/// <reference types="cypress" />

/**
 * Ministries is the only volunteer experience (#10606) — the browser-visible half. The API
 * half lives in cypress/e2e/api/private/admin/private.volunteer.rollout.spec.js.
 *
 * Order inside every hook is API setup → freshAdminLogin() → cy.visit(),
 * because cy.request() rotates the PHP session cookie (cypress-testing.md).
 */

const PERSON_VIEW_URL = "/people/view/1";

// Local helper — NOT a cy.* command (cypress-testing.md).
function freshAdminLogin() {
    cy.clearCookies();
    cy.visit("/session/begin");
    cy.get("input[name=User]").type(Cypress.testEnv("admin.username"));
    cy.get("input[name=Password]").type(Cypress.testEnv("admin.password") + "{enter}");
    // One retry: under CI load the submit occasionally lands back on the login
    // page although the server logged the login (a lost cookie on the redirect).
    cy.url().then((url) => {
        if (/\/admin\/$/.test(new URL(url).pathname)) {
            cy.get("input[name=User]").clear().type(Cypress.testEnv("admin.username"));
            cy.get("input[name=Password]").clear().type(Cypress.testEnv("admin.password") + "{enter}");
        }
    });
    cy.location("pathname").should("not.match", /\/admin\/$/);
}

before(() => {
    cy.rememberTestEnv(["admin.username", "admin.password"]);
});

describe("Volunteer Management with Ministries only — navigation (#10606)", () => {
    beforeEach(() => {
        freshAdminLogin();
    });

    it("shows one Volunteer tab on the person page", () => {
        cy.visit(PERSON_VIEW_URL);
        cy.get("#nav-item-volunteer").should("exist").and("contain", "Volunteer");
        cy.get("#volunteer").should("exist");
    });

    it("shows the Ministries menu and no Volunteer Opportunities item", () => {
        cy.visit(PERSON_VIEW_URL);
        cy.get('a[href$="/ministries/dashboard"]').should("exist");
        cy.get('a[href$="VolunteerOpportunityEditor.php"]').should("not.exist");
        cy.get('a[href$="/volunteer/my-schedule"]').should("not.exist");
    });

    it("has no Volunteer Opportunities editor", () => {
        cy.request({ url: "/VolunteerOpportunityEditor.php", failOnStatusCode: false }).its("status").should("eq", 404);
    });

    it("renders the Ministry Dashboard", () => {
        cy.visit("/ministries/dashboard");
        cy.get("#volunteer-dashboard").should("exist");
        cy.contains("Ministry Dashboard").should("be.visible");
    });

    it("offers no volunteer experience choice on Feature Toggles", () => {
        cy.visit("/admin/system/feature-toggles");
        cy.get("#featureTogglesContainer .feature-toggle").should("have.length.greaterThan", 0);
        cy.get("[data-setting='sVolunteerVersion']").should("not.exist");
    });

    it("lists no Volunteer Opportunities on People Settings", () => {
        cy.visit("/admin/people");
        cy.contains("a", "Member Portal").should("exist");
        cy.get('a[href$="VolunteerOpportunityEditor.php"]').should("not.exist");
    });
});
