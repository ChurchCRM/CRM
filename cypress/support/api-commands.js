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
    (method, url, body, expectedStatus = 200, timeoutMs) => {
        return cy.makePrivateAPICall(
            Cypress.env("admin.api.key"),
            method,
            url,
            body,
            expectedStatus,
            timeoutMs,
        );
    },
);

Cypress.Commands.add(
    "makePrivateUserAPICall",
    (method, url, body, expectedStatus = 200, timeoutMs) => {
        return cy.makePrivateAPICall(
            Cypress.env("user.api.key"),
            method,
            url,
            body,
            expectedStatus,
            timeoutMs,
        );
    },
);

Cypress.Commands.add(
    "makePrivateNoFinanceAPICall",
    (method, url, body, expectedStatus = 200, timeoutMs) => {
        return cy.makePrivateAPICall(
            Cypress.env("nofinance.api.key"),
            method,
            url,
            body,
            expectedStatus,
            timeoutMs,
        );
    },
);

Cypress.Commands.add(
    "makePrivatePlainAuthAPICall",
    (method, url, body, expectedStatus = 200, timeoutMs) => {
        return cy.makePrivateAPICall(
            Cypress.env("plainauth.api.key"),
            method,
            url,
            body,
            expectedStatus,
            timeoutMs,
        );
    },
);

Cypress.Commands.add(
    "makePrivateEditSelfAPICall",
    (method, url, body, expectedStatus = 200, timeoutMs) => {
        return cy.makePrivateAPICall(
            Cypress.env("selfedit.api.key"),
            method,
            url,
            body,
            expectedStatus,
            timeoutMs,
        );
    },
);

Cypress.Commands.add(
    "makePrivateLimitedAPICall",
    (method, url, body, expectedStatus = 200, timeoutMs) => {
        // limited.user (id=4): usr_Notes=0, usr_Admin=0, usr_EditRecords=0,
        // usr_EditSelf=0 — truly zero-permission user. Use this fixture to test
        // that endpoints requiring the Notes flag correctly return 403.
        return cy.makePrivateAPICall(
            Cypress.env("limited.api.key"),
            method,
            url,
            body,
            expectedStatus,
            timeoutMs,
        );
    },
);

Cypress.Commands.add(
    "makePrivateAPICall",
    (key, method, url, body, expectedStatus = 200, timeoutMs) => {
        const requestOptions = {
            method: method,
            failOnStatusCode: false,
            url: url,
            headers: {
                "content-type": "application/json",
                "x-api-key": key,
            },
            body: body,
        };

        if (typeof timeoutMs === 'number') {
            requestOptions.timeout = timeoutMs;
        }

        return cy.request(requestOptions).then((resp) => {
            // Handle single status code or array of acceptable status codes
            const acceptedStatuses = Array.isArray(expectedStatus) ? expectedStatus : [expectedStatus];
            expect(resp.status).to.be.oneOf(acceptedStatuses);

            // Return the full response object so tests can access resp.body
            return resp;
        });
    },
);

// Modern API testing command with better error handling
Cypress.Commands.add(
    "apiRequest",
    (options) => {
        const defaultOptions = {
            failOnStatusCode: false,
            timeout: 10000,
        };
        
        return cy.request({...defaultOptions, ...options}).then((response) => {
            // Log response for debugging
            cy.log(`API ${options.method} ${options.url} - Status: ${response.status}`);
            return cy.wrap(response);
        });
    },
);
