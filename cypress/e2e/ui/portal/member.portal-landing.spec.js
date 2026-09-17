/// <reference types="cypress" />

/**
 * Member Portal (MP2, #9863) — the landing rule for a self-service login.
 *
 * Seed persona: user 100, Lena Black (person 100, family 20). usr_EditSelf=1 and
 * no admin flag, so User::isEditSelfExclusive() is true. The username column is
 * VARCHAR(32), so the seeded address "lena.black.editself.notes@example.com" is
 * stored truncated — log in with the 32-character form.
 *
 * Design: .agents/skills/churchcrm/member-portal-design.md §2.2 / §2.3 (P10).
 *   - an Edit-Self-only login lands in /portal and never sees the admin shell
 *   - /external/limited-access is retired and redirects to /portal
 *   - a legacy *.php page bounces to /portal (Include/PageInit.php)
 */
describe("Member Portal — self-service landing", () => {
    const memberUser = "lena.black.editself.notes@exampl";
    const memberPassword = "changeme";

    const login = () => {
        cy.clearCookies();
        cy.visit("session/begin");
        cy.get("input[name=User]").type(memberUser);
        cy.get("input[name=Password]").type(memberPassword + "{enter}");
    };

    it("Login lands on /portal", () => {
        login();
        cy.url({ timeout: 10000 }).should("include", "/portal");
        cy.url().should("not.include", "/external/limited-access");
        cy.url().should("not.include", "/v2/dashboard");
    });

    it("The portal home greets the member by name and shows the family name", () => {
        login();
        cy.url({ timeout: 10000 }).should("include", "/portal");
        cy.get(".portal-home", { timeout: 10000 }).should("exist");
        cy.contains("Lena").should("exist");
        cy.contains("Black").should("exist");
    });

    it("No admin shell furniture is rendered anywhere in the portal", () => {
        login();
        cy.url({ timeout: 10000 }).should("include", "/portal");
        cy.get("#sidebar").should("not.exist");
        cy.get("#sidebar-menu").should("not.exist");
        cy.get(".navbar-vertical").should("not.exist");
        cy.get("#fab-container").should("not.exist");
    });

    it("The account menu offers Sign out, which returns to the login page", () => {
        login();
        cy.url({ timeout: 10000 }).should("include", "/portal");
        cy.get("#portal-account-toggle").click();
        cy.get("#portal-account-menu").contains('[role="menuitem"]', "Sign out").click();
        cy.url({ timeout: 10000 }).should("include", "/session/begin");
    });

    it("The retired /external/limited-access URL redirects to /portal", () => {
        login();
        cy.url({ timeout: 10000 }).should("include", "/portal");
        cy.visit("external/limited-access", { failOnStatusCode: false });
        cy.url({ timeout: 10000 }).should("include", "/portal");
        cy.url().should("not.include", "/external/limited-access");
    });

    it("A direct visit to /v2/dashboard bounces to /portal", () => {
        login();
        cy.url({ timeout: 10000 }).should("include", "/portal");
        cy.visit("v2/dashboard", { failOnStatusCode: false });
        cy.url({ timeout: 10000 }).should("include", "/portal");
        cy.url().should("not.include", "/v2/dashboard");
    });

    it("A direct visit to another MVC module bounces to /portal", () => {
        login();
        cy.url({ timeout: 10000 }).should("include", "/portal");
        cy.visit("people/dashboard", { failOnStatusCode: false });
        cy.url({ timeout: 10000 }).should("include", "/portal");
        cy.url().should("not.include", "/people/dashboard");
    });

    it("A legacy *.php page bounces to /portal (PageInit)", () => {
        login();
        cy.url({ timeout: 10000 }).should("include", "/portal");
        cy.visit("SystemSettings.php", { failOnStatusCode: false });
        cy.url({ timeout: 10000 }).should("include", "/portal");
        cy.url().should("not.include", "SystemSettings.php");
    });

    // The bar is gone for everyone (2026-09-17): the account menu's "Admin
    // Console" entry is the way back to the admin area, and a member never
    // sees that entry either.
    it("No staff bar is rendered, and the account menu has no Admin Console", () => {
        login();
        cy.url({ timeout: 10000 }).should("include", "/portal");
        cy.get(".portal-staff-bar").should("not.exist");
        cy.get("#portal-account-toggle").click();
        cy.get("#portal-account-menu").contains("Admin Console").should("not.exist");
    });
});
