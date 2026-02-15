/// <reference types="cypress" />

describe("Admin Debug", () => {
    beforeEach(() => {
        cy.setupAdminSessionFromEnv();
    });

    it("View system debug", () => {
        cy.visit("admin/system/debug");
        cy.contains("ChurchCRM Installation");
        cy.contains("Database");
        
        // Verify timezone information is displayed using stable selectors
        cy.get("#headingTimezone").should("exist").invoke("text").should("not.be.empty");
        cy.get("#browser-timezone").should("exist").invoke("text").should("not.be.empty");
        cy.get("#browser-time").should("exist").invoke("text").should("not.be.empty");
        cy.get("#timezone-summary").should("exist").invoke("text").should("not.be.empty");
    });

    it("View email debug", () => {
        cy.visit("admin/system/debug/email");
        cy.contains("Debug Email Connection");
    });

    it("View system settings", () => {
        cy.visit("SystemSettings.php");
    });
});
