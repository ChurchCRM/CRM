/// <reference types="cypress" />

/**
 * API tests for PropertyType CRUD endpoints
 *
 * Covers:
 *   GET    /admin/api/property-types
 *   POST   /admin/api/property-types
 *   GET    /admin/api/property-types/{id}
 *   PUT    /admin/api/property-types/{id}
 *   DELETE /admin/api/property-types/{id}
 *
 * The admin-only rule for these routes is covered by
 * the admin-app row in cypress/support/gates/admin.js.
 */
describe("API Private Property Types", () => {
    beforeEach(() => {
        cy.setupAdminSession();
    });

    describe("GET /admin/api/property-types - List property types", () => {
        it("Returns 200 with propertyTypes array", () => {
            cy.makePrivateAdminAPICall(
                "GET",
                "/admin/api/property-types",
                null,
                200,
            ).then((response) => {
                expect(response.body).to.have.property("propertyTypes");
                expect(response.body.propertyTypes).to.be.an("array");
            });
        });

        it("Filters by class", () => {
            cy.makePrivateAdminAPICall(
                "GET",
                "/admin/api/property-types?class=p",
                null,
                200,
            ).then((response) => {
                response.body.propertyTypes.forEach((pt) => {
                    expect(pt.class).to.equal("p");
                });
            });
        });

        it("Rejects invalid class filter with 400", () => {
            cy.makePrivateAdminAPICall(
                "GET",
                "/admin/api/property-types?class=zz",
                null,
                400,
            );
        });
    });

    describe("POST /admin/api/property-types - Create property type", () => {
        it("Creates a property type and returns 201", () => {
            const name = `Cypress PT ${Date.now()}`;
            cy.makePrivateAdminAPICall(
                "POST",
                "/admin/api/property-types",
                { class: "p", name, description: "Test description" },
                201,
            ).then((response) => {
                const pt = response.body.propertyType;
                expect(pt.id).to.be.a("number");
                expect(pt.class).to.equal("p");
                expect(pt.name).to.equal(name);
                expect(pt.description).to.equal("Test description");

                cy.makePrivateAdminAPICall(
                    "DELETE",
                    `/admin/api/property-types/${pt.id}`,
                    null,
                    200,
                );
            });
        });

        it("Returns 400 when class is invalid", () => {
            cy.makePrivateAdminAPICall(
                "POST",
                "/admin/api/property-types",
                { class: "x", name: "bad class" },
                400,
            );
        });

        it("Returns 400 when name is empty", () => {
            cy.makePrivateAdminAPICall(
                "POST",
                "/admin/api/property-types",
                { class: "f", name: "" },
                400,
            );
        });
    });

    describe("GET /admin/api/property-types/{id}", () => {
        it("Returns 404 for non-existent property type", () => {
            cy.makePrivateAdminAPICall(
                "GET",
                "/admin/api/property-types/999999",
                null,
                404,
            );
        });

        it("Returns 200 with object for existing property type", () => {
            cy.makePrivateAdminAPICall(
                "POST",
                "/admin/api/property-types",
                { class: "g", name: `Cypress PT GET ${Date.now()}` },
                201,
            ).then((createResp) => {
                const id = createResp.body.propertyType.id;
                cy.makePrivateAdminAPICall(
                    "GET",
                    `/admin/api/property-types/${id}`,
                    null,
                    200,
                ).then((getResp) => {
                    expect(getResp.body.propertyType.id).to.equal(id);
                    expect(getResp.body.propertyType.class).to.equal("g");
                });
                cy.makePrivateAdminAPICall(
                    "DELETE",
                    `/admin/api/property-types/${id}`,
                    null,
                    200,
                );
            });
        });
    });

    describe("PUT /admin/api/property-types/{id}", () => {
        it("Returns 404 for non-existent property type", () => {
            cy.makePrivateAdminAPICall(
                "PUT",
                "/admin/api/property-types/999999",
                { name: "nope" },
                404,
            );
        });

        it("Updates class, name, and description", () => {
            cy.makePrivateAdminAPICall(
                "POST",
                "/admin/api/property-types",
                { class: "p", name: `Cypress PT PUT ${Date.now()}` },
                201,
            ).then((createResp) => {
                const id = createResp.body.propertyType.id;
                cy.makePrivateAdminAPICall(
                    "PUT",
                    `/admin/api/property-types/${id}`,
                    {
                        class: "f",
                        name: "Updated PT Name",
                        description: "Updated desc",
                    },
                    200,
                ).then((updateResp) => {
                    expect(updateResp.body.propertyType.class).to.equal("f");
                    expect(updateResp.body.propertyType.name).to.equal(
                        "Updated PT Name",
                    );
                    expect(updateResp.body.propertyType.description).to.equal(
                        "Updated desc",
                    );
                });
                cy.makePrivateAdminAPICall(
                    "DELETE",
                    `/admin/api/property-types/${id}`,
                    null,
                    200,
                );
            });
        });

        it("Returns 400 when updating to invalid class", () => {
            cy.makePrivateAdminAPICall(
                "POST",
                "/admin/api/property-types",
                { class: "p", name: `Cypress PT BadClass ${Date.now()}` },
                201,
            ).then((createResp) => {
                const id = createResp.body.propertyType.id;
                cy.makePrivateAdminAPICall(
                    "PUT",
                    `/admin/api/property-types/${id}`,
                    { class: "zz" },
                    400,
                );
                cy.makePrivateAdminAPICall(
                    "DELETE",
                    `/admin/api/property-types/${id}`,
                    null,
                    200,
                );
            });
        });
    });

    describe("DELETE /admin/api/property-types/{id}", () => {
        it("Returns 404 for non-existent property type", () => {
            cy.makePrivateAdminAPICall(
                "DELETE",
                "/admin/api/property-types/999999",
                null,
                404,
            );
        });

        it("Deletes an existing property type", () => {
            cy.makePrivateAdminAPICall(
                "POST",
                "/admin/api/property-types",
                { class: "p", name: `Cypress PT DEL ${Date.now()}` },
                201,
            ).then((createResp) => {
                const id = createResp.body.propertyType.id;
                cy.makePrivateAdminAPICall(
                    "DELETE",
                    `/admin/api/property-types/${id}`,
                    null,
                    200,
                ).then((delResp) => {
                    expect(delResp.body).to.have.property("success", true);
                });
                cy.makePrivateAdminAPICall(
                    "GET",
                    `/admin/api/property-types/${id}`,
                    null,
                    404,
                );
            });
        });

        it("Returns 409 when deleting a property type that is still in use", () => {
            // Seed data: property types 1/2/3 each have a child property_pro row.
            // The DELETE route must block with 409 Conflict rather than cascade-delete.
            cy.makePrivateAdminAPICall(
                "DELETE",
                "/admin/api/property-types/1",
                null,
                409,
            ).then((resp) => {
                expect(resp.body).to.have.property("success", false);
                expect(resp.body).to.have.property("message");
                expect(resp.body.message).to.match(/in use|still reference/i);
            });
        });
    });
});
