/// <reference types="cypress" />

describe("Email Pages", () => {
    beforeEach(() => cy.setupStandardSession());
    
    it("Email Dashboard", () => {
        cy.visit("v2/email/dashboard");
        cy.contains("eMail Dashboard");
    });

    it("Duplicate Emails", () => {
        cy.visit("v2/email/duplicate");
        cy.contains("Duplicate Emails");
        cy.contains("lady@nower.com");
    });

    it("Families Without Emails", () => {
        cy.visit("v2/email/missing");
        cy.contains("Families Without Emails");
        cy.contains("Troy543267");
    });
});
