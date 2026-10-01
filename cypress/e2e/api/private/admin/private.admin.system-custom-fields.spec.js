/// <reference types="cypress" />

/**
 * API tests for the admin person custom-fields lookup
 *
 * Covers:
 *   GET /admin/api/system/custom-fields/person?typeId=N
 */
describe("Admin API System Custom Fields Endpoint", () => {
    it("returns id/value pairs for a custom-field type", () => {
        cy.makePrivateAdminAPICall(
            "GET",
            "/admin/api/system/custom-fields/person?typeId=2",
            null,
            200,
        ).then((resp) => {
            expect(resp.body).to.be.an("array");
            resp.body.forEach((field) => {
                expect(field).to.have.property("id");
                expect(field).to.have.property("value");
                expect(field.value).to.be.a("string");
            });
        });
    });

    it("accepts the trailing-slash form used by the settings pages", () => {
        cy.makePrivateAdminAPICall(
            "GET",
            "/admin/api/system/custom-fields/person/?typeId=2",
            null,
            200,
        ).then((resp) => {
            expect(resp.body).to.be.an("array");
        });
    });

    it("returns an empty list when typeId is missing", () => {
        cy.makePrivateAdminAPICall(
            "GET",
            "/admin/api/system/custom-fields/person",
            null,
            200,
        ).then((resp) => {
            expect(resp.body).to.deep.eq([]);
        });
    });

    it("returns an empty list for a type with no fields", () => {
        cy.makePrivateAdminAPICall(
            "GET",
            "/admin/api/system/custom-fields/person?typeId=99999",
            null,
            200,
        ).then((resp) => {
            expect(resp.body).to.deep.eq([]);
        });
    });

    it("non-admin is denied access", () => {
        cy.makePrivateUserAPICall(
            "GET",
            "/admin/api/system/custom-fields/person?typeId=2",
            null,
            403,
        );
    });
});
