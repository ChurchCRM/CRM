// ***********************************************
// This example commands.js shows you how to
// create various custom commands and overwrite
// existing commands.
//
// For more comprehensive examples of custom
// commands please read more here:
// https://on.cypress.io/custom-commands
// ***********************************************

// -- Modern API command patterns --
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
            headers: { 
                "content-type": "application/json", 
                "x-api-key": key 
            },
            body: body,
        }).then((resp) => {
            expect(resp.status).to.eq(expectedStatus);

            if (!resp.body) {
                return null;
            }

            // More robust response handling
            try {
                return typeof resp.body === 'string' ? JSON.parse(resp.body) : resp.body;
            } catch (e) {
                cy.log('Warning: Could not parse response body as JSON');
                return resp.body;
            }
        });
    },
);

// Modern API testing command with better error handling
Cypress.Commands.add(
    "apiRequest",
    (options) => {
        const defaultOptions = {
            failOnStatusCode: false,
            timeout: 30000,
        };
        
        return cy.request({...defaultOptions, ...options}).then((response) => {
            // Log response for debugging
            cy.log(`API ${options.method} ${options.url} - Status: ${response.status}`);
            return cy.wrap(response);
        });
    },
);
