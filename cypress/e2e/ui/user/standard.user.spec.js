/// <reference types="cypress" />

describe("Standard User Session", () => {
    it("Login and Logout", () => {
        cy.loginStandard("v2/dashboard");
        // Wait for dashboard to fully load before logging out
        cy.document().should("have.property", "readyState", "complete");
        cy.visit("/session/end");
    });

    it("UserName prefilled", () => {
        cy.loginStandard("session/begin?username=test@user.com");
        cy.get('input[id="UserBox"]').should("have.value", "test@user.com");
    });
});
