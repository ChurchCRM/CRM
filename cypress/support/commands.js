// ***********************************************
// This example commands.js shows you how to
// create various custom commands and overwrite
// existing commands.
//
// For more comprehensive examples of custom
// commands please read more here:
// https://on.cypress.io/custom-commands
// ***********************************************


// -- This is a login command --
Cypress.Commands.add("loginAdmin", (location) => {
    cy.login("admin", "changeme", location);
});

Cypress.Commands.add("loginStandard", (location) => {
    cy.login("tony.wade@example.com", "abc123", location);
});

Cypress.Commands.add("login", (username, password, location) => {
    cy.visit("/?location=/" + location);
    cy.wait(150);
    cy.get("#UserBox").type(username);
    cy.get("#PasswordBox").type(password);
    cy.get("form").submit();
    if (location) {
        cy.location('pathname').should('include', location);
    }
});


Cypress.Commands.add("buildRandom", (prefixString) => {
    let rand = Math.random().toString(36).substring(7);
    return prefixString.concat(" - ", rand);
});

