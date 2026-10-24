import { personaDefinition } from "./personas-data";

// cy.personaAPICall(persona, method, url, body, expectedStatus, timeoutMs)
// Same contract as cy.makePrivateAPICall, with the key chosen by persona.
Cypress.Commands.add("personaAPICall", (persona, method, url, body, expectedStatus = 200, timeoutMs) =>
    cy
        .readEnv(personaDefinition(persona).apiKey)
        .then((key) => cy.makePrivateAPICall(key, method, url, body, expectedStatus, timeoutMs)),
);

// cy.setupPersonaSession(persona, options)
// Cached form-login session for a persona, named after it.
Cypress.Commands.add("setupPersonaSession", (persona, options = {}) => {
    const { login } = personaDefinition(persona);
    const sessionName = `persona-${persona}`;

    if (login.usernameKey) {
        return cy.readEnv(login.usernameKey).then((username) =>
            cy.readEnv(login.passwordKey).then((password) => {
                cy.setupLoginSession(sessionName, username, password, options);
            }),
        );
    }
    return cy.setupLoginSession(sessionName, login.username, login.password, options);
});
