/// <reference types="cypress" />

/**
 * Member Portal (MP2, #9863) — staff in the portal.
 *
 * Design: .agents/skills/churchcrm/member-portal-design.md §2.3 / P10.
 *   - a staff login still lands on /v2/dashboard
 *   - the admin user menu offers "Member Portal"
 *   - inside the portal a staff session sees the fixed "viewing as yourself"
 *     bar, whose exit control returns to the admin dashboard
 */
describe("Member Portal — staff access", () => {
    beforeEach(() => {
        cy.setupAdminSession();
    });

    it("An administrator still lands on the admin dashboard", () => {
        cy.visit("/");
        cy.url({ timeout: 10000 }).should("include", "/v2/dashboard");
        cy.url().should("not.include", "/portal");
    });

    // Scoped to the user menu's dropdown: since #9864 the admin sidebar also
    // carries a "Member Portal" entry (Admin → Member Portal), so matching on
    // the label alone would find that collapsed sidebar link instead.
    const userMenuPortalLink = () => cy.get('.dropdown-menu a.dropdown-item[href$="/portal/"]');

    it("The user menu offers a Member Portal entry", () => {
        cy.visit("/v2/dashboard");
        cy.get('[aria-label="Open user menu"]').click();
        userMenuPortalLink().should("be.visible").and("contain.text", "Member Portal");
    });

    it("The Member Portal entry opens the portal", () => {
        cy.visit("/v2/dashboard");
        cy.get('[aria-label="Open user menu"]').click();
        userMenuPortalLink().click();
        cy.url({ timeout: 10000 }).should("include", "/portal");
        cy.get(".portal-home").should("exist");
    });

    it("Staff see the 'viewing as yourself' bar and no admin sidebar", () => {
        cy.visit("/portal/");
        cy.get(".portal-staff-bar", { timeout: 10000 }).should("be.visible");
        cy.contains("You are viewing the Member Portal as yourself.").should("exist");
        cy.get("#sidebar").should("not.exist");
    });

    it("The exit control returns to the admin dashboard", () => {
        cy.visit("/portal/");
        cy.get('.portal-staff-bar [aria-label="Exit to the admin area"]', { timeout: 10000 }).click();
        cy.url({ timeout: 10000 }).should("include", "/v2/dashboard");
    });
});

/**
 * Account pages stay inside the portal for every role.
 *
 * Product finding: an administrator who opened the portal and clicked "Change
 * password" or "Two-factor" was dropped back into the admin console, because
 * /v2/user/current/* only wore the portal layout for an Edit-Self-only session.
 * The portal now owns both pages, so the only way out of the portal is the
 * "Admin Console" control the staff bar offers.
 */
describe("Member Portal — account pages for staff", () => {
    beforeEach(() => {
        cy.setupAdminSession();
    });

    it("Change password from the portal keeps an administrator in the portal", () => {
        cy.visit("/portal/profile");
        cy.get("#portal-change-password-link").click();

        cy.url({ timeout: 10000 }).should("include", "/portal/profile/password");
        cy.url().should("not.include", "/v2/");
        cy.get(".portal-shell").should("exist");
        cy.get("#sidebar").should("not.exist");
        cy.get("#OldPassword").should("exist");
        cy.get("#NewPassword1").should("exist");
    });

    it("Two-factor from the portal keeps an administrator in the portal", () => {
        cy.visit("/portal/profile");
        cy.get("#portal-two-factor-link").click();

        cy.url({ timeout: 10000 }).should("include", "/portal/profile/two-factor");
        cy.url().should("not.include", "/v2/");
        cy.get(".portal-shell").should("exist");
        cy.get("#sidebar").should("not.exist");
    });

    it("The two-factor enrollment wizard runs on the portal page", () => {
        cy.visit("/portal/profile/two-factor");
        cy.get("#two-factor-enrollment-app").should("exist");
        // The shared two-factor-enrollment bundle mounts and draws its intro
        // step, which is what proves the portal page carries a working wizard
        // and not just an empty container.
        cy.get("#begin2faEnrollment", { timeout: 10000 })
            .should("exist")
            .and("be.visible")
            .and("be.enabled");
    });
});
