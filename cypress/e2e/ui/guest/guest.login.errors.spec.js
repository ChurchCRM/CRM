/// <reference types="cypress" />

describe("Login", () => {
    it("Bad password", () => {
        cy.visit('/login');
        cy.get('input[name=User]').type("admin");
        cy.get('input[name=Password]').type("badpassword" + '{enter}');
        // Wait for redirect to happen after failed login
        cy.url().should("include", "session/begin");
    });

    it("Bad username", () => {
        cy.visit('/login');
        cy.get('input[name=User]').type("idonknowyou");
        cy.get('input[name=Password]').type("badpassword" + '{enter}');
        // Wait for redirect to happen after failed login
        cy.url().should("include", "session/begin");
    });
});
