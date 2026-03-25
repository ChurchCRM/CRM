/// <reference types="cypress" />

describe("People Tools", () => {
    beforeEach(() => cy.setupStandardSession());
    
    it("Open the People Dashboard", () => {
        cy.visit("people/dashboard");
        cy.contains("People Dashboard");
        cy.contains("People Functions");
        cy.contains("Reports");
        cy.contains("Family Roles");
        cy.contains("People Classification");
        cy.contains("Gender Demographics");
    });

    it("verify people", () => {
        cy.visit("v2/people/verify");
        cy.contains("People Verify Dashboard");
    });

    it("self-register", () => {
        cy.visit("members/self-register.php");
        cy.contains("Self Registrations");
        cy.contains("People");
        cy.contains("Families");
    });

    it("Geo Page", () => {
        cy.visit("GeoPage.php");
        cy.contains("Family Geographic Utilities");
    });

});
