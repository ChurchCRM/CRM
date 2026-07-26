/// <reference types="cypress" />

describe("People Tools", () => {
    beforeEach(() => cy.setupStandardSession());
    
    it("Open the People Dashboard", () => {
        cy.visit("people/dashboard");
        cy.contains("People Dashboard");
        cy.contains("Quick Actions");
        cy.contains("Reports");
        cy.contains("Family Roles");
        cy.contains("People by Classification");
        cy.contains("Gender Demographics");
    });

    it("verify people", () => {
        cy.visit("people/verify");
        cy.contains("People Verify Dashboard");
    });

    it("self-register", () => {
        cy.visit("people/self-register");
        cy.contains("Self Registrations");
        cy.contains("New Self-Registrations");
        cy.contains("Families");
        cy.contains("Individuals (no family)");
        cy.get("#selfRegistrations tbody tr", { timeout: 10000 }).should("have.length.at.least", 1);
    });

    it("Geo Page", () => {
        cy.visit("GeoPage.php");
        cy.contains("Family Geographic Utilities");
    });

});
