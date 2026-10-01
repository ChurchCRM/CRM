/// <reference types="cypress" />

/**
 * API tests for the admin person-properties lookup
 *
 * Covers:
 *   GET /admin/api/system/properties/person
 */
describe("Admin API System Properties Endpoint", () => {
    it("GET /admin/api/system/properties/person returns id/value pairs", () => {
        cy.makePrivateAdminAPICall(
            "GET",
            `/admin/api/system/properties/person`,
            null,
            200,
        ).then((resp) => {
            expect(resp.body).to.be.an("array");
            if (resp.body.length > 0) {
                expect(resp.body[0]).to.have.property("id");
                expect(resp.body[0]).to.have.property("value");
                expect(resp.body[0].id).to.be.a("number");
                expect(resp.body[0].value).to.be.a("string");
            }
        });
    });

    it("non-admin is denied access", () => {
        cy.makePrivateUserAPICall(
            "GET",
            `/admin/api/system/properties/person`,
            null,
            [401, 403],
        );
    });
});
