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
        cy.contains("Pending Registrations");
        cy.contains("Pending review");
        cy.contains("Total registrations");
        cy.get("#selfRegistrations", { timeout: 10000 }).should("exist");
    });

    it("Find Neighbors", () => {
        cy.visit("people/map/neighbors");
        cy.contains("Find Neighbors");
    });

});
