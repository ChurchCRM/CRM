/// <reference types="cypress" />

context("Email Pages", () => {
    it("Email Dashboard", () => {
        cy.loginStandard("v2/email/dashboard");
        cy.contains("eMail Dashboard");
    });

    it("Duplicate Emails", () => {
        cy.loginStandard("v2/email/duplicate");
        cy.contains("Duplicate Emails");
        cy.contains("lady@nower.com");
    });

    it("Families Without Emails", () => {
        cy.loginStandard("v2/email/missing");
        cy.contains("Families Without Emails");
        cy.contains("Troy543267");
    });
});
