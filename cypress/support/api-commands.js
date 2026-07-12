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

/**
 * EditSelf+Notes user — regression sentinel for FamilyReadMiddleware vs FamilyMiddleware.
 *
 * User: lena.black (ID 100, family 20) with usr_EditSelf=1, usr_Notes=1 in DB.
 *
 * Post-PR#9016 (EditSelf exclusive mode): hasNoAdminPermissions() returns true for any
 * user with isEditSelf()=true, regardless of Notes. AuthMiddleware therefore blocks this
 * user (403) before reaching FamilyReadMiddleware or FamilyMiddleware.
 *
 * Future use: if EditSelf exclusivity is ever relaxed to permit EditSelf+Notes, this user
 * should get 200 on avatar/nav/photo (FamilyReadMiddleware, canReadFamily=true) and 403 on
 * full profile/notes for non-own family 1 (FamilyMiddleware, canViewFamily=false). That
 * would make these tests detect a FamilyReadMiddleware→FamilyMiddleware regression.
 */
Cypress.Commands.add(
    "makePrivateEditSelfPlusNotesAPICall",
    (method, url, body, expectedStatus = 200, timeoutMs) => {
        return cy.makePrivateAPICall(
            Cypress.env("selfedit.plus.notes.api.key"),
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
        // usr_EditSelf=1 — an EditSelf-ONLY user (NOT a zero-permission user).
        // EditSelf is exclusive, so AuthMiddleware::isEditSelfExclusive() blocks
        // this user → always returns 403.
        // Use this fixture ONLY to verify that Notes-gated endpoints return 403.
        // Do NOT use for routes that should return 200 for authenticated users
        // (e.g. timeline) — use makePrivateEditRecordsAPICall instead.
        //
        // For a genuinely zero-permission user (all flags 0, EditSelf=0) see
        // noperm.user (id=901), which now passes the gate with read-only access
        // under the read-default policy (#9003).
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
    "makePrivateEditRecordsAPICall",
    (method, url, body, expectedStatus = 200, timeoutMs) => {
        // judith.matthews (id=95): usr_EditRecords=1, usr_Notes=0, usr_Admin=0.
        // Passes AuthMiddleware (has EditRecords permission) but canReadNotes()
        // returns false (no Notes flag). Use for testing routes that should
        // return 200 to authenticated users but strip note items (e.g. timeline).
        return cy.makePrivateAPICall(
            Cypress.env("editrecords.api.key"),
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
            // Prevent the browser session cookie from being sent alongside the API key.
            // cy.request() shares the browser cookie jar by default; sending both the
            // session cookie and x-api-key causes PHP's AuthenticationManager to
            // overwrite $_SESSION['AuthenticationProvider'] with APITokenAuthentication,
            // which breaks subsequent browser page loads on the same session.
            withCredentials: false,
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
