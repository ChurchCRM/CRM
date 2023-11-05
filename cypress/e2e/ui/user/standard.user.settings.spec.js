/// <reference types="cypress" />

context("Standard User Setting", () => {
    it("View User Setting and Edit Page", () => {
        cy.loginStandard("v2/user/3");
        cy.contains("User -");
        cy.get("#editSettings").click();
        cy.url().should("contains", "SettingsIndividual.php");
        cy.contains("My User Settings");
    });
});
