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
Cypress.Commands.add("loginAdmin", (location, checkMatchingLocation = true) => {
    cy.login("admin", "changeme", location, checkMatchingLocation);
});

Cypress.Commands.add(
    "loginStandard",
    (location, checkMatchingLocation = true) => {
        cy.login(
            "tony.wade@example.com",
            "basicjoe",
            location,
            checkMatchingLocation,
        );
    },
);

Cypress.Commands.add(
    "login",
    (username, password, location, checkMatchingLocation = true) => {
        cy.visit("/?location=/" + location);
        cy.wait(150);
        cy.get("#UserBox").type(username);
        cy.get("#PasswordBox").type(password);
        cy.get("form").submit();

        if (location && checkMatchingLocation) {
            cy.location("pathname").should("include", location.split("?")[0]);
        }
    },
);

Cypress.Commands.add("buildRandom", (prefixString) => {
    let rand = Math.random().toString(36).substring(7);
    return prefixString.concat(" - ", rand);
});
