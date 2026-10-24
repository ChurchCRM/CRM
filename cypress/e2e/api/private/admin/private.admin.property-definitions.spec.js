/// <reference types="cypress" />

/**
 * API tests for deleting a property definition:
 *   DELETE /admin/api/properties/definition/{propertyId}
 *
 * The admin-only rule is covered by the admin-app row in cypress/support/gates/admin.js.
 */
describe("Admin API Property Definitions", () => {
    it("DELETE returns 404 for a non-existent property definition", () => {
        cy.makePrivateAdminAPICall(
            "DELETE",
            "/admin/api/properties/definition/999999",
            null,
            404,
        );
    });
});
