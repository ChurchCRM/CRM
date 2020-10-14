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
 Cypress.Commands.add("login", (username, password, assertLoggedIn = true) => {
     cy.visit("/");
     cy.get("#UserBox").type(username);
     cy.get("#PasswordBox").type(password);
     cy.get("form").submit();
     if (assertLoggedIn) {
         cy.location('pathname').should('include', "/Menu.php");
     }
 })

