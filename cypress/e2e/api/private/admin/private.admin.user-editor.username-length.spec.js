/// <reference types="cypress" />

// Regression coverage for #9831: usr_UserName is VARCHAR(50) in
// src/mysql/install/Install.sql / orm/schema.xml. UserService::createUser()
// and ::updateUser() enforce that width server-side (in addition to the
// existing 3-character minimum) so a login name can never be silently
// truncated by the DB.
//
// Person 10 (Tom Hart, tom.gardner@example.com) has no seeded user_usr row
// and is not used as a throwaway person by any other spec — safe to
// create/delete here.
describe("API Private Admin User Editor - username length validation (#9831)", () => {
    const personId = 10;

    function postAdminForm(url, body) {
        // Mirrors cy.makePrivateAPICall() (cypress/support/api-commands.js) but
        // with followRedirect:false so the 302-on-success vs 200-on-validation-
        // error distinction from adminUserEditorNew/Edit (src/admin/routes/system.php)
        // is visible instead of being silently followed.
        return cy.request({
            method: "POST",
            url,
            failOnStatusCode: false,
            followRedirect: false,
            withCredentials: false,
            headers: {
                "content-type": "application/json",
                "x-api-key": Cypress.env("admin.api.key"),
            },
            body,
        });
    }

    function cleanupUser() {
        // Trailing slash required: the DELETE route is registered as
        // group('/api/user/{userId:[0-9]+}') + delete('/') in
        // src/admin/routes/api/user-admin.php, so the full path is
        // .../user/{id}/ — without the slash Slim 404s on no route match
        // (masked by the acceptable-status list below) and the account is
        // never actually removed, which silently breaks the create -> delete
        // -> recreate cycle this spec relies on between tests.
        cy.makePrivateAdminAPICall("DELETE", `/admin/api/user/${personId}/`, null, [200, 204, 404]);
    }

    beforeEach(() => {
        cleanupUser();
    });

    after(() => {
        cleanupUser();
    });

    it("rejects a UserName shorter than 3 characters on create", () => {
        postAdminForm("/admin/system/users/new", {
            PersonID: personId,
            UserName: "ab",
        }).then((resp) => {
            // Validation failure re-renders the form (200), it does not redirect.
            expect(resp.status).to.eq(200);
            expect(resp.body).to.include("Login must be at least 3 characters");
        });
    });

    it("rejects a UserName longer than 50 characters on create", () => {
        const tooLong = "a".repeat(51);
        postAdminForm("/admin/system/users/new", {
            PersonID: personId,
            UserName: tooLong,
        }).then((resp) => {
            expect(resp.status).to.eq(200);
            expect(resp.body).to.include("Login must be 50 characters or fewer");
        });
    });

    it("accepts a UserName of exactly 50 characters on create and persists it in full", () => {
        const boundary = "a".repeat(50);
        postAdminForm("/admin/system/users/new", {
            PersonID: personId,
            UserName: boundary,
        }).then((resp) => {
            expect(resp.status).to.eq(302);
            expect(resp.headers.location).to.include("/admin/system/users");
        });

        cy.makePrivateAdminAPICall("GET", `/admin/system/users/${personId}/edit`, null, 200).then((resp) => {
            // Confirms the DB column actually stored all 50 characters (no
            // silent truncation) rather than just checking the HTTP status.
            expect(resp.body).to.include(boundary);
        });
    });

    it("rejects a UserName longer than 50 characters on update", () => {
        postAdminForm("/admin/system/users/new", {
            PersonID: personId,
            UserName: "tom.gardner.editor.test",
        }).then((resp) => {
            expect(resp.status).to.eq(302);
        });

        const tooLong = "b".repeat(51);
        postAdminForm(`/admin/system/users/${personId}/edit`, {
            UserName: tooLong,
        }).then((resp) => {
            expect(resp.status).to.eq(200);
            expect(resp.body).to.include("Login must be 50 characters or fewer");
        });
    });
});
