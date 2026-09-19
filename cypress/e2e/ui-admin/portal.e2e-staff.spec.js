/// <reference types="cypress" />

/**
 * Member Portal — #9869 scenario 3, "staff in the portal", as ONE end-to-end run.
 *
 * Epic #8977, issue #9869, design `.agents/skills/churchcrm/member-portal-design.md`
 * §2.3 ("who lands where"), P10 and §9 MP8; masquerade is #9843.
 *
 * Two different people can be looking at the portal on a staff session, and the portal
 * has to tell them apart, out loud, on every page:
 *
 *   1. **An administrator being themselves.** They opened the portal from their own
 *      user menu to see what members see. They are not a member of anything here, and
 *      the way out is a click: the portal account menu offers **Admin Console**. (The
 *      "You are viewing the Member Portal as yourself." bar this used to assert was
 *      retired with the account menu — the entry says the same thing without a band
 *      across every page.)
 *   2. **An administrator being somebody else.** They are masquerading, every action is
 *      recorded against the member, and the page they are looking at is the member's,
 *      not a staff preview of it. The banner says whose account it is, and is the only
 *      way back — the account menu offers no Admin Console during a masquerade.
 *
 * Getting this wrong is not a cosmetic bug. An administrator who forgets which of the
 * two they are edits the wrong person's record and has no way to notice. So the run
 * asserts, on every page: the banner exactly when masquerading, and the Admin Console
 * entry exactly when not.
 *
 * MP8 is what makes case 2 possible at all. Before it, the banner lived in the two
 * admin header layouts and the portal used neither, so a masquerade into a
 * self-service account landed on a page with no banner and no exit. The
 * `portal.impersonating` hook MP2 left is now filled by the shared include.
 *
 * Personas (cypress/data/seed.sql):
 *
 *   usr 1    admin         administrator
 *   usr 99   Amanda Black  `usr_EditSelf=1`, no admin flag — the member to become
 *   usr 100  Lena Black    a second self-service login, used to prove the portal
 *                          shows the person being impersonated and not the admin
 *
 * This spec lives in `ui-admin/` because it starts from the admin shell and drives
 * the admin user menu; `docker-admin.config.ts` is the config that runs it.
 */

const ADMIN_DASHBOARD = "/v2/dashboard";
const PORTAL_HOME = "/portal/";

const MEMBER_USER_ID = 99;
const MEMBER_NAME = "Amanda Black";
const MEMBER_FIRST_NAME = "Amanda";

const OTHER_MEMBER_NAME = "Lena";

const ADMIN_CONSOLE_TEXT = "Admin Console";
const MASQUERADE_TEXT = `You are logged in as ${MEMBER_NAME}. Actions are recorded as them.`;

/**
 * The portal pages an administrator reaches while being THEMSELVES. `/portal/family`
 * is deliberately absent: the seeded administrator is in no family record, so that
 * page is a legitimate 404 for them — which is itself a small illustration of why the
 * "as yourself" bar matters. The masquerade half of the run does include it, because
 * the member it becomes has a household.
 */
const PORTAL_PAGES = ["/portal/", "/portal/profile"];

/** The same, plus the page only a member of a family has. */
const MEMBER_PORTAL_PAGES = [...PORTAL_PAGES, "/portal/family"];

// ── helpers (local functions, NOT cy.* commands — cypress-testing.md) ───────

function adminLogin() {
    cy.clearCookies();
    cy.visit("/session/begin");
    cy.get("input[name=User]").type(Cypress.env("admin.username"));
    cy.get("input[name=Password]").type(`${Cypress.env("admin.password")}{enter}`);
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

/** No banner, and the account menu offers the way back to the admin shell. */
function assertStaffSelf() {
    cy.get(".portal-shell", { timeout: 10000 }).should("exist");
    cy.get("#impersonationBanner").should("not.exist");
    cy.get("body").should("not.have.class", "portal-body-with-bar");
    accountMenuItems()
        .filter(`:contains("${ADMIN_CONSOLE_TEXT}")`)
        .should("have.length", 1)
        .and("have.attr", "href")
        .and("include", ADMIN_DASHBOARD);
    cy.get("body").type("{esc}");
}

/** The masquerade banner, and no Admin Console to escape through. */
function assertMasqueradeBanner() {
    cy.get("#impersonationBanner", { timeout: 10000 }).should("be.visible");
    cy.get("#impersonationBanner").should("contain.text", MASQUERADE_TEXT);
    cy.get("body").should("have.class", "portal-body-with-bar");
    // The banner is the only way back: no Admin Console while acting as a member.
    accountMenuItems().should("not.contain.text", ADMIN_CONSOLE_TEXT);
    cy.get("body").type("{esc}");
}

describe("Member Portal e2e — #9869 scenario 3, staff in the portal", () => {
    describe("an administrator, being themselves", () => {
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

        it("is offered Admin Console on every page, and never a masquerade banner", () => {
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
            cy.get("#impersonationBanner").should("not.exist");
        });
    });

    describe("an administrator, being somebody else", () => {
        beforeEach(() => {
            adminLogin();
            cy.visit(`/v2/user/${MEMBER_USER_ID}`);
            cy.get("#loginAsUser").click();
            cy.get(".bootbox.modal").should(
                "contain.text",
                `Log in as ${MEMBER_NAME}?`,
            );
            cy.get(".bootbox.modal .btn-warning").click();
            // An EditSelf-exclusive target is confined to the portal (#9863), so
            // the masquerade lands there rather than on the dashboard.
            cy.url({ timeout: 10000 }).should("include", "/portal");
        });

        afterEach(() => {
            // Always hand the session back, or the next test starts as the member.
            cy.get("body").then(($body) => {
                if ($body.find("#impersonationExit").length > 0) {
                    cy.get("#impersonationExit").click();
                    cy.url({ timeout: 10000 }).should("include", "/v2/user/");
                }
            });
        });

        it("lands in the portal wearing the masquerade banner", () => {
            assertMasqueradeBanner();
            assertPortalNotAdminShell();
            cy.get(".portal-home").should("exist");
        });

        it("sees exactly the member's portal — their name, their family, their nav", () => {
            // The welcome card is the member's, not the administrator's.
            cy.get(".portal-welcome-title").should("contain", MEMBER_FIRST_NAME);
            cy.get(".portal-welcome-title").should("not.contain", "Church Admin");
            // …and not some other member's, which is the failure mode that would
            // look plausible on screen.
            cy.get(".portal-welcome-title").should("not.contain", OTHER_MEMBER_NAME);

            cy.visit("/portal/profile");
            assertMasqueradeBanner();
            cy.get("#portal-profile-details").should("contain", MEMBER_FIRST_NAME);

            cy.visit("/portal/family");
            assertMasqueradeBanner();
        });

        it("keeps the banner on every page, because it is the only way back", () => {
            for (const url of MEMBER_PORTAL_PAGES) {
                cy.visit(url);
                assertMasqueradeBanner();
                cy.get("#impersonationExit").should("be.visible");
            }
        });

        it("exits through the banner, and the administrator is themselves again", () => {
            cy.get("#impersonationExit").should("be.visible").click();

            cy.url({ timeout: 10000 }).should("include", `/v2/user/${MEMBER_USER_ID}`);
            cy.get("#impersonationBanner").should("not.exist");
            cy.get("body").should("not.have.class", "portal-body-with-bar");
            cy.get(".navbar").should("contain.text", "Church Admin");

            // And the portal is theirs again, Admin Console back in the menu.
            cy.visit(PORTAL_HOME);
            assertStaffSelf();
        });
    });
});
