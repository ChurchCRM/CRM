/// <reference types="cypress" />

describe("Standard User Session", () => {
    beforeEach(() => cy.setupStandardSession());
    
    it("Login and Logout", () => {
        cy.visit("v2/dashboard");
        
        cy.contains("Welcome to");
        
        cy.visit("/session/end");
    });

    it("UserName prefilled", () => {
        cy.visit("session/begin?username=test@user.com");
        cy.get('input[id="UserBox"]').should("have.value", "test@user.com");
    });
});
