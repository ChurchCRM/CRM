/// <reference types="cypress" />

describe("People Tools", () => {
    beforeEach(() => cy.setupStandardSession());

    it("opens the People Dashboard and the people tools for a standard user", () => {
        cy.visit("people/dashboard");
        cy.contains("People Dashboard");
        cy.contains("People by Classification");
        cy.contains("Gender Demographics");

        cy.visit("people/verify");
        cy.contains("People Verify Dashboard");

        cy.visit("people/self-register");
        cy.contains("Self Registrations");
        cy.get("#selfRegistrations", { timeout: 10000 }).should("exist");

        cy.visit("people/map/neighbors");
        cy.contains("Find Neighbors");
    });
});
