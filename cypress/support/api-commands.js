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
Cypress.Commands.add("makePrivateAPICall", (method, url, body, expectedStatus = 200) => {
    cy.request({
        method: method,
        failOnStatusCode: false,
        url: url,
        headers: {'content-type': 'application/json', "x-api-key": Cypress.env('admin.api.key')},
        body: body
    }).then((resp) => {
        expect(resp.status).to.eq(expectedStatus);
        const result = JSON.parse(JSON.stringify(resp.body));
        return result;
    })
});
