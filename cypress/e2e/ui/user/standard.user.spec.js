/// <reference types="cypress" />

describe("Standard User Session", () => {
    it("UserName prefilled on login page", () => {
        cy.visit("session/begin?username=test@user.com");
        cy.get('input[id="UserBox"]').should("have.value", "test@user.com");
    });
});
