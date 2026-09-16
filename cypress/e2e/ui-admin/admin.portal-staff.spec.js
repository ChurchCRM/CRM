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

    it("The user menu offers a Member Portal entry", () => {
        cy.visit("/v2/dashboard");
        cy.get('[aria-label="Open user menu"]').click();
        cy.contains("a", "Member Portal").should("be.visible");
    });

    it("The Member Portal entry opens the portal", () => {
        cy.visit("/v2/dashboard");
        cy.get('[aria-label="Open user menu"]').click();
        cy.contains("a", "Member Portal").click();
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
