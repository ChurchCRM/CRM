/// <reference types="cypress" />

describe("Admin Debug", () => {
    beforeEach(() => {
        cy.setupAdminSession();
    });

    it("View system debug", () => {
        cy.visit("admin/system/debug");
        cy.contains("ChurchCRM Installation");
        cy.contains("Database");
        
        // Verify timezone information is displayed
        cy.contains("Timezone Information").should("exist");
        cy.get("#browser-timezone").should("not.contain", "Loading...");
        cy.get("#browser-time").should("not.contain", "Loading...");
        cy.get("#timezone-summary").should("exist");
    });

    it("View email debug", () => {
        cy.visit("admin/system/debug/email");
        cy.contains("Debug Email Connection");
    });

    it("View system settings", () => {
        cy.visit("SystemSettings.php");
    });
});
