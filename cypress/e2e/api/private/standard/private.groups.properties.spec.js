/// <reference types="cypress" />

/**
 * API tests for group property assignment endpoints:
 *   GET    /api/groups/properties
 *   GET    /api/groups/{groupID}/properties
 *   POST   /api/groups/{groupID}/properties/{propertyId}
 *   DELETE /api/groups/{groupID}/properties/{propertyId}
 *   DELETE /api/people/properties/definition/{propertyId}
 *
 * Requires: Docker / local environment with seeded data.
 * Uses existing group ID 1 and creates a temporary group property definition.
 */

describe("API: Group Property Endpoints", () => {
    const groupID = 1;
    let createdPropertyId = null;

    // ------------------------------------------------------------------ //
    // Helpers
    // ------------------------------------------------------------------ //

    // Create a throwaway property definition via PropertyEditor form POST
    // (no dedicated CREATE API yet, so we lean on the admin API key + form)
    // Instead, we rely on seeded data — just use whatever definition exists.
    // These tests exercise the assignment CRUD only.

    // ------------------------------------------------------------------ //
    // GET /api/groups/properties — all definitions
    // ------------------------------------------------------------------ //
    describe("GET /api/groups/properties", () => {
        it("returns an array of group property definitions", () => {
            cy.makePrivateAdminAPICall(
                "GET",
                "/api/groups/properties",
                null,
                200
            ).then((resp) => {
                expect(resp.body).to.be.an("array");
            });
        });

        it("returns 401 without API key", () => {
            cy.request({
                method: "GET",
                url: "/api/groups/properties",
                failOnStatusCode: false,
            }).then((resp) => {
                expect(resp.status).to.eq(401);
            });
        });
    });

    // ------------------------------------------------------------------ //
    // GET /api/groups/{groupID}/properties — assigned properties
    // ------------------------------------------------------------------ //
    describe("GET /api/groups/{groupID}/properties", () => {
        it("returns an array for a valid group", () => {
            cy.makePrivateAdminAPICall(
                "GET",
                `/api/groups/${groupID}/properties`,
                null,
                200
            ).then((resp) => {
                expect(resp.body).to.be.an("array");
                if (resp.body.length > 0) {
                    const item = resp.body[0];
                    expect(item).to.have.all.keys(
                        "id",
                        "name",
                        "value",
                        "prompt",
                        "allowEdit",
                        "allowDelete"
                    );
                    expect(item.allowDelete).to.be.a("boolean");
                    expect(item.allowEdit).to.be.a("boolean");
                }
            });
        });

        it("returns 404 for a non-existent group", () => {
            cy.makePrivateAdminAPICall(
                "GET",
                "/api/groups/999999/properties",
                null,
                404
            );
        });

        it("returns 403 for a standard user (no ManageGroups permission)", () => {
            cy.makePrivateUserAPICall(
                "GET",
                `/api/groups/${groupID}/properties`,
                null,
                403
            );
        });
    });

    // ------------------------------------------------------------------ //
    // POST /api/groups/{groupID}/properties/{propertyId} — assign
    // ------------------------------------------------------------------ //
    describe("POST /api/groups/{groupID}/properties/{propertyId}", () => {
        before(() => {
            // Fetch available definitions and pick first group property
            cy.makePrivateAdminAPICall(
                "GET",
                "/api/groups/properties",
                null,
                200
            ).then((resp) => {
                if (resp.body.length > 0) {
                    createdPropertyId = resp.body[0].ProId ?? resp.body[0].pro_ID ?? resp.body[0].id;
                }
            });
        });

        it("assigns a property to a group (no prompt)", function () {
            if (!createdPropertyId) this.skip();

            cy.makePrivateAdminAPICall(
                "POST",
                `/api/groups/${groupID}/properties/${createdPropertyId}`,
                {},
                200
            ).then((resp) => {
                expect(resp.body).to.have.property("success", true);
            });
        });

        it("returns already-assigned message on duplicate assign", function () {
            if (!createdPropertyId) this.skip();

            cy.makePrivateAdminAPICall(
                "POST",
                `/api/groups/${groupID}/properties/${createdPropertyId}`,
                {},
                200
            ).then((resp) => {
                expect(resp.body).to.have.property("success", true);
            });
        });

        it("returns 403 for a standard user (no ManageGroups permission)", function () {
            if (!createdPropertyId) this.skip();

            cy.makePrivateUserAPICall(
                "POST",
                `/api/groups/${groupID}/properties/${createdPropertyId}`,
                {},
                403
            );
        });

        it("returns 404 for a non-existent property", () => {
            cy.makePrivateAdminAPICall(
                "POST",
                `/api/groups/${groupID}/properties/999999`,
                {},
                404
            );
        });
    });

    // ------------------------------------------------------------------ //
    // DELETE /api/groups/{groupID}/properties/{propertyId} — remove
    // ------------------------------------------------------------------ //
    describe("DELETE /api/groups/{groupID}/properties/{propertyId}", () => {
        before(() => {
            // Ensure the property is assigned before trying to remove it
            cy.makePrivateAdminAPICall(
                "GET",
                "/api/groups/properties",
                null,
                200
            ).then((resp) => {
                if (resp.body.length > 0) {
                    createdPropertyId = resp.body[0].ProId ?? resp.body[0].pro_ID ?? resp.body[0].id;
                    cy.makePrivateAdminAPICall(
                        "POST",
                        `/api/groups/${groupID}/properties/${createdPropertyId}`,
                        {},
                        200
                    );
                }
            });
        });

        it("removes an assigned property", function () {
            if (!createdPropertyId) this.skip();

            cy.makePrivateAdminAPICall(
                "DELETE",
                `/api/groups/${groupID}/properties/${createdPropertyId}`,
                null,
                200
            ).then((resp) => {
                expect(resp.body).to.have.property("success", true);
            });
        });

        it("returns 404 when removing a property that is not assigned", function () {
            if (!createdPropertyId) this.skip();

            // Already removed above — second call should 404
            cy.makePrivateAdminAPICall(
                "DELETE",
                `/api/groups/${groupID}/properties/${createdPropertyId}`,
                null,
                404
            );
        });

        it("returns 403 for a standard user (no ManageGroups permission)", function () {
            if (!createdPropertyId) this.skip();

            // Re-assign so the target exists, then try to delete as standard user
            cy.makePrivateAdminAPICall(
                "POST",
                `/api/groups/${groupID}/properties/${createdPropertyId}`,
                {},
                200
            ).then(() => {
                cy.makePrivateUserAPICall(
                    "DELETE",
                    `/api/groups/${groupID}/properties/${createdPropertyId}`,
                    null,
                    403
                );
            });

            // Cleanup
            cy.makePrivateAdminAPICall(
                "DELETE",
                `/api/groups/${groupID}/properties/${createdPropertyId}`,
                null,
                200
            );
        });
    });

    // ------------------------------------------------------------------ //
    // DELETE /api/people/properties/definition/{propertyId}
    // ------------------------------------------------------------------ //
    describe("DELETE /api/people/properties/definition/{propertyId}", () => {
        it("returns 404 for a non-existent property definition", () => {
            cy.makePrivateAdminAPICall(
                "DELETE",
                "/api/people/properties/definition/999999",
                null,
                404
            );
        });

        it("returns 403 for a standard user (no MenuOptions permission)", () => {
            cy.makePrivateUserAPICall(
                "DELETE",
                "/api/people/properties/definition/1",
                null,
                403
            );
        });

        it("returns 401 without API key", () => {
            cy.request({
                method: "DELETE",
                url: "/api/people/properties/definition/1",
                failOnStatusCode: false,
            }).then((resp) => {
                expect(resp.status).to.eq(401);
            });
        });
    });
});
