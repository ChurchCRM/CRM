/// <reference types="cypress" />

describe("Admin Debug", () => {
    beforeEach(() => {
        cy.setupAdminSession();
    });

    it("View system debug", () => {
        cy.visit("admin/system/debug");

        // Environment card subsumed the old "ChurchCRM Installation" card;
        // its tabs include Database, PHP, Web Server, Locale.
        cy.contains("Environment");
        cy.contains("Database");

        // Status banner is always rendered; it either shows "All checks
        // passing" or "Issues detected" depending on fixture state.
        cy.contains(/All checks passing|Issues detected/);

        // Timezone card keeps the stable selectors the JS relies on.
        cy.get("#headingTimezone").should("exist").invoke("text").should("not.be.empty");
        cy.get("#browser-timezone").should("exist").invoke("text").should("not.be.empty");
        cy.get("#browser-time").should("exist").invoke("text").should("not.be.empty");
        cy.get("#timezone-summary").should("exist").invoke("text").should("not.be.empty");
    });

    it("View email debug", () => {
        cy.visit("admin/system/debug/email");

        // Page title was "Debug Email Connection"; it's now "Email Debug"
        // and the page always renders one of three status cards (config
        // error / success / failure). Assert on structural elements
        // present in every state.
        cy.contains("Email Debug");
        cy.contains("SMTP Configuration");
    });

    it("View system settings", () => {
        cy.visit("SystemSettings.php");
    });
});
