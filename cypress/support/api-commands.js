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
Cypress.Commands.add(
    "makePrivateAdminAPICall",
    (method, url, body, expectedStatus = 200) => {
        cy.makePrivateAPICall(
            Cypress.env("admin.api.key"),
            method,
            url,
            body,
            expectedStatus,
        );
    },
);

Cypress.Commands.add(
    "makePrivateUserAPICall",
    (method, url, body, expectedStatus = 200) => {
        cy.makePrivateAPICall(
            Cypress.env("user.api.key"),
            method,
            url,
            body,
            expectedStatus,
        );
    },
);

Cypress.Commands.add(
    "makePrivateAPICall",
    (key, method, url, body, expectedStatus = 200) => {
        cy.request({
            method: method,
            failOnStatusCode: false,
            url: url,
            headers: { "content-type": "application/json", "x-api-key": key },
            body: body,
        }).then((resp) => {
            expect(expectedStatus).to.eq(resp.status);

            if (!resp.body) {
                return null;
            }

            return JSON.parse(JSON.stringify(resp.body));
        });
    },
);
