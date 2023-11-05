/// <reference types="cypress" />

context("Report Issue", () => {
    it("Click Menus to Report issue", () => {
        cy.loginStandard("v2/dashboard");
        cy.get(".fa-headset").click();
        cy.get("#reportIssue").click();
        cy.contains("Issue Report!");
    });
});
