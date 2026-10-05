/// <reference types="cypress" />

/**
 * API tests for the admin user-permissions lookup
 *
 * Covers:
 *   GET /admin/api/user/{userId}/permissions
 */
describe("Admin API User Permissions Endpoint", () => {
    // tony.wade, the seed's non-admin Cypress user
    const knownUserId = 3;

    it("returns the permission flags for a user", () => {
        cy.makePrivateAdminAPICall(
            "GET",
            `/admin/api/user/${knownUserId}/permissions`,
            null,
            200,
        ).then((resp) => {
            expect(resp.body.userId).to.eq(knownUserId);
            expect(resp.body.user).to.be.a("string").and.not.be.empty;
            expect(resp.body.addEvent).to.be.a("boolean");
        });
    });

    it("returns 404 for an unknown user", () => {
        cy.makePrivateAdminAPICall("GET", "/admin/api/user/99999999/permissions", null, 404).then(
            (resp) => {
                expect(resp.body.success).to.eq(false);
                expect(resp.body.message).to.eq("User not found");
            },
        );
    });

    it("non-admin is denied access", () => {
        cy.makePrivateUserAPICall(
            "GET",
            `/admin/api/user/${knownUserId}/permissions`,
            null,
            403,
        );
    });
});
