/// <reference types="cypress" />

/**
 * Member Portal — #9869 scenario 3, "staff in the portal", as ONE end-to-end run.
 *
 * Epic #8977, issue #9869, design `.agents/skills/churchcrm/member-portal-design.md`
 * §2.3 ("who lands where") and P10.
 *
 * An administrator opened the portal from their own user menu to see what members
 * see. They are not a member of anything here, and the way out is a click: the
 * portal account menu offers **Admin Console**, on every page.
 *
 * This spec lives in `ui-admin/` because it starts from the admin shell and drives
 * the admin user menu; `docker-admin.config.ts` is the config that runs it.
 */

const ADMIN_DASHBOARD = "/v2/dashboard";
const PORTAL_HOME = "/portal/";

const ADMIN_CONSOLE_TEXT = "Admin Console";

/**
 * The portal pages an administrator reaches. `/portal/family` is deliberately
 * absent: the seeded administrator is in no family record, so that page is a
 * legitimate 404 for them.
 */
const PORTAL_PAGES = ["/portal/", "/portal/profile"];

// ── helpers (local functions, NOT cy.* commands — cypress-testing.md) ───────

function adminLogin() {
    cy.clearCookies();
    cy.visit("/session/begin");
    cy.get("input[name=User]").type(Cypress.testEnv("admin.username"));
    cy.get("input[name=Password]").type(`${Cypress.testEnv("admin.password")}{enter}`);
    cy.url({ timeout: 10000 }).should("include", ADMIN_DASHBOARD);
}

/** The portal, whichever bar it is wearing, is never the admin shell. */
function assertPortalNotAdminShell() {
    cy.get(".portal-shell").should("exist");
    cy.get("#sidebar").should("not.exist");
    cy.get("#sidebar-menu").should("not.exist");
    cy.get(".navbar-vertical").should("not.exist");
}

/** Open the portal account menu and hand back its items. */
function accountMenuItems() {
    cy.get("#portal-account-toggle", { timeout: 10000 }).click();
    cy.get("#portal-account-menu").should("be.visible");

    return cy.get('#portal-account-menu [role="menuitem"]');
}

/** The account menu offers the way back to the admin shell. */
function assertStaffSelf() {
    cy.get(".portal-shell", { timeout: 10000 }).should("exist");
    accountMenuItems()
        .filter(`:contains("${ADMIN_CONSOLE_TEXT}")`)
        .should("have.length", 1)
        .and("have.attr", "href")
        .and("include", ADMIN_DASHBOARD);
    cy.get("body").type("{esc}");
}

before(() => {
    cy.rememberTestEnv(["admin.username", "admin.password"]);
});

describe("Member Portal e2e — #9869 scenario 3, staff in the portal", () => {
    describe("an administrator in the portal", () => {
        beforeEach(() => {
            adminLogin();
        });

        it("opens the portal from their own user menu", () => {
            cy.get('[aria-label="Open user menu"]').click();
            cy.get('.dropdown-menu a.dropdown-item[href$="/portal/"]')
                .should("be.visible")
                .click();

            cy.url({ timeout: 10000 }).should("include", "/portal");
            assertPortalNotAdminShell();
            cy.get(".portal-home").should("exist");
        });

        it("is offered Admin Console on every page", () => {
            for (const url of PORTAL_PAGES) {
                cy.visit(url);
                assertStaffSelf();
                assertPortalNotAdminShell();
            }
        });

        it("leaves through the account menu's Admin Console, back to the admin shell", () => {
            cy.visit(PORTAL_HOME);
            accountMenuItems().filter(`:contains("${ADMIN_CONSOLE_TEXT}")`).click();

            cy.url({ timeout: 10000 }).should("include", ADMIN_DASHBOARD);
            cy.get("#sidebar").should("exist");
        });
    });
});
