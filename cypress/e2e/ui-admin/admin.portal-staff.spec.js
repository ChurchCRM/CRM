/// <reference types="cypress" />

/**
 * Member Portal (MP2, #9863) — staff in the portal.
 *
 * Design: .agents/skills/churchcrm/member-portal-design.md §2.3 / P10.
 *   - a staff login still lands on /v2/dashboard
 *   - the admin user menu offers "Member Portal"
 *   - inside the portal a staff session gets back to the admin area through the
 *     account menu's "Admin Console" entry
 *
 * The fixed "You are viewing the Member Portal as yourself." bar was removed on
 * 2026-09-17 (product review): "Admin Console" does the same job without
 * spending a band of every page on it. The bar's offset class survives for the
 * masquerade banner only.
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

    it("No staff bar takes a band off the top of the page, and no admin sidebar", () => {
        cy.visit("/portal/");
        cy.get(".portal-account-toggle", { timeout: 10000 }).should("be.visible");
        cy.get(".portal-staff-bar").should("not.exist");
        cy.contains("You are viewing the Member Portal as yourself.").should("not.exist");
        cy.get("body").should("not.have.class", "portal-body-with-bar");
        cy.get("#sidebar").should("not.exist");
    });

    it("The account menu offers Admin Console, which returns to the admin dashboard", () => {
        cy.visit("/portal/");
        cy.get("#portal-account-toggle", { timeout: 10000 }).click();
        cy.get("#portal-account-menu")
            .contains('[role="menuitem"]', "Admin Console")
            .should("have.attr", "href")
            .and("include", "/v2/dashboard");

        cy.get("#portal-account-menu").contains('[role="menuitem"]', "Admin Console").click();
        cy.url({ timeout: 10000 }).should("include", "/v2/dashboard");
    });

    it("The account menu still offers Change Password and Sign out to staff", () => {
        cy.visit("/portal/");
        cy.get("#portal-account-toggle", { timeout: 10000 }).click();
        cy.get('#portal-account-menu [role="menuitem"]').should("have.length", 3);
        cy.get("#portal-account-menu").contains('[role="menuitem"]', "Change Password").should("exist");
        cy.get("#portal-account-menu").contains('[role="menuitem"]', "Sign out").should("exist");
    });
});
