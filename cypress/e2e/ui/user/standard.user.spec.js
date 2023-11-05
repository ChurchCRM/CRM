/// <reference types="cypress" />

context("Standard User Session", () => {
    it("Login and Logout", () => {
        cy.loginStandard("v2/dashboard");
        cy.visit("/session/end");
    });

    it("UserName prefilled", () => {
        cy.loginStandard("session/begin?username=test@user.com");
        cy.get('input[id="UserBox"]').should("have.value", "test@user.com");
    });
});
